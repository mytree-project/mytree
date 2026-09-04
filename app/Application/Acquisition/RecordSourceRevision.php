<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\SourceId;
use App\Domain\Acquisition\SourceRevision;
use App\Domain\Acquisition\SourceRevisionSnapshot;

final readonly class RecordSourceRevision
{
    public function __construct(
        private SourceRepository $sources,
        private SourceAssetRepository $assets,
        private SourceRevisionRepository $revisions,
        private SourceRevisionClock $clock,
    ) {}

    public function handle(
        SourceId $sourceId,
        ?string $changeNote = null,
        ?string $changedBy = null,
    ): SourceRevision {
        $source = $this->sources->find($sourceId) ?? throw SourceNotFound::forId($sourceId);
        $snapshot = SourceRevisionSnapshot::capture(
            $source,
            $this->assets->forSource($sourceId),
        );

        return $this->revisions->append(
            sourceId: $sourceId,
            snapshot: $snapshot,
            createdAt: $this->clock->now(),
            changeNote: $changeNote,
            changedBy: $changedBy,
        );
    }
}
