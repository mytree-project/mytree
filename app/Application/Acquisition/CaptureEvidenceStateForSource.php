<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Application\Search\SearchProjectionScheduler;
use App\Domain\Acquisition\Claim;
use App\Domain\Acquisition\ClaimOriginKind;
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
        private SearchProjectionScheduler $searchProjectionScheduler,
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

        $directRevisionIds = [];
        $derivedRevisions = [];
        foreach ($this->claims->forSource($sourceId) as $claim) {
            $revision = $this->claimRevisions->latestForClaim($sourceId, $claim->id)
                ?? throw EvidenceStateCaptureIncomplete::missingClaimRevision($claim->id);

            if ($claim->origin->kind === ClaimOriginKind::Derived) {
                $derivedRevisions[] = $revision;

                continue;
            }

            $directRevisionIds[$revision->id->value] = $revision->id;
        }

        $activeMentionRevisionIds = array_fill_keys(
            array_map(
                static fn (MentionRevisionId $id): string => $id->value,
                $mentionRevisionIds,
            ),
            true,
        );

        // A materialized Derived Claim is accepted evidence only as long as its
        // *exact* direct inputs and relevant Mention revisions are current.
        // Reconciliation/recomputation of stale rows is handled by #238.
        $claimRevisionIds = array_values($directRevisionIds);
        foreach ($derivedRevisions as $revision) {
            $derivation = $revision->reconstruct()->claim->derivation;
            if ($derivation === null) {
                continue;
            }

            foreach ($derivation->inputClaimRevisionIds as $inputId) {
                if (! isset($directRevisionIds[$inputId->value])) {
                    continue 2;
                }
            }

            foreach ($derivation->inputMentionRevisionIds as $inputId) {
                if (! isset($activeMentionRevisionIds[$inputId->value])) {
                    continue 2;
                }
            }

            $claimRevisionIds[] = $revision->id;
        }

        $evidenceState = $this->evidenceStates->append(
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

        $this->searchProjectionScheduler->sourceChanged($sourceId);

        return $evidenceState;
    }
}
