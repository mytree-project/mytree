<?php

declare(strict_types=1);

namespace App\Application\Search;

use App\Domain\Acquisition\SourceId;

final readonly class SearchSourceCandidate
{
    public function __construct(
        public SourceId $sourceId,
        public ?string $sourceName,
        public string $sourceTypeKey,
        public bool $stale,
        public ?int $sourceDateOrdinal,
        public string $sourceCreatedAt,
    ) {}
}
