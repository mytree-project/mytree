<?php

declare(strict_types=1);

namespace App\Domain\Acquisition;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class MentionRevision
{
    public function __construct(
        public MentionRevisionId $id,
        public MentionId $mentionId,
        public SourceId $sourceId,
        public int $revisionNumber,
        public MentionRevisionSnapshot $snapshot,
        public DateTimeImmutable $createdAt,
        public ?string $changeNote = null,
        public ?string $changedBy = null,
    ) {
        if ($revisionNumber < 1) {
            throw new InvalidArgumentException('MentionRevision number must be at least 1.');
        }

        if ($changeNote !== null && trim($changeNote) === '') {
            throw new InvalidArgumentException('MentionRevision change note must not be empty when provided.');
        }

        if ($changedBy !== null && trim($changedBy) === '') {
            throw new InvalidArgumentException('MentionRevision attribution must not be empty when provided.');
        }

        $mention = $snapshot->reconstruct();

        if ($mention->id->value !== $mentionId->value) {
            throw new InvalidArgumentException('MentionRevision snapshot must describe the same Mention identity.');
        }

        if ($mention->sourceId->value !== $sourceId->value) {
            throw new InvalidArgumentException('MentionRevision snapshot must belong to the same Source.');
        }
    }

    public function reconstruct(): Mention
    {
        return $this->snapshot->reconstruct();
    }
}
