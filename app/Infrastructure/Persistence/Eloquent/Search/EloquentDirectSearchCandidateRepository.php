<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Search;

use App\Application\Search\DirectSearchCandidate;
use App\Application\Search\DirectSearchCandidateBatch;
use App\Application\Search\DirectSearchCandidateRepository;
use App\Application\Search\SearchDateSpan;
use App\Application\Search\SearchQueryTerm;
use App\Application\Search\SearchSortMode;
use App\Application\Search\SearchSourceCandidate;
use App\Application\Search\SearchSourceCandidateBatch;
use App\Application\Search\SearchValueOrigin;
use App\Domain\Acquisition\SourceId;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use UnexpectedValueException;

final class EloquentDirectSearchCandidateRepository implements DirectSearchCandidateRepository
{
    public function find(
        array $terms,
        array $sourceTypeKeys,
        int $limit,
        ?SearchDateSpan $dateRange = null,
        ?SearchQueryTerm $place = null,
        SearchSortMode $sort = SearchSortMode::Relevance,
    ): DirectSearchCandidateBatch
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

        $this->applyFilters($query, $sourceTypeKeys, $dateRange, $place);

        return $this->selectCandidates($query, $limit, $sort);
    }

    public function findSources(
        array $sourceTypeKeys,
        int $limit,
        ?SearchDateSpan $dateRange = null,
        ?SearchQueryTerm $place = null,
        SearchSortMode $sort = SearchSortMode::Relevance,
    ): SearchSourceCandidateBatch {
        $query = DB::table('search_documents as document');
        $this->applyFilters($query, $sourceTypeKeys, $dateRange, $place);

        [$ids, $truncated] = $this->selectSourceIds($query, $limit, $sort);
        if ($ids === []) {
            return new SearchSourceCandidateBatch([], false);
        }

        $rows = DB::table('search_documents as document')
            ->join('sources as source', 'source.id', '=', 'document.source_id')
            ->whereIn('document.source_id', $ids)
            ->select([
                'document.source_id',
                'document.source_name',
                'document.source_type_key',
                'document.stale',
                'document.source_date_ordinal',
                'source.created_at as source_created_at',
            ])
            ->get();

        $sources = [];
        foreach ($rows as $row) {
            $sources[] = new SearchSourceCandidate(
                sourceId: new SourceId((string) $row->source_id),
                sourceName: $row->source_name === null ? null : (string) $row->source_name,
                sourceTypeKey: (string) $row->source_type_key,
                stale: (bool) $row->stale,
                sourceDateOrdinal: $row->source_date_ordinal === null ? null : (int) $row->source_date_ordinal,
                sourceCreatedAt: (string) $row->source_created_at,
            );
        }

        return new SearchSourceCandidateBatch($sources, $truncated);
    }

    public function findFuzzy(
        array $terms,
        array $sourceTypeKeys,
        int $limit,
        ?SearchDateSpan $dateRange = null,
        ?SearchQueryTerm $place = null,
        SearchSortMode $sort = SearchSortMode::Relevance,
    ): DirectSearchCandidateBatch
    {
        // A portable, deterministic preselection: names/places sharing an initial
        // Unicode letter. A finite Source window is always loaded before computing
        // any similarity in Application (no whole-table PHP candidate scan).
        $prefixes = [];
        foreach ($terms as $term) {
            foreach ($term->comparisonForms as $form) {
                if (mb_strlen($form) < 4) {
                    continue;
                }

                $prefix = mb_strtolower(mb_substr($form, 0, 1));
                if (preg_match('/^\\p{L}$/u', $prefix) === 1) {
                    $prefixes[$prefix] = true;
                }
            }
        }

        if ($prefixes === []) {
            return new DirectSearchCandidateBatch([], false);
        }

        ksort($prefixes);
        $query = DB::table('search_document_entries as entry')
            ->join('search_documents as document', 'document.source_id', '=', 'entry.source_id')
            ->where('entry.origin', '!=', SearchValueOrigin::SourceMetadata->value)
            ->whereIn('entry.field', ['person.given_name', 'person.surname', 'place.name'])
            ->where(static function (Builder $match) use ($prefixes): void {
                foreach (array_keys($prefixes) as $prefix) {
                    $match->orWhereRaw('LOWER(entry.value) LIKE ?', [$prefix.'%']);
                }
            });

        $this->applyFilters($query, $sourceTypeKeys, $dateRange, $place);

        return $this->selectCandidates($query, $limit, $sort);
    }

    /** @param  list<string>  $sourceTypeKeys */
    private function applyFilters(
        Builder $query,
        array $sourceTypeKeys,
        ?SearchDateSpan $dateRange,
        ?SearchQueryTerm $place,
    ): void {
        if ($sourceTypeKeys !== []) {
            $query->whereIn('document.source_type_key', $sourceTypeKeys);
        }

        if ($dateRange !== null) {
            $query->whereExists(static function (Builder $dates) use ($dateRange): void {
                $dates->selectRaw('1')
                    ->from('search_document_dates as date_filter')
                    ->whereColumn('date_filter.source_id', 'document.source_id')
                    ->where('date_filter.lower_ordinal', '<=', $dateRange->to)
                    ->where('date_filter.upper_ordinal', '>=', $dateRange->from);
            });
        }

        if ($place !== null) {
            $query->whereExists(static function (Builder $values) use ($place): void {
                $values->selectRaw('1')
                    ->from('search_document_entries as place_filter')
                    ->whereColumn('place_filter.source_id', 'document.source_id')
                    ->where('place_filter.field', 'place.name')
                    ->where(static function (Builder $matching) use ($place): void {
                        $matching->where('place_filter.value', $place->value);
                        foreach ($place->comparisonForms as $form) {
                            $matching->orWhereJsonContains('place_filter.index_forms', $form);
                        }
                        foreach ($place->variants as $variant) {
                            foreach ($variant->forms as $form) {
                                $matching->orWhereJsonContains('place_filter.index_forms', $form);
                            }
                        }
                    });
            });
        }
    }

    /** @return array{list<string>, bool} */
    private function selectSourceIds(Builder $query, int $limit, SearchSortMode $sort): array
    {
        $sourceQuery = (clone $query)
            ->join('sources as source', 'source.id', '=', 'document.source_id')
            ->select('document.source_id')
            ->groupBy('document.source_id', 'document.source_date_ordinal', 'source.created_at');

        $this->sortSourceQuery($sourceQuery, $sort);
        $sourceRows = $sourceQuery->limit($limit + 1)->get();

        return [$sourceRows->take($limit)->pluck('source_id')->all(), $sourceRows->count() > $limit];
    }

    private function sortSourceQuery(Builder $query, SearchSortMode $sort): void
    {
        if ($sort === SearchSortMode::SourceDate) {
            $query->orderByRaw('document.source_date_ordinal IS NULL')
                ->orderByDesc('document.source_date_ordinal');
        } elseif ($sort === SearchSortMode::SourceAdded) {
            $query->orderByDesc('source.created_at');
        }

        $query->orderBy('document.source_id');
    }

    private function selectCandidates(Builder $query, int $limit, SearchSortMode $sort): DirectSearchCandidateBatch
    {
        // Select complete Source windows rather than truncating individual entries.
        [$sourceIds, $truncated] = $this->selectSourceIds($query, $limit, $sort);

        if ($sourceIds === []) {
            return new DirectSearchCandidateBatch([], false);
        }

        $rows = $query->join('sources as source', 'source.id', '=', 'document.source_id')
            ->whereIn('document.source_id', $sourceIds)
            ->orderBy('document.source_id')
            ->orderBy('entry.id')
            ->select([
                'document.source_id',
                'document.source_name',
                'document.source_type_key',
                'document.stale',
                'document.source_date_ordinal',
                'source.created_at as source_created_at',
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
                sourceDateOrdinal: $row->source_date_ordinal === null ? null : (int) $row->source_date_ordinal,
                sourceCreatedAt: (string) $row->source_created_at,
            );
        }

        return new DirectSearchCandidateBatch($candidates, $truncated);
    }
}
