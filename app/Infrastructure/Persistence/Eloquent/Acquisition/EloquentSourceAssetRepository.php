<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Acquisition;

use App\Application\Acquisition\SourceAssetRepository;
use App\Domain\Acquisition\SourceAsset;
use App\Domain\Acquisition\SourceAssetId;
use App\Domain\Acquisition\SourceAssetStorageReference;
use App\Domain\Acquisition\SourceId;
use App\Infrastructure\Persistence\Eloquent\Acquisition\Models\SourceAssetRecord;
use DateTimeImmutable;
use DateTimeInterface;
use UnexpectedValueException;

final class EloquentSourceAssetRepository implements SourceAssetRepository
{
    public function save(SourceAsset $asset): void
    {
        SourceAssetRecord::query()->updateOrCreate(
            ['id' => $asset->id->value],
            [
                'source_id' => $asset->sourceId?->value,
                'schema_version' => $asset->schemaVersion,
                'storage_disk' => $asset->storage->disk,
                'storage_path' => $asset->storage->path,
                'original_filename' => $asset->originalFilename,
                'mime_type' => $asset->mimeType,
                'byte_size' => $asset->byteSize,
                'sha256' => $asset->sha256,
                'metadata' => $asset->metadata,
                'provenance' => $asset->provenance,
                'retrieved_at' => $asset->retrievedAt,
            ],
        );
    }

    public function find(SourceAssetId $id): ?SourceAsset
    {
        $record = SourceAssetRecord::query()->find($id->value);

        return $record === null ? null : $this->toDomain($record);
    }

    public function forSource(SourceId $sourceId): array
    {
        $assets = [];
        $records = SourceAssetRecord::query()
            ->where('source_id', $sourceId->value)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        foreach ($records as $record) {
            $assets[] = $this->toDomain($record);
        }

        return $assets;
    }

    private function toDomain(SourceAssetRecord $record): SourceAsset
    {
        $metadata = $record->getAttribute('metadata');
        $provenance = $record->getAttribute('provenance');
        $retrievedAt = $record->getAttribute('retrieved_at');

        if (! is_array($metadata)) {
            throw new UnexpectedValueException('Stored Source asset metadata must be an array.');
        }

        if (! is_array($provenance)) {
            throw new UnexpectedValueException('Stored Source asset provenance must be an array.');
        }

        if (! $retrievedAt instanceof DateTimeInterface) {
            throw new UnexpectedValueException('Stored Source asset retrieval timestamp must be a date-time value.');
        }

        return new SourceAsset(
            id: new SourceAssetId((string) $record->id),
            sourceId: $record->source_id === null ? null : new SourceId((string) $record->source_id),
            storage: new SourceAssetStorageReference(
                disk: (string) $record->storage_disk,
                path: (string) $record->storage_path,
            ),
            originalFilename: (string) $record->original_filename,
            mimeType: (string) $record->mime_type,
            byteSize: (int) $record->byte_size,
            sha256: (string) $record->sha256,
            retrievedAt: DateTimeImmutable::createFromInterface($retrievedAt),
            metadata: $this->stringKeyedArray($metadata, 'metadata'),
            provenance: $this->stringKeyedArray($provenance, 'provenance'),
            schemaVersion: (int) $record->schema_version,
        );
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<string, mixed>
     */
    private function stringKeyedArray(array $values, string $field): array
    {
        $result = [];

        foreach ($values as $key => $value) {
            if (! is_string($key)) {
                throw new UnexpectedValueException(sprintf(
                    'Stored Source asset %s must use string keys.',
                    $field,
                ));
            }

            $result[$key] = $value;
        }

        return $result;
    }
}
