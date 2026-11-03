<?php

declare(strict_types=1);

namespace App\Application\Search;

enum SearchSortMode: string
{
    case Relevance = 'relevance';
    case SourceDate = 'source_date';
    case SourceAdded = 'source_added';
}
