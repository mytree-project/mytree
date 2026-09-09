<?php

declare(strict_types=1);

namespace App\Domain\Acquisition;

enum ClaimOriginKind: string
{
    case ManualDirectSource = 'manual_direct_source';
    case ProviderObservation = 'provider_observation';
}
