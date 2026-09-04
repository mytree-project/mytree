<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Acquisition;

use App\Application\Acquisition\SourceRepository;
use App\Domain\Acquisition\Source;
use App\Domain\Acquisition\SourceId;
use App\Domain\Acquisition\SourceMetadata;
use App\Domain\Acquisition\SourceText;
use App\Domain\Acquisition\SourceTextId;
use App\Domain\Acquisition\SourceTextKind;
use App\Domain\Acquisition\SourceType;
use App\Infrastructure\Persistence\Eloquent\Acquisition\Models\SourceRecord;
use App\Infrastructure\Persistence\Eloquent\Acquisition\Models\SourceTextRecord;
use Illuminate\Support\Facades\DB;
use UnexpectedValueException;

final class EloquentSourceRepository implements SourceRepository
{
    public function save(Source $source): void
    {
        DB::transaction(function () use ($source): void {
            SourceRecord::query()->updateOrCreate(
                ['id' => $source->id->value],
                [
                    'schema_version' => $source->schemaVersion,
                    'source_type_key' => $source->type->key,
                    'source_type_schema_version' => $source->type->schemaVersion,
                    'metadata' => $source->metadata->toArray(),
                ],
            );

            SourceTextRecord::query()
                ->where('source_id', $source->id->value)
                ->delete();

            foreach ($source->texts as $position => $text) {
                SourceTextRecord::query()->create([
                    'id' => $text->id->value,
                    'source_id' => $source->id->value,
                    'schema_version' => $text->schemaVersion,
                    'kind' => $text->kind->value,
                    'language' => $text->language,
                    'content' => $text->content,
                    'position' => $position,
                ]);
            }
        });
    }

    public function find(SourceId $id): ?Source
    {
        $record = SourceRecord::query()->find($id->value);

        if ($record === null) {
            return null;
        }

        $metadata = $record->metadata;

        if (! is_array($metadata)) {
            throw new UnexpectedValueException('Stored Source metadata must be an array.');
        }

        /** @var array<string, mixed> $metadata */
        $texts = [];
        $textRecords = SourceTextRecord::query()
            ->where('source_id', $id->value)
            ->orderBy('position')
            ->get();

        foreach ($textRecords as $textRecord) {
            $texts[] = new SourceText(
                id: new SourceTextId((string) $textRecord->id),
                kind: SourceTextKind::from((string) $textRecord->kind),
                content: (string) $textRecord->content,
                language: $textRecord->language === null ? null : (string) $textRecord->language,
                schemaVersion: (int) $textRecord->schema_version,
            );
        }

        return new Source(
            id: new SourceId((string) $record->id),
            type: new SourceType(
                key: (string) $record->source_type_key,
                schemaVersion: (int) $record->source_type_schema_version,
            ),
            metadata: new SourceMetadata($metadata),
            texts: $texts,
            schemaVersion: (int) $record->schema_version,
        );
    }
}
