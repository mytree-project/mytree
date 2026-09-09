<?php

declare(strict_types=1);

namespace App\Domain\Acquisition;

use InvalidArgumentException;

final readonly class EvidenceStateSourceRevision
{
    public function __construct(
        public SourceId $sourceId,
        public int $revisionNumber,
    ) {
        if ($revisionNumber < 1) {
            throw new InvalidArgumentException('EvidenceState SourceRevision number must be at least 1.');
        }
    }
}
