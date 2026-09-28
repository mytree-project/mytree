<?php

declare(strict_types=1);

namespace App\Application\Search;

use App\Domain\Acquisition\SourceId;

final readonly class SearchSources
{
    public function __construct(
        private DirectSearchCandidateRepository $candidates,
        private SearchRuntimeConfiguration $configuration,
    ) {}

    public function handle(SearchRequest $request): SearchPage
    {
        $terms = $request->terms();
        $perPage = min($request->perPage, $this->configuration->maxPageSize);

        if ($terms === []) {
            return new SearchPage([], $request->page, $perPage, 0, false, false);
        }

        $batch = $this->candidates->find(
            terms: $terms,
            sourceTypeKeys: $request->sourceTypeKeys,
            limit: $this->configuration->candidateLimit,
        );

        /** @var array<string, array{source_id: SourceId, source_name: ?string, source_type_key: string, stale: bool, reasons: list<SearchMatchReason>, matched_terms: array<string, true>}> $grouped */
        $grouped = [];

        foreach ($batch->candidates as $candidate) {
            $sourceKey = $candidate->sourceId->value;
            $reason = $this->reasonFor($candidate);

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
            $grouped[$sourceKey]['matched_terms'][$reason->queryTerm] = true;
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

    private function reasonFor(DirectSearchCandidate $candidate): SearchMatchReason
    {
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

        return new SearchMatchReason(
            queryTerm: $candidate->matchedValue,
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

    /** @param list<SearchMatchReason> $reasons @return list<SearchMatchReason> */
    private function deduplicateReasons(array $reasons): array
    {
        $unique = [];
        foreach ($reasons as $reason) {
            $key = implode('|', [
                $reason->queryTerm,
                $reason->type->value,
                $reason->field,
                $reason->matchedValue,
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

    /** @param list<SearchMatchReason> $reasons @param list<string> $terms */
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

    /** @param list<SearchMatchReason> $reasons @param list<string> $terms */
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
