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

    /** @param  list<string>  $sourceTypeKeys */
    public function findSources(
        array $sourceTypeKeys,
        int $limit,
        ?SearchDateSpan $dateRange = null,
        ?SearchQueryTerm $place = null,
        SearchSortMode $sort = SearchSortMode::Relevance,
    ): SearchSourceCandidateBatch;

    /**
     * Retrieves a bounded Source-complete fuzzy candidate window.
     *
     * @param  list<SearchQueryTerm>  $terms
     * @param  list<string>  $sourceTypeKeys
     */
    public function findFuzzy(
        array $terms,
        array $sourceTypeKeys,
        int $limit,
        ?SearchDateSpan $dateRange = null,
        ?SearchQueryTerm $place = null,
        SearchSortMode $sort = SearchSortMode::Relevance,
    ): DirectSearchCandidateBatch;
}
