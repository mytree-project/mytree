<?php

declare(strict_types=1);

namespace App\Application\Search;

use App\Domain\Acquisition\SourceId;

interface SearchProjectionRebuildTargetRepository
{
    /** @return list<SourceId> */
    public function staleOrMissing(): array;
}
