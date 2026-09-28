<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Search;

use App\Application\Search\SearchIndexProfile;
use App\Application\Search\SearchProjectionRebuildTargetRepository;
use App\Domain\Acquisition\SourceId;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final readonly class EloquentSearchProjectionRebuildTargetRepository implements SearchProjectionRebuildTargetRepository
{
    public function __construct(
        private SearchIndexProfile $profile,
    ) {}

    public function requiringRebuild(): array
    {
        $currentSignature = $this->profile->signature();

        return DB::table('evidence_state_source_revisions as evidence')
            ->leftJoin('search_documents as document', 'document.source_id', '=', 'evidence.source_id')
            ->where(static function (Builder $query) use ($currentSignature): void {
                $query
                    ->whereNull('document.source_id')
                    ->orWhere('document.stale', true)
                    ->orWhere('document.index_signature', '!=', $currentSignature);
            })
            ->distinct()
            ->orderBy('evidence.source_id')
            ->pluck('evidence.source_id')
            ->map(static fn (mixed $sourceId): SourceId => new SourceId((string) $sourceId))
            ->values()
            ->all();
    }
}
