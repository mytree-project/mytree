<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\SourceId;

interface SourceBrowseRepository
{
    /** @return list<SourceBrowseItem> */
    public function search(?string $query = null): array;

    public function find(SourceId $sourceId): ?SourceBrowseItem;
}
