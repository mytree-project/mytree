<?php

declare(strict_types=1);

namespace App\Application\Search;

use App\Domain\Acquisition\SourceId;

interface SearchDocumentRepository
{
    public function replace(SearchDocument $document): void;

    public function find(SourceId $sourceId): ?SearchDocument;

    public function markStale(SourceId $sourceId): void;
}
