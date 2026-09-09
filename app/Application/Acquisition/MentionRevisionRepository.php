<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\MentionId;
use App\Domain\Acquisition\MentionRevision;
use App\Domain\Acquisition\MentionRevisionId;
use App\Domain\Acquisition\MentionRevisionSnapshot;
use App\Domain\Acquisition\SourceId;
use DateTimeImmutable;

interface MentionRevisionRepository
{
    public function append(
        MentionRevisionId $revisionId,
        MentionRevisionSnapshot $snapshot,
        DateTimeImmutable $createdAt,
        ?string $changeNote = null,
        ?string $changedBy = null,
    ): MentionRevision;

    public function find(MentionRevisionId $revisionId): ?MentionRevision;

    public function findForMention(
        SourceId $sourceId,
        MentionId $mentionId,
        int $revisionNumber,
    ): ?MentionRevision;

    public function latestForMention(SourceId $sourceId, MentionId $mentionId): ?MentionRevision;

    /** @return list<MentionRevision> */
    public function forMention(SourceId $sourceId, MentionId $mentionId): array;

    /** @return list<MentionRevision> */
    public function forSource(SourceId $sourceId): array;
}
