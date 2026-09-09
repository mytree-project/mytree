<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\ClaimRevision;
use App\Domain\Acquisition\EvidenceState;
use App\Domain\Acquisition\MentionRevision;
use App\Domain\Acquisition\SourceRevision;

final readonly class EvidenceStateView
{
    /**
     * @param  list<SourceRevision>  $sourceRevisions
     * @param  list<MentionRevision>  $mentionRevisions
     * @param  list<ClaimRevision>  $claimRevisions
     */
    public function __construct(
        public EvidenceState $evidenceState,
        public array $sourceRevisions,
        public array $mentionRevisions,
        public array $claimRevisions,
    ) {}
}
