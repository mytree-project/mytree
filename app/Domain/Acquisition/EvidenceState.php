<?php

declare(strict_types=1);

namespace App\Domain\Acquisition;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class EvidenceState
{
    public function __construct(
        public EvidenceStateId $id,
        public EvidenceStateSnapshot $snapshot,
        public DateTimeImmutable $createdAt,
        public ?string $changeNote = null,
        public ?string $changedBy = null,
    ) {
        if ($changeNote !== null && trim($changeNote) === '') {
            throw new InvalidArgumentException('EvidenceState change note must not be empty when provided.');
        }

        if ($changedBy !== null && trim($changedBy) === '') {
            throw new InvalidArgumentException('EvidenceState attribution must not be empty when provided.');
        }

        $snapshot->reconstruct();
    }

    public function manifest(): EvidenceStateManifest
    {
        return $this->snapshot->reconstruct();
    }
}
