<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\EvidenceState;
use App\Domain\Acquisition\EvidenceStateSnapshot;
use App\Domain\Acquisition\EvidenceStateSourceRevision;
use App\Domain\Acquisition\SourceId;

final readonly class CaptureEvidenceStateForSource
{
    public function __construct(
        private SourceRevisionRepository $sourceRevisions,
        private MentionRepository $mentions,
        private MentionRevisionRepository $mentionRevisions,
        private ClaimRepository $claims,
        private ClaimRevisionRepository $claimRevisions,
        private EvidenceStateRepository $evidenceStates,
        private SourceIdentifierGenerator $identifiers,
        private EvidenceStateClock $clock,
    ) {}

    public function capture(
        SourceId $sourceId,
        ?string $changeNote = null,
        ?string $changedBy = null,
    ): EvidenceState {
        $sourceHistory = $this->sourceRevisions->forSource($sourceId);

        if ($sourceHistory === []) {
            throw EvidenceStateCaptureIncomplete::missingSourceRevision($sourceId);
        }

        $sourceRevision = $sourceHistory[count($sourceHistory) - 1];
        $mentionRevisionIds = [];

        foreach ($this->mentions->forSource($sourceId) as $mention) {
            $revision = $this->mentionRevisions->latestForMention($sourceId, $mention->id);

            if ($revision === null) {
                throw EvidenceStateCaptureIncomplete::missingMentionRevision($mention->id->value);
            }

            $mentionRevisionIds[] = $revision->id;
        }

        $claimRevisionIds = [];

        foreach ($this->claims->forSource($sourceId) as $claim) {
            $revision = $this->claimRevisions->latestForClaim($sourceId, $claim->id);

            if ($revision === null) {
                throw EvidenceStateCaptureIncomplete::missingClaimRevision($claim->id->value);
            }

            $claimRevisionIds[] = $revision->id;
        }

        $snapshot = EvidenceStateSnapshot::capture(
            sourceRevisions: [new EvidenceStateSourceRevision($sourceId, $sourceRevision->revisionNumber)],
            mentionRevisionIds: $mentionRevisionIds,
            claimRevisionIds: $claimRevisionIds,
        );

        return $this->evidenceStates->append(
            id: $this->identifiers->evidenceStateId(),
            snapshot: $snapshot,
            createdAt: $this->clock->now(),
            changeNote: $changeNote,
            changedBy: $changedBy,
        );
    }
}
