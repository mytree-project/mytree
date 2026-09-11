<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\SourceId;
use App\Domain\Acquisition\SourceRevision;
use App\Domain\Acquisition\SourceRevisionId;
use App\Domain\Acquisition\SourceRevisionSnapshot;
use DateTimeImmutable;

interface SourceRevisionRepository
{
    public function append(
        SourceRevisionId $revisionId,
        SourceRevisionSnapshot $snapshot,
        DateTimeImmutable $createdAt,
        ?string $changeNote = null,
        ?string $changedBy = null,
    ): SourceRevision;

    public function find(SourceRevisionId $revisionId): ?SourceRevision;

    public function findForSource(SourceId $sourceId, int $revisionNumber): ?SourceRevision;

    public function latestForSource(SourceId $sourceId): ?SourceRevision;

    /** @return list<SourceRevision> */
    public function forSource(SourceId $sourceId): array;
}
