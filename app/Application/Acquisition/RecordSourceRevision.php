<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\Source;
use App\Domain\Acquisition\SourceId;
use App\Domain\Acquisition\SourceRevision;
use App\Domain\Acquisition\SourceRevisionSnapshot;

final readonly class RecordSourceRevision
{
    public function __construct(
        private SourceRepository $sources,
        private SourceAssetRepository $assets,
        private SourceRevisionRepository $revisions,
        private SourceIdentifierGenerator $identifiers,
        private SourceRevisionClock $clock,
    ) {}

    public function capture(Source $source): SourceRevisionSnapshot
    {
        return SourceRevisionSnapshot::capture(
            $source,
            $this->assets->forSource($source->id),
        );
    }

    public function append(
        SourceRevisionSnapshot $snapshot,
        ?string $changeNote = null,
        ?string $changedBy = null,
    ): SourceRevision {
        return $this->revisions->append(
            revisionId: $this->identifiers->sourceRevisionId(),
            snapshot: $snapshot,
            createdAt: $this->clock->now(),
            changeNote: $changeNote,
            changedBy: $changedBy,
        );
    }

    public function record(
        Source $source,
        ?string $changeNote = null,
        ?string $changedBy = null,
    ): SourceRevision {
        return $this->append($this->capture($source), $changeNote, $changedBy);
    }

    public function handle(
        SourceId $sourceId,
        ?string $changeNote = null,
        ?string $changedBy = null,
    ): SourceRevision {
        $source = $this->sources->find($sourceId) ?? throw SourceNotFound::forId($sourceId);

        return $this->record($source, $changeNote, $changedBy);
    }
}
