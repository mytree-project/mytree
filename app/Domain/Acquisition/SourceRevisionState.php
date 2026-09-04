<?php

declare(strict_types=1);

namespace App\Domain\Acquisition;

use InvalidArgumentException;

final readonly class SourceRevisionState
{
    /** @var list<SourceAsset> */
    public array $assets;

    /** @param list<SourceAsset> $assets */
    public function __construct(
        public Source $source,
        array $assets,
    ) {
        $seenAssetIds = [];

        foreach ($assets as $asset) {
            if ($asset->sourceId?->value !== $source->id->value) {
                throw new InvalidArgumentException('Revision assets must belong to the reconstructed Source.');
            }

            if (isset($seenAssetIds[$asset->id->value])) {
                throw new InvalidArgumentException('Revision asset ids must be unique within a Source state.');
            }

            $seenAssetIds[$asset->id->value] = true;
        }

        $this->assets = $assets;
    }
}
