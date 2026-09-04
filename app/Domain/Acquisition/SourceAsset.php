<?php

declare(strict_types=1);

namespace App\Domain\Acquisition;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class SourceAsset
{
    public const SCHEMA_VERSION = 1;

    public string $sha256;

    /** @var array<string, mixed> */
    public array $metadata;

    /** @var array<string, mixed> */
    public array $provenance;

    /**
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>  $provenance
     */
    public function __construct(
        public SourceAssetId $id,
        public ?SourceId $sourceId,
        public SourceAssetStorageReference $storage,
        public string $originalFilename,
        public string $mimeType,
        public int $byteSize,
        string $sha256,
        public DateTimeImmutable $retrievedAt,
        array $metadata = [],
        array $provenance = [],
        public int $schemaVersion = self::SCHEMA_VERSION,
    ) {
        if ($schemaVersion !== self::SCHEMA_VERSION) {
            throw new InvalidArgumentException('Unsupported SourceAsset schema version.');
        }

        if (trim($originalFilename) === '') {
            throw new InvalidArgumentException('Source asset original filename must not be empty.');
        }

        if (preg_match('/^[A-Za-z0-9!#$&^_.+-]+\/[A-Za-z0-9!#$&^_.+-]+$/D', $mimeType) !== 1) {
            throw new InvalidArgumentException('Source asset MIME type must be valid.');
        }

        if ($byteSize < 1) {
            throw new InvalidArgumentException('Source asset byte size must be at least 1.');
        }

        $normalizedHash = strtolower($sha256);

        if (preg_match('/^[0-9a-f]{64}$/D', $normalizedHash) !== 1) {
            throw new InvalidArgumentException('Source asset SHA-256 must be a 64-character hexadecimal value.');
        }

        self::assertJsonObject($metadata, 'metadata');
        self::assertJsonObject($provenance, 'provenance');

        $this->sha256 = $normalizedHash;
        $this->metadata = $metadata;
        $this->provenance = $provenance;
    }

    public function detached(): self
    {
        return new self(
            id: $this->id,
            sourceId: null,
            storage: $this->storage,
            originalFilename: $this->originalFilename,
            mimeType: $this->mimeType,
            byteSize: $this->byteSize,
            sha256: $this->sha256,
            retrievedAt: $this->retrievedAt,
            metadata: $this->metadata,
            provenance: $this->provenance,
            schemaVersion: $this->schemaVersion,
        );
    }

    /** @param array<string, mixed> $values */
    private static function assertJsonObject(array $values, string $field): void
    {
        foreach ($values as $key => $value) {
            if ($key === '') {
                throw new InvalidArgumentException(sprintf('Source asset %s keys must not be empty.', $field));
            }

            self::assertJsonCompatible($value, $field);
        }
    }

    private static function assertJsonCompatible(mixed $value, string $field): void
    {
        if ($value === null || is_string($value) || is_int($value) || is_float($value) || is_bool($value)) {
            return;
        }

        if (is_array($value)) {
            foreach ($value as $nestedValue) {
                self::assertJsonCompatible($nestedValue, $field);
            }

            return;
        }

        throw new InvalidArgumentException(sprintf(
            'Source asset %s values must be JSON-compatible scalars, arrays or null.',
            $field,
        ));
    }
}
