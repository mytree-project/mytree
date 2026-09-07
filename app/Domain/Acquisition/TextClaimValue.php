<?php

declare(strict_types=1);

namespace App\Domain\Acquisition;

use InvalidArgumentException;

final readonly class TextClaimValue implements ClaimValue
{
    public const SCHEMA_VERSION = 1;

    public function __construct(
        public string $rawValue,
        public int $valueSchemaVersion = self::SCHEMA_VERSION,
    ) {
        if (trim($rawValue) === '') {
            throw new InvalidArgumentException('Text Claim value must not be empty.');
        }

        if ($valueSchemaVersion !== self::SCHEMA_VERSION) {
            throw new InvalidArgumentException('Unsupported Text Claim value schema version.');
        }
    }

    public function type(): ClaimValueType
    {
        return ClaimValueType::Text;
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
        ];
    }
}
