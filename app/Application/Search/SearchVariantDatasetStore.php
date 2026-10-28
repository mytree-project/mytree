<?php

declare(strict_types=1);

namespace App\Application\Search;

interface SearchVariantDatasetStore
{
    public function read(string $datasetId): ?string;

    public function replace(string $datasetId, string $canonicalJson, ?string $changedBy): void;
}
