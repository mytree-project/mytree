<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\EvidenceState;
use App\Domain\Acquisition\SourceId;

final readonly class CaptureEvidenceState
{
    public function __construct(private CaptureEvidenceStateForSource $forSource) {}

    public function handle(
        SourceId $sourceId,
        ?string $changeNote = null,
        ?string $changedBy = null,
    ): EvidenceState {
        return $this->forSource->capture($sourceId, $changeNote, $changedBy);
    }
}
