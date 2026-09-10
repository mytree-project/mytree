<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\EvidenceStateId;
use UnexpectedValueException;

final readonly class GetEvidenceState
{
    public function __construct(
        private EvidenceStateRepository $evidenceStates,
        private SourceRevisionRepository $sourceRevisions,
        private MentionRevisionRepository $mentionRevisions,
        private ClaimRevisionRepository $claimRevisions,
    ) {}

    public function get(EvidenceStateId $id): ResolvedEvidenceState
    {
        $evidenceState = $this->evidenceStates->find($id)
            ?? throw EvidenceStateNotFound::forId($id);
        $snapshot = $evidenceState->snapshot;
        $sourceRevisions = [];

        foreach ($snapshot->sourceRevisionIds as $revisionId) {
            $sourceRevisions[] = $this->sourceRevisions->find($revisionId)
                ?? throw new UnexpectedValueException('EvidenceState references a missing SourceRevision.');
        }

        $mentionRevisions = [];
        foreach ($snapshot->mentionRevisionIds as $revisionId) {
            $mentionRevisions[] = $this->mentionRevisions->find($revisionId)
                ?? throw new UnexpectedValueException('EvidenceState references a missing MentionRevision.');
        }

        $claimRevisions = [];
        foreach ($snapshot->claimRevisionIds as $revisionId) {
            $claimRevisions[] = $this->claimRevisions->find($revisionId)
                ?? throw new UnexpectedValueException('EvidenceState references a missing ClaimRevision.');
        }

        return new ResolvedEvidenceState(
            evidenceState: $evidenceState,
            sourceRevisions: $sourceRevisions,
            mentionRevisions: $mentionRevisions,
            claimRevisions: $claimRevisions,
        );
    }
}
