<?php

declare(strict_types=1);

namespace App\Application\Search;

use InvalidArgumentException;

final readonly class SearchDocumentEntry
{
    /** @var list<string> */
    public array $indexForms;

    /** @param list<string> $indexForms */
    public function __construct(
        public string $field,
        public string $value,
        public SearchValueOrigin $origin,
        public ?string $mentionId = null,
        public ?string $claimId = null,
        public ?string $claimRevisionId = null,
        public ?string $language = null,
        public ?string $script = null,
        public ?string $representationRelation = null,
        array $indexForms = [],
    ) {
        if (trim($field) === '' || trim($value) === '') {
            throw new InvalidArgumentException('SearchDocument entry field and value must not be empty.');
        }

        $forms = $indexForms === [] ? [$value] : $indexForms;
        $forms = array_values(array_unique(array_filter(
            array_map(static fn (string $form): string => trim($form), $forms),
            static fn (string $form): bool => $form !== '',
        )));

        if ($forms === []) {
            throw new InvalidArgumentException('SearchDocument entry must contain at least one index form.');
        }

        $this->indexForms = $forms;
    }
}
