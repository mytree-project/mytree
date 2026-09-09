<?php

declare(strict_types=1);

namespace App\Domain\Acquisition;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class ClaimRevision
{
    public function __construct(
        public ClaimRevisionId $id,
        public ClaimId $claimId,
        public SourceId $sourceId,
        public int $revisionNumber,
        public ClaimRevisionSnapshot $snapshot,
        public DateTimeImmutable $createdAt,
        public ?string $changeNote = null,
        public ?string $changedBy = null,
    ) {
        if ($revisionNumber < 1) {
            throw new InvalidArgumentException('ClaimRevision number must be at least 1.');
        }

        if ($changeNote !== null && trim($changeNote) === '') {
            throw new InvalidArgumentException('ClaimRevision change note must not be empty when provided.');
        }

        if ($changedBy !== null && trim($changedBy) === '') {
            throw new InvalidArgumentException('ClaimRevision attribution must not be empty when provided.');
        }

        $state = $snapshot->reconstruct();

        if ($state->claim->id->value !== $claimId->value) {
            throw new InvalidArgumentException('ClaimRevision snapshot must describe the same Claim identity.');
        }

        if ($state->claim->sourceId->value !== $sourceId->value) {
            throw new InvalidArgumentException('ClaimRevision snapshot must belong to the same Source.');
        }
    }

    public function reconstruct(): ClaimRevisionState
    {
        return $this->snapshot->reconstruct();
    }
}
