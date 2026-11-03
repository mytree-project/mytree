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
    ) {}

    public function handle(SearchRequest $request): SearchPage
    {
        $terms = $request->terms();
        $perPage = min($request->perPage, $this->configuration->maxPageSize);

        if ($terms === []) {
            return new SearchPage([], $request->page, $perPage, 0, false, false);
        }

        $queryTerms = [];
        foreach ($terms as $term) {
            $technical = array_slice(
                $this->nameProcessor->indexForms(
                    new SearchNameInput($term, SearchNameType::Other),
                    $this->indexProfile->nameProcessing,
                ),
                0,
                $this->configuration->queryExpansionLimit + 1,
            );
            $variants = [];
            foreach ([SearchNameType::GivenName, SearchNameType::Surname, SearchNameType::PlaceName] as $type) {
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
            $queryTerms[] = new SearchQueryTerm(
                value: $term,
                comparisonForms: $technical,
                variants: array_slice($variants, 0, $available),
            );
        }

        $batch = $this->candidates->find(
            terms: $queryTerms,
            sourceTypeKeys: $request->sourceTypeKeys,
            limit: $this->configuration->candidateLimit,
        );

        /** @var array<string, array{source_id: SourceId, source_name: ?string, source_type_key: string, stale: bool, reasons: list<SearchMatchReason>, matched_terms: array<string, true>}> $grouped */
        $grouped = [];

        foreach ($batch->candidates as $candidate) {
            $sourceKey = $candidate->sourceId->value;
            foreach ($queryTerms as $term) {
                $match = $this->matchedForm($candidate, $term);
                if ($match === null) {
                    continue;
                }

                $reason = $this->reasonFor($candidate, $term->value, $match['form'], $match['variant']);
                if (! isset($grouped[$sourceKey])) {
                    $grouped[$sourceKey] = [
                        'source_id' => $candidate->sourceId,
                        'source_name' => $candidate->sourceName,
                        'source_type_key' => $candidate->sourceTypeKey,
                        'stale' => $candidate->stale,
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
            );
        }

        usort($results, static function (SearchSourceResult $left, SearchSourceResult $right): int {
            $byScore = $right->score <=> $left->score;

            return $byScore !== 0 ? $byScore : strcmp($left->sourceId->value, $right->sourceId->value);
        });

        $total = $batch->truncated ? null : count($results);
        $offset = ($request->page - 1) * $perPage;
        $pageResults = array_slice($results, $offset, $perPage);
        $hasMore = $batch->truncated || $offset + count($pageResults) < count($results);

        return new SearchPage(
            results: $pageResults,
            page: $request->page,
            perPage: $perPage,
            total: $total,
            hasMore: $hasMore,
            candidateWindowTruncated: $batch->truncated,
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
