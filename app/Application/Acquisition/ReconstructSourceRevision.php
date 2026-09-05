<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\SourceId;
use App\Domain\Acquisition\SourceRevisionState;

final readonly class ReconstructSourceRevision
{
    public function __construct(
        private SourceRevisionRepository $revisions,
    ) {}

    public function handle(SourceId $sourceId, int $revisionNumber): SourceRevisionState
    {
        $revision = $this->revisions->find($sourceId, $revisionNumber)
            ?? throw SourceRevisionNotFound::forRevision($sourceId, $revisionNumber);

        return $revision->snapshot->reconstruct();
    }
}
