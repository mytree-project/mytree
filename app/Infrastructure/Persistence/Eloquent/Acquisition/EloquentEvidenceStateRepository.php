<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Acquisition;

use App\Application\Acquisition\EvidenceStateRepository;
use App\Domain\Acquisition\EvidenceState;
use App\Domain\Acquisition\EvidenceStateId;
use App\Domain\Acquisition\EvidenceStateSnapshot;
use App\Infrastructure\Persistence\Eloquent\Acquisition\Models\ClaimRevisionRecord;
use App\Infrastructure\Persistence\Eloquent\Acquisition\Models\EvidenceStateRecord;
use App\Infrastructure\Persistence\Eloquent\Acquisition\Models\MentionRevisionRecord;
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
        $manifest = $snapshot->reconstruct();

        return DB::transaction(function () use ($id, $snapshot, $createdAt, $changeNote, $changedBy, $manifest): EvidenceState {
            $sourceIds = [];

            foreach ($manifest->sourceRevisions as $reference) {
                $exists = DB::table('source_revisions')
                    ->where('source_id', $reference->sourceId->value)
                    ->where('revision_number', $reference->revisionNumber)
                    ->exists();

                if (! $exists) {
                    throw new UnexpectedValueException('EvidenceState references a missing SourceRevision.');
                }

                $sourceIds[$reference->sourceId->value] = true;
            }

            foreach ($manifest->mentionRevisionIds as $revisionId) {
                $record = MentionRevisionRecord::query()->find($revisionId->value);

                if ($record === null || ! isset($sourceIds[(string) $record->source_id])) {
                    throw new UnexpectedValueException('EvidenceState MentionRevision must belong to a represented Source.');
                }
            }

            foreach ($manifest->claimRevisionIds as $revisionId) {
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

            foreach ($manifest->sourceRevisions as $reference) {
                DB::table('evidence_state_source_revisions')->insert([
                    'evidence_state_id' => $state->id->value,
                    'source_id' => $reference->sourceId->value,
                    'revision_number' => $reference->revisionNumber,
                ]);
            }

            foreach ($manifest->mentionRevisionIds as $revisionId) {
                DB::table('evidence_state_mention_revisions')->insert([
                    'evidence_state_id' => $state->id->value,
                    'mention_revision_id' => $revisionId->value,
                ]);
            }

            foreach ($manifest->claimRevisionIds as $revisionId) {
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

        $snapshot = EvidenceStateSnapshot::rehydrate(
            schemaVersion: (int) $record->snapshot_schema_version,
            canonicalPayload: (string) $record->canonical_payload,
            payloadHash: (string) $record->payload_hash,
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
        $manifest = $snapshot->reconstruct();

        $sourceReferences = DB::table('evidence_state_source_revisions')
            ->where('evidence_state_id', $id->value)
            ->orderBy('source_id')
            ->get(['source_id', 'revision_number'])
            ->map(static fn (object $row): string => sprintf('%s:%d', $row->source_id, $row->revision_number))
            ->all();
        $manifestSourceReferences = array_map(
            static fn ($reference): string => sprintf('%s:%d', $reference->sourceId->value, $reference->revisionNumber),
            $manifest->sourceRevisions,
        );
        sort($manifestSourceReferences, SORT_STRING);

        $mentionReferences = DB::table('evidence_state_mention_revisions')
            ->where('evidence_state_id', $id->value)
            ->orderBy('mention_revision_id')
            ->pluck('mention_revision_id')
            ->map(static fn (mixed $value): string => (string) $value)
            ->all();
        $manifestMentionReferences = array_map(static fn ($revisionId): string => $revisionId->value, $manifest->mentionRevisionIds);
        sort($manifestMentionReferences, SORT_STRING);

        $claimReferences = DB::table('evidence_state_claim_revisions')
            ->where('evidence_state_id', $id->value)
            ->orderBy('claim_revision_id')
            ->pluck('claim_revision_id')
            ->map(static fn (mixed $value): string => (string) $value)
            ->all();
        $manifestClaimReferences = array_map(static fn ($revisionId): string => $revisionId->value, $manifest->claimRevisionIds);
        sort($manifestClaimReferences, SORT_STRING);

        if ($sourceReferences !== $manifestSourceReferences
            || $mentionReferences !== $manifestMentionReferences
            || $claimReferences !== $manifestClaimReferences) {
            throw new UnexpectedValueException('Stored EvidenceState references do not match its canonical snapshot.');
        }
    }
}
