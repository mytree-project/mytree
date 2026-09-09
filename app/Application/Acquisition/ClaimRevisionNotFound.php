<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\ClaimId;
use App\Domain\Acquisition\SourceId;
use RuntimeException;

final class ClaimRevisionNotFound extends RuntimeException
{
    public static function forClaimAndNumber(
        SourceId $sourceId,
        ClaimId $claimId,
        int $revisionNumber,
    ): self {
        return new self(sprintf(
            'Claim revision %d was not found for Claim "%s" in Source "%s".',
            $revisionNumber,
            $claimId->value,
            $sourceId->value,
        ));
    }
}
