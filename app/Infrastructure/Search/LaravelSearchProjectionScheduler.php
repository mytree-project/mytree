<?php

declare(strict_types=1);

namespace App\Infrastructure\Search;

use App\Application\Search\SearchDocumentRepository;
use App\Application\Search\SearchProjectionScheduler;
use App\Domain\Acquisition\SourceId;
use Illuminate\Support\Facades\DB;

final readonly class LaravelSearchProjectionScheduler implements SearchProjectionScheduler
{
    public function __construct(
        private SearchDocumentRepository $documents,
    ) {}

    public function sourceChanged(SourceId $sourceId): void
    {
        $this->documents->markStale($sourceId);

        DB::afterCommit(static function () use ($sourceId): void {
            RebuildSourceSearchDocumentJob::dispatch($sourceId->value);
        });
    }
}
