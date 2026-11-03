<?php

declare(strict_types=1);

namespace App\Application\Search;

interface DirectSearchCandidateRepository
{
    /**
     * @param  list<SearchQueryTerm>  $terms
     * @param  list<string>  $sourceTypeKeys
     */
    public function find(
        array $terms,
        array $sourceTypeKeys,
        int $limit,
        ?SearchDateSpan $dateRange = null,
        ?SearchQueryTerm $place = null,
        SearchSortMode $sort = SearchSortMode::Relevance,
    ): DirectSearchCandidateBatch;

    /**
     * Retrieves a bounded Source-complete fuzzy candidate window; similarity
     * and matching policy are evaluated by Application Search.
     *
     * @param  list<SearchQueryTerm>  $terms
     * @param  list<string>  $sourceTypeKeys
     */
    /**
     * @param  list<string>  $sourceTypeKeys
     */
    public function findSources(
        array $sourceTypeKeys,
        int $limit,
        ?SearchDateSpan $dateRange = null,
        ?SearchQueryTerm $place = null,
        SearchSortMode $sort = SearchSortMode::Relevance,
    ): SearchSourceCandidateBatch;

    public function findFuzzy(
        array $terms,
        array $sourceTypeKeys,
        int $limit,
        ?SearchDateSpan $dateRange = null,
        ?SearchQueryTerm $place = null,
        SearchSortMode $sort = SearchSortMode::Relevance,
    ): DirectSearchCandidateBatch;
}
