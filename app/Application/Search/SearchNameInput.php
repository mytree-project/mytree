<?php

declare(strict_types=1);

namespace App\Application\Search;

use InvalidArgumentException;

final readonly class SearchNameInput
{
    public function __construct(
        public string $value,
        public SearchNameType $type,
        public ?string $language = null,
        public ?string $script = null,
    ) {
        if (trim($value) === '') {
            throw new InvalidArgumentException('Search name-processing input must not be empty.');
        }
    }
}
