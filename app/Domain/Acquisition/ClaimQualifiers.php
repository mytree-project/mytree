<?php

declare(strict_types=1);

namespace App\Domain\Acquisition;

use InvalidArgumentException;

final readonly class ClaimQualifiers
{
    public const SCHEMA_ID = 'mytree.claim-qualifiers.v1';

    public const SCHEMA_VERSION = 1;

    public function __construct(
        public ?DateClaimValue $effectiveTime = null,
        public int $schemaVersion = self::SCHEMA_VERSION,
    ) {
        if ($schemaVersion !== self::SCHEMA_VERSION) {
            throw new InvalidArgumentException('Unsupported Claim qualifiers schema version.');
        }
    }

    public static function empty(): self
    {
        return new self;
    }

    public function isEmpty(): bool
    {
        return $this->effectiveTime === null;
    }

    public function serialize(): string
    {
        return CanonicalJson::encode($this->toArray());
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'effective_time' => $this->effectiveTime === null
                ? null
                : ClaimValueSerializer::toArray($this->effectiveTime),
            'schema' => self::SCHEMA_ID,
            'schema_version' => $this->schemaVersion,
        ];
    }

    public static function deserialize(string $payload): self
    {
        $decoded = CanonicalJson::decodeObject($payload);

        if (CanonicalJson::encode($decoded) !== $payload) {
            throw new InvalidArgumentException('Stored Claim qualifiers payload is not in canonical form.');
        }

        if (($decoded['schema'] ?? null) !== self::SCHEMA_ID) {
            throw new InvalidArgumentException('Unsupported Claim qualifiers schema identifier.');
        }

        if (($decoded['schema_version'] ?? null) !== self::SCHEMA_VERSION) {
            throw new InvalidArgumentException('Unsupported Claim qualifiers schema version.');
        }

        return new self(
            effectiveTime: self::effectiveTime($decoded['effective_time'] ?? null),
            schemaVersion: self::SCHEMA_VERSION,
        );
    }

    private static function effectiveTime(mixed $value): ?DateClaimValue
    {
        if ($value === null) {
            return null;
        }

        if (! is_array($value) || array_is_list($value)) {
            throw new InvalidArgumentException('Claim qualifiers effective_time must be an object.');
        }

        $payload = [];

        foreach ($value as $key => $nestedValue) {
            if (! is_string($key)) {
                throw new InvalidArgumentException('Claim qualifiers effective_time object keys must be strings.');
            }

            $payload[$key] = $nestedValue;
        }

        $claimValue = ClaimValueSerializer::fromArray($payload);

        if (! $claimValue instanceof DateClaimValue) {
            throw new InvalidArgumentException('Claim qualifiers effective_time must be a Date Claim value.');
        }

        return $claimValue;
    }
}
