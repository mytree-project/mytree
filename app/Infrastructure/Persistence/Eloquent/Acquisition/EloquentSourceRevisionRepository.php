<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Acquisition;

use App\Application\Acquisition\SourceRevisionRepository;
use App\Domain\Acquisition\SourceId;
use App\Domain\Acquisition\SourceRevision;
use App\Domain\Acquisition\SourceRevisionSnapshot;
use App\Infrastructure\Persistence\Eloquent\Acquisition\Models\SourceRecord;
use App\Infrastructure\Persistence\Eloquent\Acquisition\Models\SourceRevisionRecord;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use UnexpectedValueException;

final class EloquentSourceRevisionRepository implements SourceRevisionRepository
{
    public function append(
        SourceId $sourceId,
        SourceRevisionSnapshot $snapshot,
        DateTimeImmutable $createdAt,
        ?string $changeNote = null,
        ?string $changedBy = null,
    ): SourceRevision {
        return DB::transaction(function () use ($sourceId, $snapshot, $createdAt, $changeNote, $changedBy): SourceRevision {
            SourceRecord::query()
                ->whereKey($sourceId->value)
                ->lockForUpdate()
                ->firstOrFail();

            $lastRevision = SourceRevisionRecord::query()
                ->where('source_id', $sourceId->value)
                ->max('revision_number');
            $revisionNumber = is_numeric($lastRevision) ? (int) $lastRevision + 1 : 1;

            $record = SourceRevisionRecord::query()->create([
                'source_id' => $sourceId->value,
                'revision_number' => $revisionNumber,
                'snapshot_schema_version' => $snapshot->schemaVersion,
                'canonical_payload' => $snapshot->canonicalPayload,
                'payload_hash' => $snapshot->payloadHash,
                'recorded_at' => $createdAt,
                'change_note' => $changeNote,
                'changed_by' => $changedBy,
            ]);

            return $this->toDomain($record);
        });
    }

    public function find(SourceId $sourceId, int $revisionNumber): ?SourceRevision
    {
        $record = SourceRevisionRecord::query()
            ->where('source_id', $sourceId->value)
            ->where('revision_number', $revisionNumber)
            ->first();

        return $record === null ? null : $this->toDomain($record);
    }

    public function forSource(SourceId $sourceId): array
    {
        $revisions = [];
        $records = SourceRevisionRecord::query()
            ->where('source_id', $sourceId->value)
            ->orderBy('revision_number')
            ->get();

        foreach ($records as $record) {
            $revisions[] = $this->toDomain($record);
        }

        return $revisions;
    }

    private function toDomain(SourceRevisionRecord $record): SourceRevision
    {
        $recordedAt = $record->getAttribute('recorded_at');

        if (! $recordedAt instanceof DateTimeInterface) {
            throw new UnexpectedValueException('Stored SourceRevision timestamp must be a date-time value.');
        }

        return new SourceRevision(
            sourceId: new SourceId((string) $record->source_id),
            revisionNumber: (int) $record->revision_number,
            snapshot: SourceRevisionSnapshot::rehydrate(
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
