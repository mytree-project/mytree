<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\Claim;
use App\Domain\Acquisition\SourceId;

final readonly class ListSourceClaims
{
    public function __construct(private ClaimRepository $claims) {}

    /** @return list<Claim> */
    public function handle(SourceId $sourceId): array
    {
        return $this->claims->forSource($sourceId);
    }
}
