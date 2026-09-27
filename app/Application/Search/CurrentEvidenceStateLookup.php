<?php

declare(strict_types=1);

namespace App\Application\Search;

use App\Domain\Acquisition\EvidenceStateId;
use App\Domain\Acquisition\SourceId;

interface CurrentEvidenceStateLookup
{
    public function forSource(SourceId $sourceId): ?EvidenceStateId;
}
