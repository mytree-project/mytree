<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\Mention;
use App\Domain\Acquisition\MentionId;
use App\Domain\Acquisition\MentionKind;
use App\Domain\Acquisition\MentionRawData;
use App\Domain\Acquisition\MentionRevisionSnapshot;
use App\Domain\Acquisition\SourceId;

final readonly class UpdateMention
{
    public function __construct(
        private MentionRepository $mentions,
        private MentionRevisionRepository $revisions,
        private SourceIdentifierGenerator $identifiers,
        private MentionRevisionClock $revisionClock,
        private AcquisitionTransaction $transaction,
    ) {}

    public function handle(
        SourceId $sourceId,
        MentionId $mentionId,
        MentionKind $kind,
        ?string $role = null,
        ?string $displayLabel = null,
        ?MentionRawData $rawData = null,
        ?string $changeNote = null,
        ?string $changedBy = null,
    ): Mention {
        return $this->transaction->run(function () use (
            $sourceId,
            $mentionId,
            $kind,
            $role,
            $displayLabel,
            $rawData,
            $changeNote,
            $changedBy,
        ): Mention {
            $current = $this->mentions->find($sourceId, $mentionId)
                ?? throw MentionNotFound::forSourceAndId($sourceId, $mentionId);

            $mention = new Mention(
                id: $current->id,
                sourceId: $current->sourceId,
                kind: $kind,
                localKey: $current->localKey,
                role: $role,
                displayLabel: $displayLabel,
                rawData: $rawData ?? $current->rawData,
                schemaVersion: $current->schemaVersion,
            );
            $currentSnapshot = MentionRevisionSnapshot::capture($current);
            $updatedSnapshot = MentionRevisionSnapshot::capture($mention);

            if (hash_equals($currentSnapshot->payloadHash, $updatedSnapshot->payloadHash)) {
                return $current;
            }

            $this->mentions->update($mention);
            $this->revisions->append(
                revisionId: $this->identifiers->mentionRevisionId(),
                snapshot: $updatedSnapshot,
                createdAt: $this->revisionClock->now(),
                changeNote: $changeNote,
                changedBy: $changedBy,
            );

            return $mention;
        });
    }
}
