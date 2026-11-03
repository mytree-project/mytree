<?php

declare(strict_types=1);

namespace App\Application\Search;

final readonly class SearchDocumentDate
{
    public function __construct(
        public string $field,
        public SearchDateSpan $span,
        public ?string $claimRevisionId = null,
    ) {}
}
