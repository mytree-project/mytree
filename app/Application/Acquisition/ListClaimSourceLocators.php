<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\ClaimId;
use App\Domain\Acquisition\SourceId;
use App\Domain\Acquisition\SourceLocator;

final readonly class ListClaimSourceLocators
{
    public function __construct(private SourceLocatorRepository $locators) {}

    /** @return list<SourceLocator> */
    public function handle(SourceId $sourceId, ClaimId $claimId): array
    {
        return $this->locators->forClaim($sourceId, $claimId);
    }
}
