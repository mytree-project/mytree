<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\ClaimId;
use App\Domain\Acquisition\SourceId;
use App\Domain\Acquisition\SourceLocator;
use App\Domain\Acquisition\SourceLocatorId;

interface SourceLocatorRepository
{
    public function add(SourceLocator $locator): void;

    public function update(SourceLocator $locator): void;

    public function find(SourceId $sourceId, ClaimId $claimId, SourceLocatorId $locatorId): ?SourceLocator;

    /** @return list<SourceLocator> */
    public function forClaim(SourceId $sourceId, ClaimId $claimId): array;

    public function remove(SourceId $sourceId, ClaimId $claimId, SourceLocatorId $locatorId): void;
}
