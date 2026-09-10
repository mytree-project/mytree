<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use RuntimeException;

final class EvidenceStateReferenceMismatch extends RuntimeException
{
    public static function missing(string $kind, string $identity): self
    {
        return new self(sprintf('EvidenceState references missing %s %s.', $kind, $identity));
    }
}
