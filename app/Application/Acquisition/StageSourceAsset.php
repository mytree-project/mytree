<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\SourceAsset;
use App\Domain\Acquisition\SourceId;

final readonly class StageSourceAsset
{
    public function __construct(
        private SourceAssetRepository $assets,
        private SourceAssetStorage $storage,
        private SourceIdentifierGenerator $identifiers,
        private AcquisitionTransaction $transaction,
    ) {}

    public function handle(SourceId $futureSourceId, StoreSourceAssetInput $input): SourceAsset
    {
        $assetId = $this->identifiers->sourceAssetId();
        $storage = $this->storage->referenceFor($futureSourceId, $assetId);
        $asset = new SourceAsset(
            id: $assetId,
            sourceId: null,
            storage: $storage,
            originalFilename: $input->originalFilename,
            mimeType: $input->mimeType,
            byteSize: strlen($input->contents),
            sha256: hash('sha256', $input->contents),
            retrievedAt: $input->retrievedAt,
            metadata: $input->metadata,
            provenance: $input->provenance,
        );

        $this->storage->write($storage, $input->contents);

        return $this->transaction->run(function () use ($asset): SourceAsset {
            $this->assets->save($asset);

            return $asset;
        });
    }
}
