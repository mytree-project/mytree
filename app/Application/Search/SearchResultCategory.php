<?php

declare(strict_types=1);

namespace App\Application\Search;

enum SearchResultCategory: string
{
    case Source = 'source';
    // Reserved result categories; M5 local Search does not populate them.
    case ExternalIndexObservation = 'external_index_observation';
    case Interpretation = 'interpretation';
}
