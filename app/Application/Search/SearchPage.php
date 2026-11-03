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
        foreach ($categories as $category) {
            if (! $category instanceof SearchResultCategory) {
                throw new InvalidArgumentException('Search page category must be a SearchResultCategory.');
            }
        }

        foreach ($results as $result) {
            if (! $result instanceof SearchResult || ! in_array($result->category, $categories, true)) {
                throw new InvalidArgumentException('Search page results must be typed and within requested categories.');
            }
        }

        $this->categories = array_values(array_unique($categories, SORT_REGULAR));
        $this->results = array_values($results);
    }
}
