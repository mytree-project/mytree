<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\EvidenceStateId;
use RuntimeException;

final class EvidenceStateNotFound extends RuntimeException
{
    public static function forId(EvidenceStateId $id): self
    {
        return new self(sprintf('EvidenceState %s was not found.', $id->value));
    }
}
