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

    public function get(EvidenceStateId $id): EvidenceStateView
    {
        $evidenceState = $this->evidenceStates->find($id)
            ?? throw EvidenceStateNotFound::forId($id);
        $manifest = $evidenceState->manifest();
        $sourceRevisions = [];

        foreach ($manifest->sourceRevisions as $reference) {
            $sourceRevisions[] = $this->sourceRevisions->find($reference->sourceId, $reference->revisionNumber)
                ?? throw new UnexpectedValueException('EvidenceState references a missing SourceRevision.');
        }

        $mentionRevisions = [];
        foreach ($manifest->mentionRevisionIds as $revisionId) {
            $mentionRevisions[] = $this->mentionRevisions->find($revisionId)
                ?? throw new UnexpectedValueException('EvidenceState references a missing MentionRevision.');
        }

        $claimRevisions = [];
        foreach ($manifest->claimRevisionIds as $revisionId) {
            $claimRevisions[] = $this->claimRevisions->find($revisionId)
                ?? throw new UnexpectedValueException('EvidenceState references a missing ClaimRevision.');
        }

        return new EvidenceStateView(
            evidenceState: $evidenceState,
            sourceRevisions: $sourceRevisions,
            mentionRevisions: $mentionRevisions,
            claimRevisions: $claimRevisions,
        );
    }
}
