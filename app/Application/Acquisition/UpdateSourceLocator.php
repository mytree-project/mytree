<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\ClaimId;
use App\Domain\Acquisition\SourceAssetId;
use App\Domain\Acquisition\SourceId;
use App\Domain\Acquisition\SourceLocator;
use App\Domain\Acquisition\SourceLocatorId;
use App\Domain\Acquisition\SourceLocatorValue;

final readonly class UpdateSourceLocator
{
    public function __construct(
        private SourceLocatorRepository $locators,
        private SourceLocatorReferenceValidator $references,
    ) {}

    public function handle(
        SourceId $sourceId,
        ClaimId $claimId,
        SourceLocatorId $locatorId,
        SourceLocatorValue $value,
        ?SourceAssetId $sourceAssetId = null,
    ): SourceLocator {
        $current = $this->locators->find($sourceId, $claimId, $locatorId)
            ?? throw SourceLocatorNotFound::forClaimAndId($sourceId, $claimId, $locatorId);

        $locator = new SourceLocator(
            id: $current->id,
            sourceId: $current->sourceId,
            claimId: $current->claimId,
            value: $value,
            sourceAssetId: $sourceAssetId,
            schemaVersion: $current->schemaVersion,
        );

        $this->references->validate($locator);
        $this->locators->update($locator);

        return $locator;
    }
}
