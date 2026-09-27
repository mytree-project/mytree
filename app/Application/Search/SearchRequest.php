<?php

declare(strict_types=1);

namespace App\Application\Search;

use InvalidArgumentException;

final readonly class SearchRequest
{
    /** @var list<string> */
    public array $sourceTypeKeys;

    /** @param list<string> $sourceTypeKeys */
    public function __construct(
        public string $query,
        public int $page = 1,
        public int $perPage = 20,
        array $sourceTypeKeys = [],
    ) {
        if ($page < 1 || $perPage < 1) {
            throw new InvalidArgumentException('Search page and per-page values must be positive integers.');
        }

        $this->sourceTypeKeys = array_values(array_unique(array_filter(
            array_map(static fn (string $key): string => trim($key), $sourceTypeKeys),
            static fn (string $key): bool => $key !== '',
        )));
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
