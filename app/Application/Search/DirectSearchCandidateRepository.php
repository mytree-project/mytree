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
    ): DirectSearchCandidateBatch;

    /**
     * Retrieves a bounded Source-complete fuzzy candidate window; similarity
     * and matching policy are evaluated by Application Search.
     *
     * @param  list<SearchQueryTerm>  $terms
     * @param  list<string>  $sourceTypeKeys
     */
    public function findFuzzy(
        array $terms,
        array $sourceTypeKeys,
        int $limit,
    ): DirectSearchCandidateBatch;
}
