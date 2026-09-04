<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\SourceAssetId;
use App\Domain\Acquisition\SourceAssetStorageReference;
use App\Domain\Acquisition\SourceId;

interface SourceAssetStorage
{
    public function referenceFor(SourceId $sourceId, SourceAssetId $assetId): SourceAssetStorageReference;

    public function write(SourceAssetStorageReference $reference, string $contents): void;
}
