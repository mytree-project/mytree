<?php

declare(strict_types=1);

namespace App\Application\Search;

interface SearchResultDetails
{
    public function category(): SearchResultCategory;

    /** Identity within the result category, not an interchangeable Source ID. */
    public function resultId(): string;

    public function label(): ?string;

    public function ranking(): ?SearchResultScore;
}
