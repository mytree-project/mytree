<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Search\CurrentEvidenceStateLookup;
use App\Domain\Acquisition\SourceId;
use App\Infrastructure\Search\RebuildSourceSearchDocumentJob;
use Illuminate\Console\Command;
use InvalidArgumentException;

final class RebuildSearchSource extends Command
{
    protected $signature = 'search:rebuild
        {sourceId : Source UUID to rebuild}';

    protected $description = 'Queue a SearchDocument rebuild for one Source';

    public function handle(CurrentEvidenceStateLookup $currentEvidence): int
    {
        try {
            $sourceId = new SourceId(trim((string) $this->argument('sourceId')));
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::INVALID;
        }

        if ($currentEvidence->forSource($sourceId) === null) {
            $this->error(sprintf('Source %s has no accepted EvidenceState to index.', $sourceId->value));

            return self::FAILURE;
        }

        RebuildSourceSearchDocumentJob::dispatch($sourceId->value);

        $this->info(sprintf('Queued SearchDocument rebuild for Source %s.', $sourceId->value));

        return self::SUCCESS;
    }
}
