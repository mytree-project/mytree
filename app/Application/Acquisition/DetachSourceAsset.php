<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\SourceAsset;
use App\Domain\Acquisition\SourceAssetId;

final readonly class DetachSourceAsset
{
    public function __construct(
        private SourceAssetRepository $assets,
        private RecordSourceRevision $revisions,
        private AcquisitionTransaction $transaction,
    ) {}

    public function handle(
        SourceAssetId $id,
        ?string $changeNote = null,
        ?string $changedBy = null,
    ): SourceAsset {
        $asset = $this->assets->find($id) ?? throw SourceAssetNotFound::forId($id);

        if ($asset->sourceId === null) {
            return $asset;
        }

        $sourceId = $asset->sourceId;
        $detached = $asset->detached();

        return $this->transaction->run(function () use ($sourceId, $detached, $changeNote, $changedBy): SourceAsset {
            $this->assets->save($detached);
            $this->revisions->handle($sourceId, $changeNote, $changedBy);

            return $detached;
        });
    }
}
