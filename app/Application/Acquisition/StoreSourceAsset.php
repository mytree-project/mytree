<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\SourceAsset;
use App\Domain\Acquisition\SourceId;

final readonly class StoreSourceAsset
{
    public function __construct(
        private SourceRepository $sources,
        private SourceAssetRepository $assets,
        private SourceAssetStorage $storage,
        private SourceIdentifierGenerator $identifiers,
        private RecordSourceRevision $revisions,
    ) {}

    public function handle(
        SourceId $sourceId,
        StoreSourceAssetInput $input,
        ?string $changeNote = null,
        ?string $changedBy = null,
    ): SourceAsset {
        if ($this->sources->find($sourceId) === null) {
            throw SourceNotFound::forId($sourceId);
        }

        $assetId = $this->identifiers->sourceAssetId();
        $storage = $this->storage->referenceFor($sourceId, $assetId);
        $asset = new SourceAsset(
            id: $assetId,
            sourceId: $sourceId,
            storage: $storage,
            originalFilename: $input->originalFilename,
            mimeType: $input->mimeType,
            byteSize: strlen($input->contents),
            sha256: hash('sha256', $input->contents),
            retrievedAt: $input->retrievedAt,
            metadata: $input->metadata,
            provenance: $input->provenance,
        );

        $this->storage->write($asset->storage, $input->contents);
        $this->assets->save($asset);
        $this->revisions->handle($sourceId, $changeNote, $changedBy);

        return $asset;
    }
}
