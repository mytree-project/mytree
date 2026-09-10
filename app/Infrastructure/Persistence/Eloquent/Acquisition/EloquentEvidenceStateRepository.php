<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Acquisition;

use App\Application\Acquisition\EvidenceStateRepository;
use App\Domain\Acquisition\ClaimRevisionId;
use App\Domain\Acquisition\EvidenceState;
use App\Domain\Acquisition\EvidenceStateId;
use App\Domain\Acquisition\EvidenceStateSnapshot;
use App\Domain\Acquisition\MentionRevisionId;
use App\Domain\Acquisition\SourceRevisionId;
use App\Infrastructure\Persistence\Eloquent\Acquisition\Models\ClaimRevisionRecord;
use App\Infrastructure\Persistence\Eloquent\Acquisition\Models\EvidenceStateRecord;
use App\Infrastructure\Persistence\Eloquent\Acquisition\Models\MentionRevisionRecord;
use App\Infrastructure\Persistence\Eloquent\Acquisition\Models\SourceRevisionRecord;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use UnexpectedValueException;

final class EloquentEvidenceStateRepository implements EvidenceStateRepository
{
    public function append(
        EvidenceStateId $id,
        EvidenceStateSnapshot $snapshot,
        DateTimeImmutable $createdAt,
        ?string $changeNote = null,
        ?string $changedBy = null,
    ): EvidenceState {
        return DB::transaction(function () use ($id, $snapshot, $createdAt, $changeNote, $changedBy): EvidenceState {
            $sourceRecords = $this->sourceRecordsFor($snapshot);
            $sourceIds = [];

            foreach ($sourceRecords as $record) {
                $sourceId = (string) $record->source_id;

                if (isset($sourceIds[$sourceId])) {
                    throw new UnexpectedValueException('EvidenceState may contain only one SourceRevision per Source.');
                }

                $sourceIds[$sourceId] = true;
            }

            $this->assertLegacySourceReferencesMatch($snapshot, $sourceRecords);

            foreach ($snapshot->mentionRevisionIds as $revisionId) {
                $record = MentionRevisionRecord::query()->find($revisionId->value);

                if ($record === null || ! isset($sourceIds[(string) $record->source_id])) {
                    throw new UnexpectedValueException('EvidenceState MentionRevision must belong to a represented Source.');
                }
            }

            foreach ($snapshot->claimRevisionIds as $revisionId) {
                $record = ClaimRevisionRecord::query()->find($revisionId->value);

                if ($record === null || ! isset($sourceIds[(string) $record->source_id])) {
                    throw new UnexpectedValueException('EvidenceState ClaimRevision must belong to a represented Source.');
                }

                $this->assertClaimMentionRevisionExists((string) $record->subject_mention_revision_id, (string) $record->source_id);

                if ($record->object_mention_revision_id !== null) {
                    $this->assertClaimMentionRevisionExists((string) $record->object_mention_revision_id, (string) $record->source_id);
                }
            }

            $state = new EvidenceState(
                id: $id,
                snapshot: $snapshot,
                createdAt: $createdAt,
                changeNote: $changeNote,
                changedBy: $changedBy,
            );

            EvidenceStateRecord::query()->create([
                'id' => $state->id->value,
                'snapshot_schema_version' => $state->snapshot->schemaVersion,
                'canonical_payload' => $state->snapshot->canonicalPayload,
                'payload_hash' => $state->snapshot->payloadHash,
                'recorded_at' => $state->createdAt,
                'change_note' => $state->changeNote,
                'changed_by' => $state->changedBy,
            ]);

            foreach ($sourceRecords as $record) {
                DB::table('evidence_state_source_revisions')->insert([
                    'evidence_state_id' => $state->id->value,
                    'source_id' => (string) $record->source_id,
                    'revision_number' => (int) $record->revision_number,
                    'source_revision_id' => (string) $record->revision_id,
                ]);
            }

            foreach ($snapshot->mentionRevisionIds as $revisionId) {
                DB::table('evidence_state_mention_revisions')->insert([
                    'evidence_state_id' => $state->id->value,
                    'mention_revision_id' => $revisionId->value,
                ]);
            }

            foreach ($snapshot->claimRevisionIds as $revisionId) {
                DB::table('evidence_state_claim_revisions')->insert([
                    'evidence_state_id' => $state->id->value,
                    'claim_revision_id' => $revisionId->value,
                ]);
            }

            return $state;
        });
    }

    public function find(EvidenceStateId $id): ?EvidenceState
    {
        $record = EvidenceStateRecord::query()->find($id->value);

        if ($record === null) {
            return null;
        }

        $recordedAt = $record->getAttribute('recorded_at');

        if (! $recordedAt instanceof DateTimeInterface) {
            throw new UnexpectedValueException('Stored EvidenceState timestamp must be a date-time value.');
        }

        $schemaVersion = (int) $record->snapshot_schema_version;
        $canonicalPayload = (string) $record->canonical_payload;
        $legacySourceRevisionIds = $schemaVersion === EvidenceStateSnapshot::LEGACY_SCHEMA_VERSION
            ? $this->resolveLegacySourceRevisionIds($canonicalPayload)
            : [];

        $snapshot = EvidenceStateSnapshot::rehydrate(
            schemaVersion: $schemaVersion,
            canonicalPayload: $canonicalPayload,
            payloadHash: (string) $record->payload_hash,
            legacySourceRevisionIds: $legacySourceRevisionIds,
        );
        $this->assertPersistedReferencesMatchSnapshot($id, $snapshot);

        return new EvidenceState(
            id: new EvidenceStateId((string) $record->id),
            snapshot: $snapshot,
            createdAt: DateTimeImmutable::createFromInterface($recordedAt),
            changeNote: $record->change_note === null ? null : (string) $record->change_note,
            changedBy: $record->changed_by === null ? null : (string) $record->changed_by,
        );
    }

    /** @return list<SourceRevisionRecord> */
    private function sourceRecordsFor(EvidenceStateSnapshot $snapshot): array
    {
        $records = [];

        foreach ($snapshot->sourceRevisionIds as $revisionId) {
            $record = SourceRevisionRecord::query()
                ->where('revision_id', $revisionId->value)
                ->first();

            if ($record === null) {
                throw new UnexpectedValueException('EvidenceState references a missing SourceRevision.');
            }

            $records[] = $record;
        }

        return $records;
    }

    /**
     * @param  list<SourceRevisionRecord>  $sourceRecords
     */
    private function assertLegacySourceReferencesMatch(EvidenceStateSnapshot $snapshot, array $sourceRecords): void
    {
        if ($snapshot->schemaVersion !== EvidenceStateSnapshot::LEGACY_SCHEMA_VERSION) {
            return;
        }

        $expected = [];
        foreach ($snapshot->legacySourceReferences() as $reference) {
            $expected[] = sprintf(
                '%s:%d',
                $reference['sourceId']->value,
                $reference['revisionNumber'],
            );
        }
        sort($expected, SORT_STRING);

        $actual = array_map(
            static fn (SourceRevisionRecord $record): string => sprintf(
                '%s:%d',
                (string) $record->source_id,
                (int) $record->revision_number,
            ),
            $sourceRecords,
        );
        sort($actual, SORT_STRING);

        if ($expected !== $actual) {
            throw new UnexpectedValueException('Legacy EvidenceState SourceRevision identities do not match its retained snapshot.');
        }
    }

    /** @return list<SourceRevisionId> */
    private function resolveLegacySourceRevisionIds(string $canonicalPayload): array
    {
        $ids = [];

        foreach (EvidenceStateSnapshot::legacySourceReferencesFromCanonicalPayload($canonicalPayload) as $reference) {
            $record = SourceRevisionRecord::query()
                ->where('source_id', $reference['sourceId']->value)
                ->where('revision_number', $reference['revisionNumber'])
                ->first();

            if ($record === null || ! is_string($record->revision_id) || $record->revision_id === '') {
                throw new UnexpectedValueException('Legacy EvidenceState references a SourceRevision without an immutable identity.');
            }

            $ids[] = new SourceRevisionId($record->revision_id);
        }

        return $ids;
    }

    private function assertClaimMentionRevisionExists(string $revisionId, string $sourceId): void
    {
        $exists = MentionRevisionRecord::query()
            ->whereKey($revisionId)
            ->where('source_id', $sourceId)
            ->exists();

        if (! $exists) {
            throw new UnexpectedValueException('EvidenceState ClaimRevision references an invalid MentionRevision dependency.');
        }
    }

    private function assertPersistedReferencesMatchSnapshot(EvidenceStateId $id, EvidenceStateSnapshot $snapshot): void
    {
        $sourceRevisionIds = DB::table('evidence_state_source_revisions')
            ->where('evidence_state_id', $id->value)
            ->orderBy('source_revision_id')
            ->pluck('source_revision_id')
            ->map(static fn (mixed $value): string => (string) $value)
            ->all();
        $snapshotSourceRevisionIds = array_map(
            static fn (SourceRevisionId $revisionId): string => $revisionId->value,
            $snapshot->sourceRevisionIds,
        );
        sort($snapshotSourceRevisionIds, SORT_STRING);

        $mentionReferences = DB::table('evidence_state_mention_revisions')
            ->where('evidence_state_id', $id->value)
            ->orderBy('mention_revision_id')
            ->pluck('mention_revision_id')
            ->map(static fn (mixed $value): string => (string) $value)
            ->all();
        $snapshotMentionReferences = array_map(
            static fn (MentionRevisionId $revisionId): string => $revisionId->value,
            $snapshot->mentionRevisionIds,
        );
        sort($snapshotMentionReferences, SORT_STRING);

        $claimReferences = DB::table('evidence_state_claim_revisions')
            ->where('evidence_state_id', $id->value)
            ->orderBy('claim_revision_id')
            ->pluck('claim_revision_id')
            ->map(static fn (mixed $value): string => (string) $value)
            ->all();
        $snapshotClaimReferences = array_map(
            static fn (ClaimRevisionId $revisionId): string => $revisionId->value,
            $snapshot->claimRevisionIds,
        );
        sort($snapshotClaimReferences, SORT_STRING);

        if ($sourceRevisionIds !== $snapshotSourceRevisionIds
            || $mentionReferences !== $snapshotMentionReferences
            || $claimReferences !== $snapshotClaimReferences) {
            throw new UnexpectedValueException('Stored EvidenceState references do not match its canonical snapshot.');
        }

        if ($snapshot->schemaVersion === EvidenceStateSnapshot::LEGACY_SCHEMA_VERSION) {
            $sourceRecords = $this->sourceRecordsFor($snapshot);
            $this->assertLegacySourceReferencesMatch($snapshot, $sourceRecords);
        }
    }
}
