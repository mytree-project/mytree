<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\SourceAssetId;
use App\Domain\Acquisition\SourceId;

final readonly class ReadSourceAsset
{
    public function __construct(
        private SourceAssetRepository $assets,
        private SourceAssetStorage $storage,
    ) {}

    public function handle(SourceId $sourceId, SourceAssetId $assetId): SourceAssetPayload
    {
        $asset = $this->assets->find($assetId);
        if ($asset === null || $asset->sourceId->value !== $sourceId->value) {
            throw SourceAssetNotFound::forId($assetId);
        }

        return new SourceAssetPayload(
            contents: $this->storage->read($asset->storage),
            filename: $asset->originalFilename,
            mimeType: $asset->mimeType,
        );
    }
}
