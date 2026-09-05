<?php

declare(strict_types=1);

namespace App\Domain\Acquisition;

use InvalidArgumentException;

final class ClaimValueSerializer
{
    public const SCHEMA_ID = 'mytree.claim-value.v1';

    public const SCHEMA_VERSION = 1;

    public static function serialize(ClaimValue $value): string
    {
        return CanonicalJson::encode(self::toArray($value));
    }

    /** @return array<string, mixed> */
    public static function toArray(ClaimValue $value): array
    {
        return [
            'data' => $value->data(),
            'schema' => self::SCHEMA_ID,
            'schema_version' => self::SCHEMA_VERSION,
            'type' => $value->type()->value,
            'type_schema_version' => $value->schemaVersion(),
        ];
    }

    public static function deserialize(string $payload): ClaimValue
    {
        $decoded = CanonicalJson::decodeObject($payload);

        if (CanonicalJson::encode($decoded) !== $payload) {
            throw new InvalidArgumentException('Stored Claim value payload is not in canonical form.');
        }

        return self::fromArray($decoded);
    }

    /** @param array<string, mixed> $payload */
    public static function fromArray(array $payload): ClaimValue
    {
        if (($payload['schema'] ?? null) !== self::SCHEMA_ID) {
            throw new InvalidArgumentException('Unsupported Claim value schema identifier.');
        }

        if (($payload['schema_version'] ?? null) !== self::SCHEMA_VERSION) {
            throw new InvalidArgumentException('Unsupported Claim value schema version.');
        }

        $type = self::valueType($payload['type'] ?? null);
        $typeSchemaVersion = self::integer($payload['type_schema_version'] ?? null, 'type_schema_version');
        $data = self::object($payload['data'] ?? null, 'data');

        return match ($type) {
            ClaimValueType::Text => new TextClaimValue(
                rawValue: self::string($data['raw'] ?? null, 'data.raw'),
                valueSchemaVersion: $typeSchemaVersion,
            ),
            ClaimValueType::Integer => new IntegerClaimValue(
                rawValue: self::string($data['raw'] ?? null, 'data.raw'),
                value: self::integer($data['value'] ?? null, 'data.value'),
                valueSchemaVersion: $typeSchemaVersion,
            ),
            ClaimValueType::Boolean => new BooleanClaimValue(
                rawValue: self::string($data['raw'] ?? null, 'data.raw'),
                value: self::boolean($data['value'] ?? null, 'data.value'),
                valueSchemaVersion: $typeSchemaVersion,
            ),
            ClaimValueType::Enum => new EnumClaimValue(
                rawValue: self::string($data['raw'] ?? null, 'data.raw'),
                key: self::string($data['key'] ?? null, 'data.key'),
                valueSchemaVersion: $typeSchemaVersion,
            ),
            ClaimValueType::Date => self::dateValue($data, $typeSchemaVersion),
            ClaimValueType::Age => self::ageValue($data, $typeSchemaVersion),
        };
    }

    /** @param array<string, mixed> $data */
    private static function dateValue(array $data, int $typeSchemaVersion): DateClaimValue
    {
        $kind = DateExpressionKind::tryFrom(self::string($data['kind'] ?? null, 'data.kind'));

        if ($kind === null) {
            throw new InvalidArgumentException('Unsupported Date Claim value kind.');
        }

        $to = self::nullableString($data['to'] ?? null, 'data.to');

        return new DateClaimValue(
            rawValue: self::string($data['raw'] ?? null, 'data.raw'),
            kind: $kind,
            from: HistoricalDate::fromIsoString(self::string($data['from'] ?? null, 'data.from')),
            to: $to === null ? null : HistoricalDate::fromIsoString($to),
            valueSchemaVersion: $typeSchemaVersion,
        );
    }

    /** @param array<string, mixed> $data */
    private static function ageValue(array $data, int $typeSchemaVersion): AgeClaimValue
    {
        $kind = AgeExpressionKind::tryFrom(self::string($data['kind'] ?? null, 'data.kind'));
        $unit = AgeUnit::tryFrom(self::string($data['unit'] ?? null, 'data.unit'));

        if ($kind === null) {
            throw new InvalidArgumentException('Unsupported Age Claim value kind.');
        }

        if ($unit === null) {
            throw new InvalidArgumentException('Unsupported Age Claim value unit.');
        }

        return new AgeClaimValue(
            rawValue: self::string($data['raw'] ?? null, 'data.raw'),
            kind: $kind,
            unit: $unit,
            from: self::integer($data['from'] ?? null, 'data.from'),
            to: self::nullableInteger($data['to'] ?? null, 'data.to'),
            valueSchemaVersion: $typeSchemaVersion,
        );
    }

    private static function valueType(mixed $value): ClaimValueType
    {
        $type = is_string($value) ? ClaimValueType::tryFrom($value) : null;

        if ($type === null) {
            throw new InvalidArgumentException('Unsupported Claim value type.');
        }

        return $type;
    }

    /** @return array<string, mixed> */
    private static function object(mixed $value, string $path): array
    {
        if (! is_array($value) || array_is_list($value)) {
            throw new InvalidArgumentException(sprintf('Claim value %s must be an object.', $path));
        }

        foreach (array_keys($value) as $key) {
            if (! is_string($key)) {
                throw new InvalidArgumentException(sprintf('Claim value %s object keys must be strings.', $path));
            }
        }

        /** @var array<string, mixed> $value */
        return $value;
    }

    private static function string(mixed $value, string $path): string
    {
        if (! is_string($value)) {
            throw new InvalidArgumentException(sprintf('Claim value %s must be a string.', $path));
        }

        return $value;
    }

    private static function nullableString(mixed $value, string $path): ?string
    {
        if ($value === null) {
            return null;
        }

        return self::string($value, $path);
    }

    private static function integer(mixed $value, string $path): int
    {
        if (! is_int($value)) {
            throw new InvalidArgumentException(sprintf('Claim value %s must be an integer.', $path));
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

    private static function boolean(mixed $value, string $path): bool
    {
        if (! is_bool($value)) {
            throw new InvalidArgumentException(sprintf('Claim value %s must be a boolean.', $path));
        }

        return $value;
    }
}
