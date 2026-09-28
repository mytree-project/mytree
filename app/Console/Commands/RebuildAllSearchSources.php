<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Search\SearchProjectionRebuildTargetRepository;
use App\Infrastructure\Search\RebuildSourceSearchDocumentJob;
use Illuminate\Console\Command;

final class RebuildAllSearchSources extends Command
{
    protected $signature = 'search:rebuild-all';

    protected $description = 'Queue SearchDocument rebuilds for every Source with accepted evidence';

    public function handle(SearchProjectionRebuildTargetRepository $targets): int
    {
        $sourceIds = $targets->allIndexable();

        foreach ($sourceIds as $sourceId) {
            RebuildSourceSearchDocumentJob::dispatch($sourceId->value);
        }

        $count = count($sourceIds);
        $this->info($count === 0
            ? 'No Sources with accepted EvidenceState are available for SearchDocument rebuild.'
            : sprintf('Queued %d SearchDocument rebuild%s.', $count, $count === 1 ? '' : 's'));

        return self::SUCCESS;
    }
}
