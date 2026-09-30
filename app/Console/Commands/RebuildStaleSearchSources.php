<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Search\SearchProjectionRebuildTargetRepository;
use App\Infrastructure\Search\RebuildSourceSearchDocumentJob;
use Illuminate\Console\Command;

final class RebuildStaleSearchSources extends Command
{
    protected $signature = 'search:rebuild-stale';

    protected $description = 'Queue SearchDocument rebuilds for stale, missing, or outdated projections';

    public function handle(SearchProjectionRebuildTargetRepository $targets): int
    {
        $sourceIds = $targets->requiringRebuild();

        foreach ($sourceIds as $sourceId) {
            RebuildSourceSearchDocumentJob::dispatch($sourceId->value);
        }

        $count = count($sourceIds);
        $this->info($count === 0
            ? 'No SearchDocuments require rebuild.'
            : sprintf('Queued %d SearchDocument rebuild%s.', $count, $count === 1 ? '' : 's'));

        return self::SUCCESS;
    }
}
