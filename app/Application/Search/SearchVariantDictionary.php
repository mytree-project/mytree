<?php

declare(strict_types=1);

namespace App\Application\Search;

interface SearchVariantDictionary
{
    /** @return list<SearchVariantCandidate> */
    public function variants(string $value, SearchNameType $type): array;

    /** Native mytree.name-variants.v1 JSON. */
    public function export(): string;

    /** Validates the entire dataset before atomically replacing the working copy. */
    public function import(string $json, ?string $changedBy = null): void;
}
