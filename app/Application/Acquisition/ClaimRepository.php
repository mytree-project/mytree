<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\Claim;
use App\Domain\Acquisition\ClaimId;
use App\Domain\Acquisition\SourceId;

interface ClaimRepository
{
    public function add(Claim $claim): void;

    public function update(Claim $claim): void;

    public function find(SourceId $sourceId, ClaimId $claimId): ?Claim;

    /** @return list<Claim> */
    public function forSource(SourceId $sourceId): array;

    public function remove(SourceId $sourceId, ClaimId $claimId): void;
}
