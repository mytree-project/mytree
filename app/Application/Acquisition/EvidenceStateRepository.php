<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\EvidenceState;
use App\Domain\Acquisition\EvidenceStateId;
use App\Domain\Acquisition\EvidenceStateSnapshot;
use DateTimeImmutable;

interface EvidenceStateRepository
{
    public function append(
        EvidenceStateId $id,
        EvidenceStateSnapshot $snapshot,
        DateTimeImmutable $createdAt,
        ?string $changeNote = null,
        ?string $changedBy = null,
    ): EvidenceState;

    public function find(EvidenceStateId $id): ?EvidenceState;
}
