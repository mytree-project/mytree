<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\ClaimId;
use App\Domain\Acquisition\SourceId;
use RuntimeException;

final class ClaimNotFound extends RuntimeException
{
    public static function forSourceAndId(SourceId $sourceId, ClaimId $claimId): self
    {
        return new self(sprintf('Claim "%s" was not found for Source "%s".', $claimId->value, $sourceId->value));
    }
}
