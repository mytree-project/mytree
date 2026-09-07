<?php

declare(strict_types=1);

namespace App\Domain\Acquisition;

use InvalidArgumentException;
use JsonException;

/** @internal */
final class CanonicalJson
{
    private const FLAGS = JSON_THROW_ON_ERROR
        | JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
        | JSON_PRESERVE_ZERO_FRACTION;

    /** @param array<array-key, mixed> $payload */
    public static function encode(array $payload): string
    {
        try {
            return json_encode(self::canonicalize($payload), self::FLAGS);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('Payload cannot be serialized as canonical JSON.', 0, $exception);
        }
    }

    /** @return array<string, mixed> */
    public static function decodeObject(string $payload): array
    {
        try {
            $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('Payload is not valid JSON.', 0, $exception);
        }

        if (! is_array($decoded) || array_is_list($decoded)) {
            throw new InvalidArgumentException('Payload must be a JSON object.');
        }

        $object = [];

        foreach ($decoded as $key => $value) {
            if (! is_string($key)) {
                throw new InvalidArgumentException('Payload object keys must be strings.');
            }

            $object[$key] = $value;
        }

        return $object;
    }

    private static function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            $canonical = [];

            foreach ($value as $nestedValue) {
                $canonical[] = self::canonicalize($nestedValue);
            }

            return $canonical;
        }

        ksort($value, SORT_STRING);

        foreach ($value as $key => $nestedValue) {
            $value[$key] = self::canonicalize($nestedValue);
        }

        return $value;
    }
}
