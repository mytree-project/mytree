<?php

declare(strict_types=1);

namespace App\Application\Search;

use InvalidArgumentException;

final readonly class SearchPage
{
    /** @var list<SearchResult> */
    public array $results;

    /** @var list<SearchResultCategory> */
    public array $categories;

    /**
     * @param  list<SearchResult>  $results
     * @param  list<SearchResultCategory>  $categories
     */
    public function __construct(
        array $results,
        public int $page,
        public int $perPage,
        public ?int $total,
        public bool $hasMore,
        public bool $candidateWindowTruncated,
        array $categories = [SearchResultCategory::Source],
    ) {
        foreach ($results as $result) {
            if (in_array($result->category, $categories, true) === false) {
                throw new InvalidArgumentException('Search page results must belong to requested categories.');
            }
        }

        $this->categories = array_values(array_unique($categories, SORT_REGULAR));
        $this->results = $results;
    }
}
