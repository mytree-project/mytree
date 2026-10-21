<?php

declare(strict_types=1);

namespace App\Application\Search;

enum SearchNameType: string
{
    case GivenName = 'given_name';
    case Surname = 'surname';
    case PlaceName = 'place_name';
    case Other = 'other';
}
