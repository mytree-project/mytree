<?php

declare(strict_types=1);

namespace App\Application\Search;

final readonly class SearchQueryVariant
{
    /** @var list<string> */
    public array $forms;

    /** @param list<string> $forms */
    public function __construct(
        public SearchVariantCandidate $candidate,
        array $forms,
    ) {
        $this->forms = array_values(array_unique([$candidate->value, ...$forms]));
    }

    public function field(): string
    {
        return match ($this->candidate->type) {
            SearchNameType::GivenName => 'person.given_name',
            SearchNameType::Surname => 'person.surname',
            SearchNameType::PlaceName => 'place.name',
            SearchNameType::Other => 'other',
        };
    }
}
