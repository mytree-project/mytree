<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

enum SupportedAcquisitionFieldMappingKind: string
{
    case DirectClaim = 'direct_claim';
    case ReifiedContext = 'reified_context';
}
