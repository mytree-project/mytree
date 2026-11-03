<?php

declare(strict_types=1);

namespace App\Application\Search;

use InvalidArgumentException;

final readonly class SearchResultScore
{
    public function __construct(
        public string $scale,
        public int|float $value,
    ) {
        if (trim($scale) === '' || is_finite((float) $value) === false) {
            throw new InvalidArgumentException('Search result score requires a scale and finite value.');
        }
    }
}
