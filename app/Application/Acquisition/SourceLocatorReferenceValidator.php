<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\SourceLocator;
use InvalidArgumentException;

final readonly class SourceLocatorReferenceValidator
{
    public function __construct(
        private ClaimRepository $claims,
        private SourceAssetRepository $assets,
    ) {}

    public function validate(SourceLocator $locator): void
    {
        if ($this->claims->find($locator->sourceId, $locator->claimId) === null) {
            throw ClaimNotFound::forSourceAndId($locator->sourceId, $locator->claimId);
        }

        if ($locator->sourceAssetId === null) {
            return;
        }

        $asset = $this->assets->find($locator->sourceAssetId)
            ?? throw SourceAssetNotFound::forId($locator->sourceAssetId);

        if ($asset->sourceId === null || $asset->sourceId->value !== $locator->sourceId->value) {
            throw new InvalidArgumentException('Source locator asset must belong to the same Source as the Claim.');
        }
    }
}
