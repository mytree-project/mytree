<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\Claim;
use App\Domain\Acquisition\ClaimRevision;
use App\Domain\Acquisition\ClaimRevisionSnapshot;

final readonly class ClaimRevisionRecorder
{
    public function __construct(
        private MentionRevisionRepository $mentionRevisions,
        private SourceLocatorRepository $locators,
        private ClaimRevisionRepository $claimRevisions,
        private SourceIdentifierGenerator $identifiers,
        private ClaimRevisionClock $clock,
    ) {}

    public function capture(Claim $claim): ClaimRevisionSnapshot
    {
        $subjectRevision = $this->mentionRevisions->latestForMention($claim->sourceId, $claim->subjectMentionId)
            ?? throw ClaimRevisionMentionStateNotFound::forMention($claim->sourceId, $claim->subjectMentionId);

        $objectRevision = $claim->objectMentionId === null
            ? null
            : $this->mentionRevisions->latestForMention($claim->sourceId, $claim->objectMentionId)
                ?? throw ClaimRevisionMentionStateNotFound::forMention($claim->sourceId, $claim->objectMentionId);

        return ClaimRevisionSnapshot::capture(
            claim: $claim,
            subjectMentionRevisionId: $subjectRevision->id,
            objectMentionRevisionId: $objectRevision?->id,
            sourceLocators: $this->locators->forClaim($claim->sourceId, $claim->id),
        );
    }

    public function append(
        ClaimRevisionSnapshot $snapshot,
        ?string $changeNote = null,
        ?string $changedBy = null,
    ): ClaimRevision {
        return $this->claimRevisions->append(
            revisionId: $this->identifiers->claimRevisionId(),
            snapshot: $snapshot,
            createdAt: $this->clock->now(),
            changeNote: $changeNote,
            changedBy: $changedBy,
        );
    }

    public function record(
        Claim $claim,
        ?string $changeNote = null,
        ?string $changedBy = null,
    ): ClaimRevision {
        return $this->append($this->capture($claim), $changeNote, $changedBy);
    }
}
