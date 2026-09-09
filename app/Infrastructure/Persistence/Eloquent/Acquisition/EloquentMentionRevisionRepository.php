<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Acquisition;

use App\Application\Acquisition\MentionNotFound;
use App\Application\Acquisition\MentionRevisionRepository;
use App\Domain\Acquisition\MentionId;
use App\Domain\Acquisition\MentionRevision;
use App\Domain\Acquisition\MentionRevisionId;
use App\Domain\Acquisition\MentionRevisionSnapshot;
use App\Domain\Acquisition\SourceId;
use App\Infrastructure\Persistence\Eloquent\Acquisition\Models\MentionRecord;
use App\Infrastructure\Persistence\Eloquent\Acquisition\Models\MentionRevisionRecord;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use UnexpectedValueException;

final class EloquentMentionRevisionRepository implements MentionRevisionRepository
{
    public function append(
        MentionRevisionId $revisionId,
        MentionRevisionSnapshot $snapshot,
        DateTimeImmutable $createdAt,
        ?string $changeNote = null,
        ?string $changedBy = null,
    ): MentionRevision {
        $mention = $snapshot->reconstruct();

        return DB::transaction(function () use (
            $revisionId,
            $snapshot,
            $createdAt,
            $changeNote,
            $changedBy,
            $mention,
        ): MentionRevision {
            $current = MentionRecord::query()
                ->whereKey($mention->id->value)
                ->where('source_id', $mention->sourceId->value)
                ->lockForUpdate()
                ->first();

            if ($current === null) {
                throw MentionNotFound::forSourceAndId($mention->sourceId, $mention->id);
            }

            $lastRevision = MentionRevisionRecord::query()
                ->where('source_id', $mention->sourceId->value)
                ->where('mention_id', $mention->id->value)
                ->max('revision_number');
            $revisionNumber = is_numeric($lastRevision) ? (int) $lastRevision + 1 : 1;

            $revision = new MentionRevision(
                id: $revisionId,
                mentionId: $mention->id,
                sourceId: $mention->sourceId,
                revisionNumber: $revisionNumber,
                snapshot: $snapshot,
                createdAt: $createdAt,
                changeNote: $changeNote,
                changedBy: $changedBy,
            );

            MentionRevisionRecord::query()->create([
                'id' => $revision->id->value,
                'source_id' => $revision->sourceId->value,
                'mention_id' => $revision->mentionId->value,
                'revision_number' => $revision->revisionNumber,
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

    public function find(MentionRevisionId $revisionId): ?MentionRevision
    {
        $record = MentionRevisionRecord::query()->find($revisionId->value);

        return $record === null ? null : $this->toDomain($record);
    }

    public function findForMention(
        SourceId $sourceId,
        MentionId $mentionId,
        int $revisionNumber,
    ): ?MentionRevision {
        $record = MentionRevisionRecord::query()
            ->where('source_id', $sourceId->value)
            ->where('mention_id', $mentionId->value)
            ->where('revision_number', $revisionNumber)
            ->first();

        return $record === null ? null : $this->toDomain($record);
    }

    public function latestForMention(SourceId $sourceId, MentionId $mentionId): ?MentionRevision
    {
        $record = MentionRevisionRecord::query()
            ->where('source_id', $sourceId->value)
            ->where('mention_id', $mentionId->value)
            ->orderByDesc('revision_number')
            ->first();

        return $record === null ? null : $this->toDomain($record);
    }

    public function forMention(SourceId $sourceId, MentionId $mentionId): array
    {
        $revisions = [];
        $records = MentionRevisionRecord::query()
            ->where('source_id', $sourceId->value)
            ->where('mention_id', $mentionId->value)
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
        $records = MentionRevisionRecord::query()
            ->where('source_id', $sourceId->value)
            ->orderBy('mention_id')
            ->orderBy('revision_number')
            ->get();

        foreach ($records as $record) {
            $revisions[] = $this->toDomain($record);
        }

        return $revisions;
    }

    private function toDomain(MentionRevisionRecord $record): MentionRevision
    {
        $recordedAt = $record->getAttribute('recorded_at');

        if (! $recordedAt instanceof DateTimeInterface) {
            throw new UnexpectedValueException('Stored MentionRevision timestamp must be a date-time value.');
        }

        return new MentionRevision(
            id: new MentionRevisionId((string) $record->id),
            mentionId: new MentionId((string) $record->mention_id),
            sourceId: new SourceId((string) $record->source_id),
            revisionNumber: (int) $record->revision_number,
            snapshot: MentionRevisionSnapshot::rehydrate(
                schemaVersion: (int) $record->snapshot_schema_version,
                canonicalPayload: (string) $record->canonical_payload,
                payloadHash: (string) $record->payload_hash,
            ),
            createdAt: DateTimeImmutable::createFromInterface($recordedAt),
            changeNote: $record->change_note === null ? null : (string) $record->change_note,
            changedBy: $record->changed_by === null ? null : (string) $record->changed_by,
        );
    }
}
