<?php

declare(strict_types=1);

namespace App\Application\Search;

use InvalidArgumentException;

final readonly class SearchRuntimeConfiguration
{
    public function __construct(
        public int $maxPageSize,
        public int $candidateLimit,
        public int $queryExpansionLimit,
        public bool $fuzzyEnabled,
        public float $fuzzyThreshold,
        public int $fuzzyCandidateLimit,
        public int $directSourceMetadataWeight,
        public int $directClaimValueWeight,
        public int $sourceRepresentationWeight,
        public int $sameMentionBonus,
    ) {
        if ($maxPageSize < 1 || $candidateLimit < 1 || $queryExpansionLimit < 1 || $fuzzyCandidateLimit < 1) {
            throw new InvalidArgumentException('Search limits must be positive integers.');
        }

        if ($fuzzyThreshold < 0.0 || $fuzzyThreshold > 1.0) {
            throw new InvalidArgumentException('Fuzzy threshold must be between 0 and 1.');
        }

        if (
            $directSourceMetadataWeight < 0
            || $directClaimValueWeight < 0
            || $sourceRepresentationWeight < 0
            || $sameMentionBonus < 0
        ) {
            throw new InvalidArgumentException('Search ranking weights must not be negative.');
        }
    }
}
