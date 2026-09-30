<?php

declare(strict_types=1);

namespace App\Application\Search;

use App\Domain\Acquisition\SourceId;

final readonly class DirectSearchCandidate
{
    public function __construct(
        public SourceId $sourceId,
        public ?string $sourceName,
        public string $sourceTypeKey,
        public bool $stale,
        public string $matchedValue,
        public string $field,
        public SearchValueOrigin $origin,
        public ?string $mentionId = null,
        public ?string $claimId = null,
        public ?string $claimRevisionId = null,
        public ?string $language = null,
        public ?string $script = null,
        public ?string $representationRelation = null,
    ) {}
}
