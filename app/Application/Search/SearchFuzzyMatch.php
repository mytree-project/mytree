<?php

declare(strict_types=1);

namespace App\Application\Search;

final readonly class SearchFuzzyMatch
{
    public function __construct(
        public string $queryForm,
        public string $matchedForm,
        public float $similarity,
    ) {}
}
