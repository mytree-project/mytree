<?php

declare(strict_types=1);

namespace App\Application\Search;

enum SearchMatchType: string
{
    case DirectSourceMetadata = 'direct_source_metadata';
    case DirectClaimValue = 'direct_claim_value';
    case SourceRepresentation = 'source_representation';
}
