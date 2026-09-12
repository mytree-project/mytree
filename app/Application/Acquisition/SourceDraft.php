<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

final readonly class SourceDraft
{
    public function __construct(
        public SourceDraftState $current,
        public ?SourceDraftBaseState $baseState,
        public SourceDraftChanges $changes,
        public bool $isNew,
        public ?string $changeNote = null,
        public ?string $changedBy = null,
    ) {}

    public function withChanges(
        SourceDraftChanges $changes,
        ?string $changeNote = null,
        ?string $changedBy = null,
    ): self {
        return new self(
            current: $this->current,
            baseState: $this->baseState,
            changes: $changes,
            isNew: $this->isNew,
            changeNote: $changeNote,
            changedBy: $changedBy,
        );
    }
}
