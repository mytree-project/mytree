<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Search;

use App\Application\Search\SearchProjectionRebuildTargetRepository;
use App\Domain\Acquisition\SourceId;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class EloquentSearchProjectionRebuildTargetRepository implements SearchProjectionRebuildTargetRepository
{
    public function staleOrMissing(): array
    {
        return DB::table('evidence_state_source_revisions as evidence')
            ->leftJoin('search_documents as document', 'document.source_id', '=', 'evidence.source_id')
            ->where(static function (Builder $query): void {
                $query
                    ->whereNull('document.source_id')
                    ->orWhere('document.stale', true);
            })
            ->distinct()
            ->orderBy('evidence.source_id')
            ->pluck('evidence.source_id')
            ->map(static fn (mixed $sourceId): SourceId => new SourceId((string) $sourceId))
            ->values()
            ->all();
    }
}
