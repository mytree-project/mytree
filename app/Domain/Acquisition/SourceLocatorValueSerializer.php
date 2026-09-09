<?php

declare(strict_types=1);

namespace App\Domain\Acquisition;

use InvalidArgumentException;

final class SourceLocatorValueSerializer
{
    public const SCHEMA_ID = 'mytree.source-locator-value.v1';

    public const SCHEMA_VERSION = 1;

    public static function serialize(SourceLocatorValue $value): string
    {
        return CanonicalJson::encode([
            'data' => $value->data(),
            'schema' => self::SCHEMA_ID,
            'schema_version' => self::SCHEMA_VERSION,
            'type' => $value->type()->value,
            'type_schema_version' => $value->schemaVersion(),
        ]);
    }

    public static function deserialize(string $payload): SourceLocatorValue
    {
        $decoded = CanonicalJson::decodeObject($payload);

        if (CanonicalJson::encode($decoded) !== $payload) {
            throw new InvalidArgumentException('Stored Source locator value payload is not in canonical form.');
        }

        if (($decoded['schema'] ?? null) !== self::SCHEMA_ID || ($decoded['schema_version'] ?? null) !== self::SCHEMA_VERSION) {
            throw new InvalidArgumentException('Unsupported Source locator value schema.');
        }

        $type = is_string($decoded['type'] ?? null) ? SourceLocatorType::tryFrom($decoded['type']) : null;
        $version = $decoded['type_schema_version'] ?? null;
        $data = $decoded['data'] ?? null;

        if ($type === null || ! is_int($version) || ! is_array($data) || array_is_list($data)) {
            throw new InvalidArgumentException('Invalid Source locator value payload.');
        }

        return match ($type) {
            SourceLocatorType::PdfPage => new PdfPageLocatorValue(
                page: self::integer($data['page'] ?? null, 'data.page'),
                valueSchemaVersion: $version,
            ),
            SourceLocatorType::ImageBoundingBox => new ImageBoundingBoxLocatorValue(
                x: self::number($data['x'] ?? null, 'data.x'),
                y: self::number($data['y'] ?? null, 'data.y'),
                width: self::number($data['width'] ?? null, 'data.width'),
                height: self::number($data['height'] ?? null, 'data.height'),
                coordinateSpace: self::string($data['coordinate_space'] ?? null, 'data.coordinate_space'),
                valueSchemaVersion: $version,
            ),
            SourceLocatorType::MediaTimestamp => new MediaTimestampLocatorValue(
                startMilliseconds: self::integer($data['start_milliseconds'] ?? null, 'data.start_milliseconds'),
                endMilliseconds: self::nullableInteger($data['end_milliseconds'] ?? null, 'data.end_milliseconds'),
                valueSchemaVersion: $version,
            ),
            SourceLocatorType::QuotedFragment => new QuotedFragmentLocatorValue(
                text: self::string($data['text'] ?? null, 'data.text'),
                valueSchemaVersion: $version,
            ),
        };
    }

    private static function string(mixed $value, string $path): string
    {
        if (! is_string($value)) {
            throw new InvalidArgumentException(sprintf('Source locator value %s must be a string.', $path));
        }

        return $value;
    }

    private static function integer(mixed $value, string $path): int
    {
        if (! is_int($value)) {
            throw new InvalidArgumentException(sprintf('Source locator value %s must be an integer.', $path));
        }

        return $value;
    }

    private static function nullableInteger(mixed $value, string $path): ?int
    {
        if ($value === null) {
            return null;
        }

        return self::integer($value, $path);
    }

    private static function number(mixed $value, string $path): float
    {
        if (! is_int($value) && ! is_float($value)) {
            throw new InvalidArgumentException(sprintf('Source locator value %s must be numeric.', $path));
        }

        return (float) $value;
    }
}
