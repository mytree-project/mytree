<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Search;

use App\Application\Search\DirectSearchCandidate;
use App\Application\Search\DirectSearchCandidateBatch;
use App\Application\Search\DirectSearchCandidateRepository;
use App\Application\Search\SearchValueOrigin;
use App\Domain\Acquisition\SourceId;
use Illuminate\Support\Facades\DB;

final class EloquentDirectSearchCandidateRepository implements DirectSearchCandidateRepository
{
    public function find(array $terms, array $sourceTypeKeys, int $limit): DirectSearchCandidateBatch
    {
        if ($terms === []) {
            return new DirectSearchCandidateBatch([], false);
        }

        $query = DB::table('search_document_entries as entry')
            ->join('search_documents as document', 'document.source_id', '=', 'entry.source_id')
            ->whereIn('entry.value', $terms)
            ->orderBy('document.source_id')
            ->orderBy('entry.id')
            ->limit($limit + 1)
            ->select([
                'document.source_id',
                'document.source_name',
                'document.source_type_key',
                'document.stale',
                'entry.field',
                'entry.value',
                'entry.origin',
                'entry.mention_id',
                'entry.claim_id',
                'entry.claim_revision_id',
                'entry.language',
                'entry.script',
                'entry.representation_relation',
            ]);

        if ($sourceTypeKeys !== []) {
            $query->whereIn('document.source_type_key', $sourceTypeKeys);
        }

        $rows = $query->get();
        $truncated = $rows->count() > $limit;
        if ($truncated) {
            $rows = $rows->take($limit);
        }

        $candidates = [];
        foreach ($rows as $row) {
            $candidates[] = new DirectSearchCandidate(
                sourceId: new SourceId((string) $row->source_id),
                sourceName: $row->source_name === null ? null : (string) $row->source_name,
                sourceTypeKey: (string) $row->source_type_key,
                stale: (bool) $row->stale,
                matchedValue: (string) $row->value,
                field: (string) $row->field,
                origin: SearchValueOrigin::from((string) $row->origin),
                mentionId: $row->mention_id === null ? null : (string) $row->mention_id,
                claimId: $row->claim_id === null ? null : (string) $row->claim_id,
                claimRevisionId: $row->claim_revision_id === null ? null : (string) $row->claim_revision_id,
                language: $row->language === null ? null : (string) $row->language,
                script: $row->script === null ? null : (string) $row->script,
                representationRelation: $row->representation_relation === null
                    ? null
                    : (string) $row->representation_relation,
            );
        }

        return new DirectSearchCandidateBatch($candidates, $truncated);
    }
}
