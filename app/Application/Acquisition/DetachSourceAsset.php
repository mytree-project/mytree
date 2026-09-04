<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\SourceAsset;
use App\Domain\Acquisition\SourceAssetId;

final readonly class DetachSourceAsset
{
    public function __construct(
        private SourceAssetRepository $assets,
    ) {}

    public function handle(SourceAssetId $id): SourceAsset
    {
        $asset = $this->assets->find($id) ?? throw SourceAssetNotFound::forId($id);
        $detached = $asset->detached();
        $this->assets->save($detached);

        return $detached;
    }
}
