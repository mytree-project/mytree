<?php

declare(strict_types=1);

namespace App\Domain\Acquisition;

use InvalidArgumentException;

final readonly class EnumClaimValue implements ClaimValue
{
    public const SCHEMA_VERSION = 1;

    public function __construct(
        public string $rawValue,
        public string $key,
        public int $valueSchemaVersion = self::SCHEMA_VERSION,
    ) {
        if (trim($rawValue) === '') {
            throw new InvalidArgumentException('Enum Claim value raw representation must not be empty.');
        }

        if (preg_match('/^[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)*$/D', $key) !== 1) {
            throw new InvalidArgumentException('Enum Claim value key must use lowercase dot-separated identifiers.');
        }

        if ($valueSchemaVersion !== self::SCHEMA_VERSION) {
            throw new InvalidArgumentException('Unsupported Enum Claim value schema version.');
        }
    }

    public function type(): ClaimValueType
    {
        return ClaimValueType::Enum;
    }

    public function schemaVersion(): int
    {
        return $this->valueSchemaVersion;
    }

    public function raw(): string
    {
        return $this->rawValue;
    }

    /** @return array<string, mixed> */
    public function data(): array
    {
        return [
            'key' => $this->key,
            'raw' => $this->rawValue,
        ];
    }
}
