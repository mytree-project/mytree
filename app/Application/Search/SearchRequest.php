<?php

declare(strict_types=1);

namespace App\Application\Search;

use App\Domain\Acquisition\HistoricalDate;
use InvalidArgumentException;

final readonly class SearchRequest
{
    /** @var list<string> */
    public array $sourceTypeKeys;

    public ?SearchDateSpan $dateRange;

    /** @var list<SearchResultCategory> */
    public array $categories;

    /**
     * @param  list<string>  $sourceTypeKeys
     * @param  list<SearchResultCategory>  $categories
     */
    public function __construct(
        public string $query,
        public int $page = 1,
        public int $perPage = 20,
        array $sourceTypeKeys = [],
        public ?string $place = null,
        public ?string $dateFrom = null,
        public ?string $dateTo = null,
        public SearchSortMode $sort = SearchSortMode::Relevance,
        array $categories = [SearchResultCategory::Source],
    ) {
        if ($page < 1 || $perPage < 1) {
            throw new InvalidArgumentException('Search page and per-page values must be positive integers.');
        }

        foreach ($categories as $category) {
            if ($category instanceof SearchResultCategory === false) {
                throw new InvalidArgumentException('Search result category must be a SearchResultCategory.');
            }
        }
        $this->categories = array_values(array_unique($categories, SORT_REGULAR));

        $this->sourceTypeKeys = array_values(array_unique(array_filter(
            array_map(static fn (string $key): string => trim($key), $sourceTypeKeys),
            static fn (string $key): bool => $key !== '',
        )));

        $from = $dateFrom === null || trim($dateFrom) === ''
            ? null
            : HistoricalDate::fromIsoString(trim($dateFrom));
        $to = $dateTo === null || trim($dateTo) === ''
            ? null
            : HistoricalDate::fromIsoString(trim($dateTo));

        $this->dateRange = $from === null && $to === null
            ? null
            : SearchDateSpan::between($from, $to);
    }

    public function includesCategory(SearchResultCategory $category): bool
    {
        return in_array($category, $this->categories, true);
    }

    public function hasScope(): bool
    {
        return $this->sourceTypeKeys !== []
            || ($this->place !== null && trim($this->place) !== '')
            || $this->dateRange !== null
            || $this->sort !== SearchSortMode::Relevance;
    }

    /** @return list<string> */
    public function terms(): array
    {
        $query = trim($this->query);
        if ($query === '') {
            return [];
        }

        $parts = preg_split('/\s+/u', $query);
        if ($parts === false) {
            throw new InvalidArgumentException('Search query could not be split into terms.');
        }

        return array_values(array_unique(array_filter(
            array_map(static fn (string $term): string => trim($term), $parts),
            static fn (string $term): bool => $term !== '',
        )));
    }
}
