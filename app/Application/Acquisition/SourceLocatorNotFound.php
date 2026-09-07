<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\ClaimId;
use App\Domain\Acquisition\SourceId;
use App\Domain\Acquisition\SourceLocatorId;
use RuntimeException;

final class SourceLocatorNotFound extends RuntimeException
{
    public static function forClaimAndId(SourceId $sourceId, ClaimId $claimId, SourceLocatorId $locatorId): self
    {
        return new self(sprintf(
            'Source locator "%s" was not found for Claim "%s" in Source "%s".',
            $locatorId->value,
            $claimId->value,
            $sourceId->value,
        ));
    }
}
