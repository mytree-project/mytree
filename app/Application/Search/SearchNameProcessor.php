<?php

declare(strict_types=1);

namespace App\Application\Search;

interface SearchNameProcessor
{
    /** @return list<string> */
    public function indexForms(
        SearchNameInput $input,
        SearchIndexNameProcessingProfile $profile,
    ): array;

    public function signature(SearchIndexNameProcessingProfile $profile): string;
}
