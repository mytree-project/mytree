<?php

declare(strict_types=1);

namespace App\Application\Search;

use InvalidArgumentException;

final readonly class SearchQueryTerm
{
    /** @var list<string> */
    public array $comparisonForms;

    /** @param list<string> $comparisonForms */
    public function __construct(
        public string $value,
        array $comparisonForms,
    ) {
        if (trim($value) === '') {
            throw new InvalidArgumentException('Search query term must not be empty.');
        }

        $this->comparisonForms = array_values(array_unique(array_filter(
            [$value, ...$comparisonForms],
            static fn (string $form): bool => trim($form) !== '',
        )));
    }
}
