<?php

declare(strict_types=1);

namespace App\Application\Search;

final readonly class SearchVariantCandidate
{
    public function __construct(
        public string $value,
        public SearchNameType $type,
        public string $groupId,
        public string $datasetId,
        public string $datasetVersion,
        public string $datasetSignature,
    ) {}
}
