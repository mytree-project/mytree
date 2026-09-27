<?php

declare(strict_types=1);

namespace App\Application\Search;

enum SearchValueOrigin: string
{
    case SourceMetadata = 'source_metadata';
    case ClaimValue = 'claim_value';
    case SourceRepresentation = 'source_representation';
}
