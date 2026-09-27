<?php

declare(strict_types=1);

namespace App\Application\Search;

final readonly class SearchPage
{
    /** @var list<SearchSourceResult> */
    public array $results;

    /** @param list<SearchSourceResult> $results */
    public function __construct(
        array $results,
        public int $page,
        public int $perPage,
        public ?int $total,
        public bool $hasMore,
        public bool $candidateWindowTruncated,
    ) {
        $this->results = $results;
    }
}
