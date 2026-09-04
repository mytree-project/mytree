<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\SourceAsset;
use App\Domain\Acquisition\SourceAssetId;
use App\Domain\Acquisition\SourceId;

interface SourceAssetRepository
{
    public function save(SourceAsset $asset): void;

    public function find(SourceAssetId $id): ?SourceAsset;

    /** @return list<SourceAsset> */
    public function forSource(SourceId $sourceId): array;
}
