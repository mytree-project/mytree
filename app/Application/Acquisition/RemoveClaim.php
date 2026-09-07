<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\Claim;
use App\Domain\Acquisition\ClaimId;
use App\Domain\Acquisition\SourceId;

final readonly class RemoveClaim
{
    public function __construct(private ClaimRepository $claims) {}

    public function handle(SourceId $sourceId, ClaimId $claimId): Claim
    {
        $claim = $this->claims->find($sourceId, $claimId)
            ?? throw ClaimNotFound::forSourceAndId($sourceId, $claimId);

        $this->claims->remove($sourceId, $claimId);

        return $claim;
    }
}
