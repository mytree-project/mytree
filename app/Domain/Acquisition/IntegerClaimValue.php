<?php

declare(strict_types=1);

namespace App\Domain\Acquisition;

use InvalidArgumentException;

final readonly class IntegerClaimValue implements ClaimValue
{
    public const SCHEMA_VERSION = 1;

    public function __construct(
        public string $rawValue,
        public int $value,
        public int $valueSchemaVersion = self::SCHEMA_VERSION,
    ) {
        if (trim($rawValue) === '') {
            throw new InvalidArgumentException('Integer Claim value raw representation must not be empty.');
        }

        if ($valueSchemaVersion !== self::SCHEMA_VERSION) {
            throw new InvalidArgumentException('Unsupported Integer Claim value schema version.');
        }
    }

    public function type(): ClaimValueType
    {
        return ClaimValueType::Integer;
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
            'raw' => $this->rawValue,
            'value' => $this->value,
        ];
    }
}
