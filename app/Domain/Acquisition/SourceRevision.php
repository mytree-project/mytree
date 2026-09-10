<?php

declare(strict_types=1);

namespace App\Domain\Acquisition;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class SourceRevision
{
    public function __construct(
        public SourceRevisionId $id,
        public SourceId $sourceId,
        public int $revisionNumber,
        public SourceRevisionSnapshot $snapshot,
        public DateTimeImmutable $createdAt,
        public ?string $changeNote = null,
        public ?string $changedBy = null,
    ) {
        if ($revisionNumber < 1) {
            throw new InvalidArgumentException('Source revision number must be at least 1.');
        }

        if ($changeNote !== null && trim($changeNote) === '') {
            throw new InvalidArgumentException('Source revision change note must not be empty when provided.');
        }

        if ($changedBy !== null && trim($changedBy) === '') {
            throw new InvalidArgumentException('Source revision attribution must not be empty when provided.');
        }

        $state = $snapshot->reconstruct();

        if ($state->source->id->value !== $sourceId->value) {
            throw new InvalidArgumentException('Source revision snapshot must belong to the revision Source.');
        }
    }
}
