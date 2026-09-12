<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Acquisition;

use App\Application\Acquisition\SourceBrowseItem;
use App\Application\Acquisition\SourceBrowseRepository;
use App\Domain\Acquisition\SourceId;
use App\Domain\Acquisition\SourceMetadata;
use App\Domain\Acquisition\SourceType;
use App\Infrastructure\Persistence\Eloquent\Acquisition\Models\SourceRecord;
use App\Infrastructure\Persistence\Eloquent\Acquisition\Models\SourceRevisionRecord;
use JsonException;
use UnexpectedValueException;

final class EloquentSourceBrowseRepository implements SourceBrowseRepository
{
    public function search(?string $query = null): array
    {
        $needle = mb_strtolower(trim((string) $query));
        $items = [];

        foreach (SourceRecord::query()->orderByDesc('updated_at')->orderBy('id')->get() as $record) {
            $item = $this->toItem($record);

            if ($needle !== '' && ! str_contains($this->searchableText($item), $needle)) {
                continue;
            }

            $items[] = $item;

            if (count($items) >= 200) {
                break;
            }
        }

        return $items;
    }

    public function find(SourceId $sourceId): ?SourceBrowseItem
    {
        $record = SourceRecord::query()->find($sourceId->value);

        return $record === null ? null : $this->toItem($record);
    }

    private function toItem(SourceRecord $record): SourceBrowseItem
    {
        $metadata = $record->getAttribute('metadata');
        if (! is_array($metadata)) {
            throw new UnexpectedValueException('Stored Source metadata must be an array.');
        }

        $revisionNumber = SourceRevisionRecord::query()
            ->where('source_id', (string) $record->id)
            ->max('revision_number');

        if (! is_int($revisionNumber) && ! is_numeric($revisionNumber)) {
            throw new UnexpectedValueException('Stored Source must have a retained revision.');
        }

        return new SourceBrowseItem(
            id: new SourceId((string) $record->id),
            type: new SourceType(
                key: (string) $record->source_type_key,
                schemaVersion: (int) $record->source_type_schema_version,
            ),
            metadata: new SourceMetadata($this->stringKeyedArray($metadata)),
            revisionNumber: (int) $revisionNumber,
        );
    }

    private function searchableText(SourceBrowseItem $item): string
    {
        try {
            $metadata = json_encode($item->metadata->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        } catch (JsonException) {
            $metadata = '';
        }

        return mb_strtolower(implode(' ', [
            $item->id->value,
            $item->type->key,
            $metadata,
        ]));
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<string, mixed>
     */
    private function stringKeyedArray(array $values): array
    {
        $result = [];

        foreach ($values as $key => $value) {
            if (! is_string($key)) {
                throw new UnexpectedValueException('Stored Source metadata must use string keys.');
            }

            $result[$key] = $value;
        }

        return $result;
    }
}
