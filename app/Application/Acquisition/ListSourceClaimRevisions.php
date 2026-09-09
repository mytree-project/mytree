<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\ClaimRevision;
use App\Domain\Acquisition\SourceId;

final readonly class ListSourceClaimRevisions
{
    public function __construct(private ClaimRevisionRepository $revisions) {}

    /** @return list<ClaimRevision> */
    public function handle(SourceId $sourceId): array
    {
        return $this->revisions->forSource($sourceId);
    }
}
