<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\EvidenceState;
use App\Domain\Acquisition\EvidenceStateId;

final readonly class RecreateEvidenceState
{
    public function __construct(
        private EvidenceStateRepository $evidenceStates,
        private SourceIdentifierGenerator $identifiers,
        private EvidenceStateClock $clock,
    ) {}

    public function recreate(
        EvidenceStateId $sourceStateId,
        ?string $changeNote = null,
        ?string $changedBy = null,
    ): EvidenceState {
        $sourceState = $this->evidenceStates->find($sourceStateId)
            ?? throw EvidenceStateNotFound::forId($sourceStateId);

        return $this->evidenceStates->append(
            id: $this->identifiers->evidenceStateId(),
            snapshot: $sourceState->snapshot,
            createdAt: $this->clock->now(),
            changeNote: $changeNote,
            changedBy: $changedBy,
        );
    }
}
