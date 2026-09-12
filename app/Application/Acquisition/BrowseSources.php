<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\SourceId;

final readonly class BrowseSources
{
    public function __construct(private SourceBrowseRepository $sources) {}

    /** @return list<SourceBrowseItem> */
    public function search(?string $query = null): array
    {
        return $this->sources->search($query);
    }

    public function find(SourceId $sourceId): ?SourceBrowseItem
    {
        return $this->sources->find($sourceId);
    }
}
