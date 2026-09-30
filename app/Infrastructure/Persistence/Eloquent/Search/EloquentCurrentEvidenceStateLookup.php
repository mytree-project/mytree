<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Search;

use App\Application\Search\CurrentEvidenceStateLookup;
use App\Domain\Acquisition\EvidenceStateId;
use App\Domain\Acquisition\SourceId;
use Illuminate\Support\Facades\DB;

final class EloquentCurrentEvidenceStateLookup implements CurrentEvidenceStateLookup
{
    public function forSource(SourceId $sourceId): ?EvidenceStateId
    {
        $id = DB::table('evidence_state_source_revisions')
            ->join(
                'evidence_states',
                'evidence_states.id',
                '=',
                'evidence_state_source_revisions.evidence_state_id',
            )
            ->where('evidence_state_source_revisions.source_id', $sourceId->value)
            ->orderByDesc('evidence_states.recorded_at')
            ->orderByDesc('evidence_states.id')
            ->value('evidence_states.id');

        return is_string($id) && $id !== '' ? new EvidenceStateId($id) : null;
    }
}
