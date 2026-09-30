<?php

declare(strict_types=1);

namespace App\Application\Search;

final readonly class SearchMatchReason
{
    public function __construct(
        public string $queryTerm,
        public SearchMatchType $type,
        public string $field,
        public string $matchedValue,
        public int $weight,
        public ?string $mentionId = null,
        public ?string $claimId = null,
        public ?string $claimRevisionId = null,
        public ?string $language = null,
        public ?string $script = null,
        public ?string $representationRelation = null,
    ) {}
}
