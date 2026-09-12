<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\EvidenceStateId;

final readonly class SourceDraftSaveResult
{
    public function __construct(
        public SourceDraft $draft,
        public ?EvidenceStateId $evidenceStateId,
        public bool $changed,
    ) {}
}
