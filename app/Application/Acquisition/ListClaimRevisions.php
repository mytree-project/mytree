<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\ClaimId;
use App\Domain\Acquisition\ClaimRevision;
use App\Domain\Acquisition\SourceId;

final readonly class ListClaimRevisions
{
    public function __construct(private ClaimRevisionRepository $revisions) {}

    /** @return list<ClaimRevision> */
    public function handle(SourceId $sourceId, ClaimId $claimId): array
    {
        return $this->revisions->forClaim($sourceId, $claimId);
    }
}
