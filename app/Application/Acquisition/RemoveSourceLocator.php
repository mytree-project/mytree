<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\ClaimId;
use App\Domain\Acquisition\SourceId;
use App\Domain\Acquisition\SourceLocator;
use App\Domain\Acquisition\SourceLocatorId;

final readonly class RemoveSourceLocator
{
    public function __construct(private SourceLocatorRepository $locators) {}

    public function handle(SourceId $sourceId, ClaimId $claimId, SourceLocatorId $locatorId): SourceLocator
    {
        $locator = $this->locators->find($sourceId, $claimId, $locatorId)
            ?? throw SourceLocatorNotFound::forClaimAndId($sourceId, $claimId, $locatorId);

        $this->locators->remove($sourceId, $claimId, $locatorId);

        return $locator;
    }
}
