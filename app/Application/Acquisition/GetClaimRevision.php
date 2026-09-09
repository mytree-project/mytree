<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\ClaimId;
use App\Domain\Acquisition\ClaimRevision;
use App\Domain\Acquisition\SourceId;

final readonly class GetClaimRevision
{
    public function __construct(private ClaimRevisionRepository $revisions) {}

    public function handle(SourceId $sourceId, ClaimId $claimId, int $revisionNumber): ClaimRevision
    {
        return $this->revisions->findForClaim($sourceId, $claimId, $revisionNumber)
            ?? throw ClaimRevisionNotFound::forClaimAndNumber($sourceId, $claimId, $revisionNumber);
    }
}
