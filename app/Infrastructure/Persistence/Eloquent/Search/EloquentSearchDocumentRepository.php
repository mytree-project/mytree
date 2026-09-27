<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Search;

use App\Application\Search\SearchDocument;
use App\Application\Search\SearchDocumentEntry;
use App\Application\Search\SearchDocumentRepository;
use App\Application\Search\SearchValueOrigin;
use App\Domain\Acquisition\EvidenceStateId;
use App\Domain\Acquisition\SourceId;
use App\Domain\Acquisition\SourceRevisionId;
use App\Infrastructure\Persistence\Eloquent\Search\Models\SearchDocumentEntryRecord;
use App\Infrastructure\Persistence\Eloquent\Search\Models\SearchDocumentRecord;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use UnexpectedValueException;

final class EloquentSearchDocumentRepository implements SearchDocumentRepository
{
    public function replace(SearchDocument $document): void
    {
        DB::transaction(function () use ($document): void {
            SearchDocumentRecord::query()->updateOrCreate(
                ['source_id' => $document->sourceId->value],
                [
                    'evidence_state_id' => $document->evidenceStateId->value,
                    'source_revision_id' => $document->sourceRevisionId->value,
                    'source_name' => $document->sourceName,
                    'source_type_key' => $document->sourceTypeKey,
                    'input_fingerprint' => $document->inputFingerprint,
                    'index_signature' => $document->indexSignature,
                    'stale' => $document->stale,
                    'built_at' => $document->builtAt,
                ],
            );

            SearchDocumentEntryRecord::query()
                ->where('source_id', $document->sourceId->value)
                ->delete();

            foreach ($document->entries as $entry) {
                SearchDocumentEntryRecord::query()->create([
                    'source_id' => $document->sourceId->value,
                    'field' => $entry->field,
                    'value' => $entry->value,
                    'origin' => $entry->origin->value,
                    'mention_id' => $entry->mentionId,
                    'claim_id' => $entry->claimId,
                    'claim_revision_id' => $entry->claimRevisionId,
                    'language' => $entry->language,
                    'script' => $entry->script,
                    'representation_relation' => $entry->representationRelation,
                    'index_forms' => $entry->indexForms,
                ]);
            }
        });
    }

    public function find(SourceId $sourceId): ?SearchDocument
    {
        $record = SearchDocumentRecord::query()->find($sourceId->value);

        if ($record === null) {
            return null;
        }

        $builtAt = $record->getAttribute('built_at');
        if (! $builtAt instanceof DateTimeInterface) {
            throw new UnexpectedValueException('Stored SearchDocument built_at must be a date-time value.');
        }

        $entries = SearchDocumentEntryRecord::query()
            ->where('source_id', $sourceId->value)
            ->orderBy('id')
            ->get()
            ->map(static function (SearchDocumentEntryRecord $entry): SearchDocumentEntry {
                $indexForms = $entry->getAttribute('index_forms');

                if (! is_array($indexForms)) {
                    throw new UnexpectedValueException('Stored SearchDocument index forms must be an array.');
                }

                /** @var list<string> $forms */
                $forms = array_values(array_map(
                    static fn (mixed $value): string => (string) $value,
                    $indexForms,
                ));

                return new SearchDocumentEntry(
                    field: (string) $entry->field,
                    value: (string) $entry->value,
                    origin: SearchValueOrigin::from((string) $entry->origin),
                    mentionId: $entry->mention_id === null ? null : (string) $entry->mention_id,
                    claimId: $entry->claim_id === null ? null : (string) $entry->claim_id,
                    claimRevisionId: $entry->claim_revision_id === null ? null : (string) $entry->claim_revision_id,
                    language: $entry->language === null ? null : (string) $entry->language,
                    script: $entry->script === null ? null : (string) $entry->script,
                    representationRelation: $entry->representation_relation === null
                        ? null
                        : (string) $entry->representation_relation,
                    indexForms: $forms,
                );
            })
            ->all();

        return new SearchDocument(
            sourceId: new SourceId((string) $record->source_id),
            evidenceStateId: new EvidenceStateId((string) $record->evidence_state_id),
            sourceRevisionId: new SourceRevisionId((string) $record->source_revision_id),
            sourceName: $record->source_name === null ? null : (string) $record->source_name,
            sourceTypeKey: (string) $record->source_type_key,
            inputFingerprint: (string) $record->input_fingerprint,
            indexSignature: (string) $record->index_signature,
            entries: $entries,
            stale: (bool) $record->stale,
            builtAt: DateTimeImmutable::createFromInterface($builtAt),
        );
    }

    public function markStale(SourceId $sourceId): void
    {
        SearchDocumentRecord::query()
            ->whereKey($sourceId->value)
            ->update(['stale' => true]);
    }
}
