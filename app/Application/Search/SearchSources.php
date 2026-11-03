<?php

declare(strict_types=1);

namespace App\Application\Search;

use App\Domain\Acquisition\SourceId;

final readonly class SearchSources
{
    public function __construct(
        private DirectSearchCandidateRepository $candidates,
        private SearchRuntimeConfiguration $configuration,
        private SearchNameProcessor $nameProcessor,
        private SearchIndexProfile $indexProfile,
        private SearchVariantDictionary $dictionary,
        private SearchFuzzyMatcher $fuzzyMatcher,
    ) {}

    public function handle(SearchRequest $request): SearchPage
    {
        $terms = $request->terms();
        $perPage = min($request->perPage, $this->configuration->maxPageSize);

        // M5 has only a local Source producer. Unsupported category requests
        // never trigger a Source query or manufacture external observations.
        if (! $request->includesCategory(SearchResultCategory::Source)) {
            return new SearchPage([], $request->page, $perPage, 0, false, false, $request->categories);
        }

        if ($terms === [] && ! $request->hasScope()) {
            return new SearchPage([], $request->page, $perPage, 0, false, false, $request->categories);
        }

        $placeTerm = $request->place !== null && trim($request->place) !== ''
            ? $this->makeTerm(trim($request->place), SearchNameType::PlaceName)
            : null;

        if ($terms === []) {
            $batch = $this->candidates->findSources(
                sourceTypeKeys: $request->sourceTypeKeys,
                limit: $this->configuration->candidateLimit,
                dateRange: $request->dateRange,
                place: $placeTerm,
                sort: $request->sort,
            );
            $results = array_map(
                static fn (SearchSourceCandidate $candidate): SearchSourceResult => new SearchSourceResult(
                    sourceId: $candidate->sourceId,
                    sourceName: $candidate->sourceName,
                    sourceTypeKey: $candidate->sourceTypeKey,
                    score: 0,
                    stale: $candidate->stale,
                    matchReasons: [],
                    sourceDateOrdinal: $candidate->sourceDateOrdinal,
                    sourceCreatedAt: $candidate->sourceCreatedAt,
                ),
                $batch->sources,
            );

            return $this->page($results, $request, $perPage, $batch->truncated);
        }

        $queryTerms = array_map(
            fn (string $term): SearchQueryTerm => $this->makeTerm($term),
            $terms,
        );

        $batch = $this->candidates->find(
            terms: $queryTerms,
            sourceTypeKeys: $request->sourceTypeKeys,
            limit: $this->configuration->candidateLimit,
            dateRange: $request->dateRange,
            place: $placeTerm,
            sort: $request->sort,
        );
        $fuzzyBatch = $this->configuration->fuzzyEnabled
            ? $this->candidates->findFuzzy(
                terms: $queryTerms,
                sourceTypeKeys: $request->sourceTypeKeys,
                limit: $this->configuration->fuzzyCandidateLimit,
                dateRange: $request->dateRange,
                place: $placeTerm,
                sort: $request->sort,
            )
            : new DirectSearchCandidateBatch([], false);
        $truncated = $batch->truncated || $fuzzyBatch->truncated;

        /** @var array<string, array{source_id: SourceId, source_name: ?string, source_type_key: string, stale: bool, source_date_ordinal: ?int, source_created_at: string, reasons: list<SearchMatchReason>, matched_terms: array<string, true>}> $grouped */
        $grouped = [];

        foreach ([...$batch->candidates, ...$fuzzyBatch->candidates] as $candidate) {
            $sourceKey = $candidate->sourceId->value;
            foreach ($queryTerms as $term) {
                $match = $this->matchedForm($candidate, $term);
                if ($match !== null) {
                    $reason = $this->reasonFor($candidate, $term->value, $match['form'], $match['variant']);
                } else {
                    if (! $this->configuration->fuzzyEnabled) {
                        continue;
                    }

                    $fuzzyMatch = $this->fuzzyMatcher->match($term, $candidate, $this->configuration->fuzzyThreshold);
                    if ($fuzzyMatch === null) {
                        continue;
                    }

                    $reason = $this->fuzzyReasonFor($candidate, $term->value, $fuzzyMatch);
                }
                if (! isset($grouped[$sourceKey])) {
                    $grouped[$sourceKey] = [
                        'source_id' => $candidate->sourceId,
                        'source_name' => $candidate->sourceName,
                        'source_type_key' => $candidate->sourceTypeKey,
                        'stale' => $candidate->stale,
                        'source_date_ordinal' => $candidate->sourceDateOrdinal,
                        'source_created_at' => $candidate->sourceCreatedAt,
                        'reasons' => [],
                        'matched_terms' => [],
                    ];
                }

                $grouped[$sourceKey]['reasons'][] = $reason;
                $grouped[$sourceKey]['matched_terms'][$term->value] = true;
            }
        }

        $results = [];
        foreach ($grouped as $group) {
            if (! $this->containsAllTerms($group['matched_terms'], $terms)) {
                continue;
            }

            $reasons = $this->deduplicateReasons($group['reasons']);
            $results[] = new SearchSourceResult(
                sourceId: $group['source_id'],
                sourceName: $group['source_name'],
                sourceTypeKey: $group['source_type_key'],
                score: $this->score($reasons, $terms),
                stale: $group['stale'],
                matchReasons: $reasons,
                sourceDateOrdinal: $group['source_date_ordinal'],
                sourceCreatedAt: $group['source_created_at'],
            );
        }

        return $this->page($results, $request, $perPage, $truncated);
    }

    private function makeTerm(string $term, ?SearchNameType $onlyType = null): SearchQueryTerm
    {
        $technical = array_slice(
            $this->nameProcessor->indexForms(
                new SearchNameInput($term, $onlyType ?? SearchNameType::Other),
                $this->indexProfile->nameProcessing,
            ),
            0,
            $this->configuration->queryExpansionLimit + 1,
        );
        $variants = [];
        $types = $onlyType === null
            ? [SearchNameType::GivenName, SearchNameType::Surname, SearchNameType::PlaceName]
            : [$onlyType];
        foreach ($types as $type) {
            foreach ($this->dictionary->variants($term, $type) as $candidate) {
                $forms = $this->nameProcessor->indexForms(
                    new SearchNameInput($candidate->value, $type),
                    $this->indexProfile->nameProcessing,
                );
                $variants[] = new SearchQueryVariant($candidate, $forms);
            }
        }

        // Literal/technical forms take precedence; remaining capacity is
        // assigned to deterministic dictionary candidates.
        usort($variants, static fn (SearchQueryVariant $a, SearchQueryVariant $b): int => strcmp(
            $a->field().'|'.$a->candidate->value.'|'.$a->candidate->groupId,
            $b->field().'|'.$b->candidate->value.'|'.$b->candidate->groupId,
        ));
        $available = max(0, $this->configuration->queryExpansionLimit - max(0, count($technical) - 1));

        return new SearchQueryTerm(
            value: $term,
            comparisonForms: $technical,
            variants: array_slice($variants, 0, $available),
        );
    }

    /**
     * @param  list<SearchSourceResult>  $results
     */
    private function page(array $results, SearchRequest $request, int $perPage, bool $truncated): SearchPage
    {
        usort($results, static function (SearchSourceResult $left, SearchSourceResult $right) use ($request): int {
            if ($request->sort === SearchSortMode::SourceDate) {
                if ($left->sourceDateOrdinal === null && $right->sourceDateOrdinal !== null) {
                    return 1;
                }
                if ($right->sourceDateOrdinal === null && $left->sourceDateOrdinal !== null) {
                    return -1;
                }
                $byDate = $right->sourceDateOrdinal <=> $left->sourceDateOrdinal;
                if ($byDate !== 0) {
                    return $byDate;
                }
            } elseif ($request->sort === SearchSortMode::SourceAdded) {
                $byCreated = strcmp($right->sourceCreatedAt, $left->sourceCreatedAt);
                if ($byCreated !== 0) {
                    return $byCreated;
                }
            } else {
                $byScore = $right->score <=> $left->score;
                if ($byScore !== 0) {
                    return $byScore;
                }
            }

            return strcmp($left->sourceId->value, $right->sourceId->value);
        });

        $total = $truncated ? null : count($results);
        $offset = ($request->page - 1) * $perPage;
        $pageResults = array_map(
            static fn (SearchSourceResult $source): SearchResult => new SearchResult($source),
            array_slice($results, $offset, $perPage),
        );
        $hasMore = $truncated || $offset + count($pageResults) < count($results);

        return new SearchPage(
            results: $pageResults,
            page: $request->page,
            perPage: $perPage,
            total: $total,
            hasMore: $hasMore,
            candidateWindowTruncated: $truncated,
            categories: $request->categories,
        );
    }

    /** @return array{form: string, variant: ?SearchVariantCandidate}|null */
    private function matchedForm(DirectSearchCandidate $candidate, SearchQueryTerm $term): ?array
    {
        if ($candidate->matchedValue === $term->value) {
            return ['form' => $term->value, 'variant' => null];
        }

        if ($candidate->origin === SearchValueOrigin::SourceMetadata) {
            return null;
        }

        foreach ($term->comparisonForms as $form) {
            if (in_array($form, $candidate->indexForms, true)) {
                return ['form' => $form, 'variant' => null];
            }
        }

        foreach ($term->variants as $variant) {
            if ($variant->field() !== $candidate->field) {
                continue;
            }
            foreach ($variant->forms as $form) {
                if (in_array($form, $candidate->indexForms, true)) {
                    return ['form' => $form, 'variant' => $variant->candidate];
                }
            }
        }

        return null;
    }

    private function reasonFor(
        DirectSearchCandidate $candidate,
        string $queryTerm,
        string $matchedForm,
        ?SearchVariantCandidate $variant,
    ): SearchMatchReason {
        if ($candidate->matchedValue === $queryTerm) {
            [$type, $weight] = match ($candidate->origin) {
                SearchValueOrigin::SourceMetadata => [
                    SearchMatchType::DirectSourceMetadata,
                    $this->configuration->directSourceMetadataWeight,
                ],
                SearchValueOrigin::ClaimValue => [
                    SearchMatchType::DirectClaimValue,
                    $this->configuration->directClaimValueWeight,
                ],
                SearchValueOrigin::SourceRepresentation => [
                    SearchMatchType::SourceRepresentation,
                    $this->configuration->sourceRepresentationWeight,
                ],
            };
        } elseif ($variant !== null) {
            $type = SearchMatchType::DictionaryVariant;
            $weight = $this->configuration->dictionaryVariantWeight;
        } else {
            $type = SearchMatchType::TechnicalComparison;
            $weight = $this->configuration->technicalComparisonWeight;
        }

        return new SearchMatchReason(
            queryTerm: $queryTerm,
            type: $type,
            field: $candidate->field,
            matchedValue: $candidate->matchedValue,
            weight: $weight,
            mentionId: $candidate->mentionId,
            claimId: $candidate->claimId,
            claimRevisionId: $candidate->claimRevisionId,
            language: $candidate->language,
            script: $candidate->script,
            representationRelation: $candidate->representationRelation,
            matchedForm: $matchedForm,
            datasetId: $variant?->datasetId,
            datasetVersion: $variant?->datasetVersion,
            datasetSignature: $variant?->datasetSignature,
            variantGroupId: $variant?->groupId,
        );
    }

    private function fuzzyReasonFor(
        DirectSearchCandidate $candidate,
        string $queryTerm,
        SearchFuzzyMatch $match,
    ): SearchMatchReason {
        return new SearchMatchReason(
            queryTerm: $queryTerm,
            type: SearchMatchType::FuzzyComparison,
            field: $candidate->field,
            matchedValue: $candidate->matchedValue,
            weight: (int) round($this->configuration->fuzzyComparisonWeight * $match->similarity),
            mentionId: $candidate->mentionId,
            claimId: $candidate->claimId,
            claimRevisionId: $candidate->claimRevisionId,
            language: $candidate->language,
            script: $candidate->script,
            representationRelation: $candidate->representationRelation,
            matchedForm: $match->matchedForm,
            similarity: $match->similarity,
            queryComparisonForm: $match->queryForm,
        );
    }

    /**
     * @param  array<string, true>  $matchedTerms
     * @param  list<string>  $terms
     */
    private function containsAllTerms(array $matchedTerms, array $terms): bool
    {
        foreach ($terms as $term) {
            if (! isset($matchedTerms[$term])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<SearchMatchReason>  $reasons
     * @return list<SearchMatchReason>
     */
    private function deduplicateReasons(array $reasons): array
    {
        /** @var array<string, SearchMatchReason> $unique */
        $unique = [];

        foreach ($reasons as $reason) {
            $key = implode('|', [
                $reason->queryTerm,
                $reason->type->value,
                $reason->field,
                $reason->matchedValue,
                $reason->matchedForm ?? '',
                $reason->datasetSignature ?? '',
                $reason->variantGroupId ?? '',
                $reason->queryComparisonForm ?? '',
                $reason->mentionId ?? '',
                $reason->claimId ?? '',
                $reason->claimRevisionId ?? '',
            ]);
            $unique[$key] = $reason;
        }

        $reasons = array_values($unique);
        usort($reasons, static function (SearchMatchReason $left, SearchMatchReason $right): int {
            $byTerm = strcmp($left->queryTerm, $right->queryTerm);
            if ($byTerm !== 0) {
                return $byTerm;
            }

            $byWeight = $right->weight <=> $left->weight;

            return $byWeight !== 0 ? $byWeight : strcmp($left->field, $right->field);
        });

        return $reasons;
    }

    /**
     * @param  list<SearchMatchReason>  $reasons
     * @param  list<string>  $terms
     */
    private function score(array $reasons, array $terms): int
    {
        $score = 0;
        foreach ($terms as $term) {
            $best = 0;
            foreach ($reasons as $reason) {
                if ($reason->queryTerm === $term) {
                    $best = max($best, $reason->weight);
                }
            }
            $score += $best;
        }

        if ($this->hasSameMentionCoverage($reasons, $terms)) {
            $score += $this->configuration->sameMentionBonus;
        }

        return $score;
    }

    /**
     * @param  list<SearchMatchReason>  $reasons
     * @param  list<string>  $terms
     */
    private function hasSameMentionCoverage(array $reasons, array $terms): bool
    {
        /** @var array<string, array<string, true>> $termsByMention */
        $termsByMention = [];
        foreach ($reasons as $reason) {
            if ($reason->mentionId === null) {
                continue;
            }

            $termsByMention[$reason->mentionId][$reason->queryTerm] = true;
        }

        foreach ($termsByMention as $matchedTerms) {
            if ($this->containsAllTerms($matchedTerms, $terms)) {
                return true;
            }
        }

        return false;
    }
}
