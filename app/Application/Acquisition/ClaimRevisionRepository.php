<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\ClaimId;
use App\Domain\Acquisition\ClaimRevision;
use App\Domain\Acquisition\ClaimRevisionId;
use App\Domain\Acquisition\ClaimRevisionSnapshot;
use App\Domain\Acquisition\SourceId;
use DateTimeImmutable;

interface ClaimRevisionRepository
{
    public function append(
        ClaimRevisionId $revisionId,
        ClaimRevisionSnapshot $snapshot,
        DateTimeImmutable $createdAt,
        ?string $changeNote = null,
        ?string $changedBy = null,
    ): ClaimRevision;

    public function find(ClaimRevisionId $revisionId): ?ClaimRevision;

    public function findForClaim(
        SourceId $sourceId,
        ClaimId $claimId,
        int $revisionNumber,
    ): ?ClaimRevision;

    public function latestForClaim(SourceId $sourceId, ClaimId $claimId): ?ClaimRevision;

    /** @return list<ClaimRevision> */
    public function forClaim(SourceId $sourceId, ClaimId $claimId): array;

    /** @return list<ClaimRevision> */
    public function forSource(SourceId $sourceId): array;
}
