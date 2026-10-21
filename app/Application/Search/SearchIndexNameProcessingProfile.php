<?php

declare(strict_types=1);

namespace App\Application\Search;

use InvalidArgumentException;

final readonly class SearchIndexNameProcessingProfile
{
    public function __construct(
        public string $normalizeProfile,
        public string $transliterateProfile,
        public string $foldProfile,
    ) {
        if (
            trim($normalizeProfile) === ''
            || trim($transliterateProfile) === ''
            || trim($foldProfile) === ''
        ) {
            throw new InvalidArgumentException('Search name-processing profile identifiers must not be empty.');
        }
    }

    /** @return array{normalize: string, transliterate: string, fold: string} */
    public function toArray(): array
    {
        return [
            'normalize' => $this->normalizeProfile,
            'transliterate' => $this->transliterateProfile,
            'fold' => $this->foldProfile,
        ];
    }
}
