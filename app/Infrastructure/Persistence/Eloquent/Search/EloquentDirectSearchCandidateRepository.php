<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Search;

use App\Application\Search\DirectSearchCandidate;
use App\Application\Search\DirectSearchCandidateBatch;
use App\Application\Search\DirectSearchCandidateRepository;
use App\Application\Search\SearchQueryTerm;
use App\Application\Search\SearchValueOrigin;
use App\Domain\Acquisition\SourceId;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use UnexpectedValueException;

final class EloquentDirectSearchCandidateRepository implements DirectSearchCandidateRepository
{
    public function find(array $terms, array $sourceTypeKeys, int $limit): DirectSearchCandidateBatch
    {
        if ($terms === []) {
            return new DirectSearchCandidateBatch([], false);
        }

        $values = array_map(static fn (SearchQueryTerm $term): string => $term->value, $terms);
        $query = DB::table('search_document_entries as entry')
            ->join('search_documents as document', 'document.source_id', '=', 'entry.source_id')
            ->where(static function (Builder $match) use ($terms, $values): void {
                $match->whereIn('entry.value', $values);
                foreach ($terms as $term) {
                    foreach ($term->variants as $variant) {
                        foreach ($variant->forms as $form) {
                            $match->orWhere(static function (Builder $comparison) use ($form, $variant): void {
                                $comparison->where('entry.field', $variant->field())
                                    ->whereJsonContains('entry.index_forms', $form);
                            });
                        }
                    }
                    foreach ($term->comparisonForms as $form) {
                        $match->orWhere(static function (Builder $comparison) use ($form): void {
                            $comparison->where('entry.origin', '!=', SearchValueOrigin::SourceMetadata->value)
                                ->whereJsonContains('entry.index_forms', $form);
                        });
                    }
                }
            });

        if ($sourceTypeKeys !== []) {
            $query->whereIn('document.source_type_key', $sourceTypeKeys);
        }

        // Limit complete Source candidates rather than individual matching entries.
        $sourceRows = (clone $query)
            ->select('document.source_id')
            ->distinct()
            ->orderBy('document.source_id')
            ->limit($limit + 1)
            ->get();
        $truncated = $sourceRows->count() > $limit;
        $sourceIds = $sourceRows->take($limit)->pluck('source_id')->all();

        if ($sourceIds === []) {
            return new DirectSearchCandidateBatch([], false);
        }

        $rows = $query->whereIn('document.source_id', $sourceIds)
            ->orderBy('document.source_id')
            ->orderBy('entry.id')
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
                'entry.index_forms',
            ])
            ->get();

        $candidates = [];
        foreach ($rows as $row) {
            $forms = json_decode((string) $row->index_forms, true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($forms) || ! array_is_list($forms)) {
                throw new UnexpectedValueException('Search index forms must be a JSON array.');
            }

            /** @var list<string> $forms */
            $forms = array_map(
                static fn (mixed $form): string => (string) $form,
                $forms,
            );

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
                indexForms: $forms,
            );
        }

        return new DirectSearchCandidateBatch($candidates, $truncated);
    }
}
