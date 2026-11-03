<?php

declare(strict_types=1);

namespace App\Application\Search;

use InvalidArgumentException;

final readonly class SearchResult
{
    public SearchResultCategory $category;

    public string $resultKey;

    public ?string $title;

    public ?SearchResultScore $score;

    public function __construct(
        public SearchResultDetails $details,
    ) {
        $id = trim($details->resultId());
        if ($id === '') {
            throw new InvalidArgumentException('Search result category-local identity must not be blank.');
        }

        $this->category = $details->category();
        $this->resultKey = $this->category->value.':'.$id;
        $this->title = $details->label();
        $this->score = $details->ranking();
    }
}
