<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Acquisition;

use App\Application\Acquisition\MentionNotFound;
use App\Application\Acquisition\MentionRepository;
use App\Domain\Acquisition\Mention;
use App\Domain\Acquisition\MentionId;
use App\Domain\Acquisition\MentionKind;
use App\Domain\Acquisition\MentionRawData;
use App\Domain\Acquisition\SourceId;
use App\Infrastructure\Persistence\Eloquent\Acquisition\Models\MentionRecord;
use UnexpectedValueException;

final class EloquentMentionRepository implements MentionRepository
{
    public function add(Mention $mention): void
    {
        MentionRecord::query()->create($this->attributes($mention, includeOwnership: true));
    }

    public function update(Mention $mention): void
    {
        MentionRecord::query()
            ->where('id', $mention->id->value)
            ->where('source_id', $mention->sourceId->value)
            ->update($this->attributes($mention, includeOwnership: false));
    }

    public function find(SourceId $sourceId, MentionId $mentionId): ?Mention
    {
        $record = MentionRecord::query()
            ->where('source_id', $sourceId->value)
            ->where('id', $mentionId->value)
            ->first();

        return $record === null ? null : $this->map($record);
    }

    public function forSource(SourceId $sourceId): array
    {
        $mentions = [];

        $records = MentionRecord::query()
            ->where('source_id', $sourceId->value)
            ->orderBy('local_key')
            ->get();

        foreach ($records as $record) {
            $mentions[] = $this->map($record);
        }

        return $mentions;
    }

    public function remove(SourceId $sourceId, MentionId $mentionId): void
    {
        $deleted = MentionRecord::query()
            ->where('source_id', $sourceId->value)
            ->where('id', $mentionId->value)
            ->delete();

        if ($deleted !== 1) {
            throw MentionNotFound::forSourceAndId($sourceId, $mentionId);
        }
    }

    private function map(MentionRecord $record): Mention
    {
        $rawData = $record->getAttribute('raw_data');

        if (! is_array($rawData)) {
            throw new UnexpectedValueException('Stored Mention raw data must be an array.');
        }

        return new Mention(
            id: new MentionId((string) $record->id),
            sourceId: new SourceId((string) $record->source_id),
            kind: new MentionKind((string) $record->kind),
            localKey: (string) $record->local_key,
            role: $record->role === null ? null : (string) $record->role,
            displayLabel: $record->display_label === null ? null : (string) $record->display_label,
            rawData: new MentionRawData($this->stringKeyedArray($rawData)),
            schemaVersion: (int) $record->schema_version,
        );
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
                throw new UnexpectedValueException('Stored Mention raw data must use string keys.');
            }

            $result[$key] = $value;
        }

        return $result;
    }

    /** @return array<string, mixed> */
    private function attributes(Mention $mention, bool $includeOwnership): array
    {
        $attributes = [
            'schema_version' => $mention->schemaVersion,
            'kind' => $mention->kind->key,
            'local_key' => $mention->localKey,
            'role' => $mention->role,
            'display_label' => $mention->displayLabel,
            'raw_data' => $mention->rawData->toArray(),
        ];

        if ($includeOwnership) {
            $attributes['id'] = $mention->id->value;
            $attributes['source_id'] = $mention->sourceId->value;
        }

        return $attributes;
    }
}
