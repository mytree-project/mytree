<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\Mention;
use App\Domain\Acquisition\MentionKind;
use App\Domain\Acquisition\MentionRawData;
use App\Domain\Acquisition\MentionRevisionSnapshot;
use App\Domain\Acquisition\SourceId;

final readonly class CreateMention
{
    public function __construct(
        private SourceRepository $sources,
        private MentionRepository $mentions,
        private MentionRevisionRepository $revisions,
        private SourceIdentifierGenerator $identifiers,
        private MentionRevisionClock $revisionClock,
        private AcquisitionTransaction $transaction,
    ) {}

    public function handle(
        SourceId $sourceId,
        MentionKind $kind,
        string $localKey,
        ?string $role = null,
        ?string $displayLabel = null,
        ?MentionRawData $rawData = null,
        ?string $changeNote = null,
        ?string $changedBy = null,
    ): Mention {
        return $this->transaction->run(function () use (
            $sourceId,
            $kind,
            $localKey,
            $role,
            $displayLabel,
            $rawData,
            $changeNote,
            $changedBy,
        ): Mention {
            if ($this->sources->find($sourceId) === null) {
                throw SourceNotFound::forId($sourceId);
            }

            $mention = new Mention(
                id: $this->identifiers->mentionId(),
                sourceId: $sourceId,
                kind: $kind,
                localKey: $localKey,
                role: $role,
                displayLabel: $displayLabel,
                rawData: $rawData,
            );

            $this->mentions->add($mention);
            $this->revisions->append(
                revisionId: $this->identifiers->mentionRevisionId(),
                snapshot: MentionRevisionSnapshot::capture($mention),
                createdAt: $this->revisionClock->now(),
                changeNote: $changeNote,
                changedBy: $changedBy,
            );

            return $mention;
        });
    }
}
