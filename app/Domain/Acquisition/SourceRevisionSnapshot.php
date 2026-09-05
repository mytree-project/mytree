<?php

declare(strict_types=1);

namespace App\Domain\Acquisition;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use JsonException;

final readonly class SourceRevisionSnapshot
{
    public const SCHEMA_ID = 'mytree.source-revision.v1';

    public const SCHEMA_VERSION = 1;

    private const JSON_FLAGS = JSON_THROW_ON_ERROR
        | JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
        | JSON_PRESERVE_ZERO_FRACTION;

    private function __construct(
        public int $schemaVersion,
        public string $canonicalPayload,
        public string $payloadHash,
    ) {}

    /** @param list<SourceAsset> $assets */
    public static function capture(Source $source, array $assets): self
    {
        usort(
            $assets,
            static fn (SourceAsset $left, SourceAsset $right): int => $left->id->value <=> $right->id->value,
        );

        $assetPayloads = [];

        foreach ($assets as $asset) {
            $assetSourceId = $asset->sourceId;

            if ($assetSourceId === null || $assetSourceId->value !== $source->id->value) {
                throw new InvalidArgumentException('Only assets currently attached to the Source may be snapshotted.');
            }

            $assetPayloads[] = [
                'id' => $asset->id->value,
                'source_id' => $assetSourceId->value,
                'schema_version' => $asset->schemaVersion,
                'storage' => [
                    'disk' => $asset->storage->disk,
                    'path' => $asset->storage->path,
                ],
                'original_filename' => $asset->originalFilename,
                'mime_type' => $asset->mimeType,
                'byte_size' => $asset->byteSize,
                'sha256' => $asset->sha256,
                'retrieved_at' => self::canonicalDateTime($asset->retrievedAt),
                'metadata' => $asset->metadata,
                'provenance' => $asset->provenance,
            ];
        }

        $textPayloads = [];

        foreach ($source->texts as $text) {
            $textPayloads[] = [
                'id' => $text->id->value,
                'schema_version' => $text->schemaVersion,
                'kind' => $text->kind->value,
                'content' => $text->content,
                'language' => $text->language,
            ];
        }

        $payload = [
            'schema' => self::SCHEMA_ID,
            'source' => [
                'id' => $source->id->value,
                'schema_version' => $source->schemaVersion,
                'type' => [
                    'key' => $source->type->key,
                    'schema_version' => $source->type->schemaVersion,
                ],
                'metadata' => $source->metadata->toArray(),
                'texts' => $textPayloads,
                'assets' => $assetPayloads,
            ],
        ];

        $canonicalPayload = self::encodeCanonical($payload);

        return new self(
            schemaVersion: self::SCHEMA_VERSION,
            canonicalPayload: $canonicalPayload,
            payloadHash: hash('sha256', $canonicalPayload),
        );
    }

    public static function rehydrate(
        int $schemaVersion,
        string $canonicalPayload,
        string $payloadHash,
    ): self {
        if ($schemaVersion !== self::SCHEMA_VERSION) {
            throw new InvalidArgumentException(sprintf(
                'Unsupported SourceRevision snapshot schema version %d.',
                $schemaVersion,
            ));
        }

        $normalizedHash = strtolower($payloadHash);

        if (preg_match('/^[0-9a-f]{64}$/D', $normalizedHash) !== 1) {
            throw new InvalidArgumentException('SourceRevision payload hash must be a SHA-256 hexadecimal value.');
        }

        if (! hash_equals($normalizedHash, hash('sha256', $canonicalPayload))) {
            throw new InvalidArgumentException('SourceRevision payload hash does not match the stored payload.');
        }

        $decoded = self::decodeObject($canonicalPayload);

        if (($decoded['schema'] ?? null) !== self::SCHEMA_ID) {
            throw new InvalidArgumentException('Unsupported SourceRevision snapshot schema identifier.');
        }

        if (self::encodeCanonical($decoded) !== $canonicalPayload) {
            throw new InvalidArgumentException('Stored SourceRevision payload is not in canonical form.');
        }

        return new self(
            schemaVersion: $schemaVersion,
            canonicalPayload: $canonicalPayload,
            payloadHash: $normalizedHash,
        );
    }

    public function reconstruct(): SourceRevisionState
    {
        $payload = self::decodeObject($this->canonicalPayload);
        $sourcePayload = self::objectAt($payload, 'source');
        $typePayload = self::objectAt($sourcePayload, 'type');
        $sourceId = new SourceId(self::stringAt($sourcePayload, 'id'));

        $texts = [];

        foreach (self::listAt($sourcePayload, 'texts') as $textValue) {
            $textPayload = self::objectValue($textValue, 'SourceRevision text');
            $texts[] = new SourceText(
                id: new SourceTextId(self::stringAt($textPayload, 'id')),
                kind: SourceTextKind::from(self::stringAt($textPayload, 'kind')),
                content: self::stringAt($textPayload, 'content'),
                language: self::nullableStringAt($textPayload, 'language'),
                schemaVersion: self::intAt($textPayload, 'schema_version'),
            );
        }

        $source = new Source(
            id: $sourceId,
            type: new SourceType(
                key: self::stringAt($typePayload, 'key'),
                schemaVersion: self::intAt($typePayload, 'schema_version'),
            ),
            metadata: new SourceMetadata(self::stringKeyedObjectAt($sourcePayload, 'metadata')),
            texts: $texts,
            schemaVersion: self::intAt($sourcePayload, 'schema_version'),
        );

        $assets = [];

        foreach (self::listAt($sourcePayload, 'assets') as $assetValue) {
            $assetPayload = self::objectValue($assetValue, 'SourceRevision asset');
            $storagePayload = self::objectAt($assetPayload, 'storage');
            $assetSourceId = self::stringAt($assetPayload, 'source_id');

            if ($assetSourceId !== $sourceId->value) {
                throw new InvalidArgumentException('Stored SourceRevision asset belongs to a different Source.');
            }

            $retrievedAt = DateTimeImmutable::createFromFormat(
                '!Y-m-d\TH:i:s.u\Z',
                self::stringAt($assetPayload, 'retrieved_at'),
                new DateTimeZone('UTC'),
            );

            if ($retrievedAt === false) {
                throw new InvalidArgumentException('Stored SourceRevision asset retrieval timestamp is invalid.');
            }

            $assets[] = new SourceAsset(
                id: new SourceAssetId(self::stringAt($assetPayload, 'id')),
                sourceId: $sourceId,
                storage: new SourceAssetStorageReference(
                    disk: self::stringAt($storagePayload, 'disk'),
                    path: self::stringAt($storagePayload, 'path'),
                ),
                originalFilename: self::stringAt($assetPayload, 'original_filename'),
                mimeType: self::stringAt($assetPayload, 'mime_type'),
                byteSize: self::intAt($assetPayload, 'byte_size'),
                sha256: self::stringAt($assetPayload, 'sha256'),
                retrievedAt: $retrievedAt,
                metadata: self::stringKeyedObjectAt($assetPayload, 'metadata'),
                provenance: self::stringKeyedObjectAt($assetPayload, 'provenance'),
                schemaVersion: self::intAt($assetPayload, 'schema_version'),
            );
        }

        return new SourceRevisionState($source, $assets);
    }

    private static function canonicalDateTime(DateTimeImmutable $dateTime): string
    {
        return $dateTime
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d\TH:i:s.u\Z');
    }

    /** @param array<array-key, mixed> $value */
    private static function encodeCanonical(array $value): string
    {
        try {
            return json_encode(self::canonicalize($value), self::JSON_FLAGS);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('SourceRevision state cannot be serialized as canonical JSON.', 0, $exception);
        }
    }

    private static function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(
                static fn (mixed $nestedValue): mixed => self::canonicalize($nestedValue),
                $value,
            );
        }

        ksort($value, SORT_STRING);

        foreach ($value as $key => $nestedValue) {
            $value[$key] = self::canonicalize($nestedValue);
        }

        return $value;
    }

    /** @return array<string, mixed> */
    private static function decodeObject(string $payload): array
    {
        try {
            $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('Stored SourceRevision payload is not valid JSON.', 0, $exception);
        }

        return self::objectValue($decoded, 'SourceRevision payload');
    }

    /**
     * @param  array<string, mixed>  $object
     * @return array<string, mixed>
     */
    private static function objectAt(array $object, string $key): array
    {
        if (! array_key_exists($key, $object)) {
            throw new InvalidArgumentException(sprintf('Stored SourceRevision payload is missing "%s".', $key));
        }

        return self::objectValue($object[$key], sprintf('SourceRevision "%s"', $key));
    }

    /** @return array<string, mixed> */
    private static function objectValue(mixed $value, string $field): array
    {
        if (! is_array($value) || ($value !== [] && array_is_list($value))) {
            throw new InvalidArgumentException(sprintf('%s must be a JSON object.', $field));
        }

        $result = [];

        foreach ($value as $key => $nestedValue) {
            if (! is_string($key)) {
                throw new InvalidArgumentException(sprintf('%s must use string keys.', $field));
            }

            $result[$key] = $nestedValue;
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $object
     * @return list<mixed>
     */
    private static function listAt(array $object, string $key): array
    {
        $value = $object[$key] ?? null;

        if (! is_array($value) || ! array_is_list($value)) {
            throw new InvalidArgumentException(sprintf('Stored SourceRevision "%s" must be a JSON list.', $key));
        }

        return $value;
    }

    /** @param array<string, mixed> $object */
    private static function stringAt(array $object, string $key): string
    {
        $value = $object[$key] ?? null;

        if (! is_string($value)) {
            throw new InvalidArgumentException(sprintf('Stored SourceRevision "%s" must be a string.', $key));
        }

        return $value;
    }

    /** @param array<string, mixed> $object */
    private static function nullableStringAt(array $object, string $key): ?string
    {
        if (! array_key_exists($key, $object) || $object[$key] === null) {
            return null;
        }

        return self::stringAt($object, $key);
    }

    /** @param array<string, mixed> $object */
    private static function intAt(array $object, string $key): int
    {
        $value = $object[$key] ?? null;

        if (! is_int($value)) {
            throw new InvalidArgumentException(sprintf('Stored SourceRevision "%s" must be an integer.', $key));
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $object
     * @return array<string, mixed>
     */
    private static function stringKeyedObjectAt(array $object, string $key): array
    {
        return self::objectAt($object, $key);
    }
}
