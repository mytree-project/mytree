<?php

declare(strict_types=1);

namespace App\Application\Search;

interface DirectSearchCandidateRepository
{
    /**
     * @param  list<string>  $terms
     * @param  list<string>  $sourceTypeKeys
     */
    public function find(
        array $terms,
        array $sourceTypeKeys,
        int $limit,
    ): DirectSearchCandidateBatch;
}
