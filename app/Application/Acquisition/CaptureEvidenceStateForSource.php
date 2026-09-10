<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\Claim;
use App\Domain\Acquisition\ClaimRevisionId;
use App\Domain\Acquisition\EvidenceState;
use App\Domain\Acquisition\EvidenceStateSnapshot;
use App\Domain\Acquisition\Mention;
use App\Domain\Acquisition\MentionRevisionId;
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
        $sourceRevision = $this->sourceRevisions->latestForSource($sourceId)
            ?? throw EvidenceStateCaptureIncomplete::missingSourceRevision($sourceId);

        $mentionRevisionIds = array_map(
            function (Mention $mention) use ($sourceId): MentionRevisionId {
                $revision = $this->mentionRevisions->latestForMention($sourceId, $mention->id)
                    ?? throw EvidenceStateCaptureIncomplete::missingMentionRevision($mention->id);

                return $revision->id;
            },
            $this->mentions->forSource($sourceId),
        );

        $claimRevisionIds = array_map(
            function (Claim $claim) use ($sourceId): ClaimRevisionId {
                $revision = $this->claimRevisions->latestForClaim($sourceId, $claim->id)
                    ?? throw EvidenceStateCaptureIncomplete::missingClaimRevision($claim->id);

                return $revision->id;
            },
            $this->claims->forSource($sourceId),
        );

        return $this->evidenceStates->append(
            id: $this->identifiers->evidenceStateId(),
            snapshot: EvidenceStateSnapshot::capture(
                sourceRevisionIds: [$sourceRevision->id],
                mentionRevisionIds: $mentionRevisionIds,
                claimRevisionIds: $claimRevisionIds,
            ),
            createdAt: $this->clock->now(),
            changeNote: $changeNote,
            changedBy: $changedBy,
        );
    }
}
