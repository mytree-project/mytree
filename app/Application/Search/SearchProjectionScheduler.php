<?php

declare(strict_types=1);

namespace App\Application\Search;

use App\Domain\Acquisition\SourceId;

interface SearchProjectionScheduler
{
    public function sourceChanged(SourceId $sourceId): void;
}
