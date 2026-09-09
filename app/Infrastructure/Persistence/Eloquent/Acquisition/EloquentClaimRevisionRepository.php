<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Acquisition;

use App\Application\Acquisition\ClaimNotFound;
use App\Application\Acquisition\ClaimRevisionRepository;
use App\Domain\Acquisition\ClaimId;
use App\Domain\Acquisition\ClaimRevision;
use App\Domain\Acquisition\ClaimRevisionId;
use App\Domain\Acquisition\ClaimRevisionSnapshot;
use App\Domain\Acquisition\SourceId;
use App\Infrastructure\Persistence\Eloquent\Acquisition\Models\ClaimRecord;
use App\Infrastructure\Persistence\Eloquent\Acquisition\Models\ClaimRevisionRecord;
use App\Infrastructure\Persistence\Eloquent\Acquisition\Models\MentionRevisionRecord;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use UnexpectedValueException;

final class EloquentClaimRevisionRepository implements ClaimRevisionRepository
{
    public function append(
        ClaimRevisionId $revisionId,
        ClaimRevisionSnapshot $snapshot,
        DateTimeImmutable $createdAt,
        ?string $changeNote = null,
        ?string $changedBy = null,
    ): ClaimRevision {
        $state = $snapshot->reconstruct();
        $claim = $state->claim;

        return DB::transaction(function () use (
            $revisionId,
            $snapshot,
            $createdAt,
            $changeNote,
            $changedBy,
            $state,
            $claim,
        ): ClaimRevision {
            $current = ClaimRecord::query()
                ->whereKey($claim->id->value)
                ->where('source_id', $claim->sourceId->value)
                ->lockForUpdate()
                ->first();

            if ($current === null) {
                throw ClaimNotFound::forSourceAndId($claim->sourceId, $claim->id);
            }

            $this->assertMentionRevisionReference(
                $state->subjectMentionRevisionId->value,
                $claim->sourceId->value,
                $claim->subjectMentionId->value,
                'subject',
            );

            if ($claim->objectMentionId !== null && $state->objectMentionRevisionId !== null) {
                $this->assertMentionRevisionReference(
                    $state->objectMentionRevisionId->value,
                    $claim->sourceId->value,
                    $claim->objectMentionId->value,
                    'object',
                );
            }

            $lastRevision = ClaimRevisionRecord::query()
                ->where('source_id', $claim->sourceId->value)
                ->where('claim_id', $claim->id->value)
                ->max('revision_number');
            $revisionNumber = is_numeric($lastRevision) ? (int) $lastRevision + 1 : 1;

            $revision = new ClaimRevision(
                id: $revisionId,
                claimId: $claim->id,
                sourceId: $claim->sourceId,
                revisionNumber: $revisionNumber,
                snapshot: $snapshot,
                createdAt: $createdAt,
                changeNote: $changeNote,
                changedBy: $changedBy,
            );

            ClaimRevisionRecord::query()->create([
                'id' => $revision->id->value,
                'source_id' => $revision->sourceId->value,
                'claim_id' => $revision->claimId->value,
                'revision_number' => $revision->revisionNumber,
                'subject_mention_revision_id' => $state->subjectMentionRevisionId->value,
                'object_mention_revision_id' => $state->objectMentionRevisionId?->value,
                'snapshot_schema_version' => $revision->snapshot->schemaVersion,
                'canonical_payload' => $revision->snapshot->canonicalPayload,
                'payload_hash' => $revision->snapshot->payloadHash,
                'recorded_at' => $revision->createdAt,
                'change_note' => $revision->changeNote,
                'changed_by' => $revision->changedBy,
            ]);

            return $revision;
        });
    }

    public function find(ClaimRevisionId $revisionId): ?ClaimRevision
    {
        $record = ClaimRevisionRecord::query()->find($revisionId->value);

        return $record === null ? null : $this->toDomain($record);
    }

    public function findForClaim(
        SourceId $sourceId,
        ClaimId $claimId,
        int $revisionNumber,
    ): ?ClaimRevision {
        $record = ClaimRevisionRecord::query()
            ->where('source_id', $sourceId->value)
            ->where('claim_id', $claimId->value)
            ->where('revision_number', $revisionNumber)
            ->first();

        return $record === null ? null : $this->toDomain($record);
    }

    public function latestForClaim(SourceId $sourceId, ClaimId $claimId): ?ClaimRevision
    {
        $record = ClaimRevisionRecord::query()
            ->where('source_id', $sourceId->value)
            ->where('claim_id', $claimId->value)
            ->orderByDesc('revision_number')
            ->first();

        return $record === null ? null : $this->toDomain($record);
    }

    public function forClaim(SourceId $sourceId, ClaimId $claimId): array
    {
        $revisions = [];
        $records = ClaimRevisionRecord::query()
            ->where('source_id', $sourceId->value)
            ->where('claim_id', $claimId->value)
            ->orderBy('revision_number')
            ->get();

        foreach ($records as $record) {
            $revisions[] = $this->toDomain($record);
        }

        return $revisions;
    }

    public function forSource(SourceId $sourceId): array
    {
        $revisions = [];
        $records = ClaimRevisionRecord::query()
            ->where('source_id', $sourceId->value)
            ->orderBy('claim_id')
            ->orderBy('revision_number')
            ->get();

        foreach ($records as $record) {
            $revisions[] = $this->toDomain($record);
        }

        return $revisions;
    }

    private function toDomain(ClaimRevisionRecord $record): ClaimRevision
    {
        $recordedAt = $record->getAttribute('recorded_at');

        if (! $recordedAt instanceof DateTimeInterface) {
            throw new UnexpectedValueException('Stored ClaimRevision timestamp must be a date-time value.');
        }

        $snapshot = ClaimRevisionSnapshot::rehydrate(
            schemaVersion: (int) $record->snapshot_schema_version,
            canonicalPayload: (string) $record->canonical_payload,
            payloadHash: (string) $record->payload_hash,
        );
        $state = $snapshot->reconstruct();
        $storedSubjectRevisionId = (string) $record->subject_mention_revision_id;
        $storedObjectRevisionId = $record->object_mention_revision_id === null
            ? null
            : (string) $record->object_mention_revision_id;

        if ($state->subjectMentionRevisionId->value !== $storedSubjectRevisionId) {
            throw new UnexpectedValueException('Stored ClaimRevision subject MentionRevision does not match its snapshot.');
        }

        if ($state->objectMentionRevisionId?->value !== $storedObjectRevisionId) {
            throw new UnexpectedValueException('Stored ClaimRevision object MentionRevision does not match its snapshot.');
        }

        return new ClaimRevision(
            id: new ClaimRevisionId((string) $record->id),
            claimId: new ClaimId((string) $record->claim_id),
            sourceId: new SourceId((string) $record->source_id),
            revisionNumber: (int) $record->revision_number,
            snapshot: $snapshot,
            createdAt: DateTimeImmutable::createFromInterface($recordedAt),
            changeNote: $record->change_note === null ? null : (string) $record->change_note,
            changedBy: $record->changed_by === null ? null : (string) $record->changed_by,
        );
    }

    private function assertMentionRevisionReference(
        string $revisionId,
        string $sourceId,
        string $mentionId,
        string $role,
    ): void {
        $exists = MentionRevisionRecord::query()
            ->whereKey($revisionId)
            ->where('source_id', $sourceId)
            ->where('mention_id', $mentionId)
            ->exists();

        if (! $exists) {
            throw new UnexpectedValueException(sprintf(
                'ClaimRevision %s MentionRevision reference is inconsistent with Source/Mention ownership.',
                $role,
            ));
        }
    }
}
