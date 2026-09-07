<?php

declare(strict_types=1);

namespace App\Domain\Acquisition;

use InvalidArgumentException;

final readonly class AgeClaimValue implements ClaimValue
{
    public const SCHEMA_VERSION = 1;

    public function __construct(
        public string $rawValue,
        public AgeExpressionKind $kind,
        public AgeUnit $unit,
        public int $from,
        public ?int $to = null,
        public int $valueSchemaVersion = self::SCHEMA_VERSION,
    ) {
        if (trim($rawValue) === '') {
            throw new InvalidArgumentException('Age Claim value raw representation must not be empty.');
        }

        if ($from < 0) {
            throw new InvalidArgumentException('Age Claim value must not be negative.');
        }

        if ($kind === AgeExpressionKind::Range && $to === null) {
            throw new InvalidArgumentException('Range age Claim value requires an upper bound.');
        }

        if ($kind !== AgeExpressionKind::Range && $to !== null) {
            throw new InvalidArgumentException('Only a range age Claim value may have an upper bound.');
        }

        if ($to !== null && $to < $from) {
            throw new InvalidArgumentException('Range age Claim value upper bound must not be below its lower bound.');
        }

        if ($valueSchemaVersion !== self::SCHEMA_VERSION) {
            throw new InvalidArgumentException('Unsupported Age Claim value schema version.');
        }
    }

    public static function exact(string $rawValue, int $value, AgeUnit $unit = AgeUnit::Years): self
    {
        return new self($rawValue, AgeExpressionKind::Exact, $unit, $value);
    }

    public static function approximate(string $rawValue, int $value, AgeUnit $unit = AgeUnit::Years): self
    {
        return new self($rawValue, AgeExpressionKind::Approximate, $unit, $value);
    }

    public static function uncertain(string $rawValue, int $value, AgeUnit $unit = AgeUnit::Years): self
    {
        return new self($rawValue, AgeExpressionKind::Uncertain, $unit, $value);
    }

    public static function range(
        string $rawValue,
        int $from,
        int $to,
        AgeUnit $unit = AgeUnit::Years,
    ): self {
        return new self($rawValue, AgeExpressionKind::Range, $unit, $from, $to);
    }

    public function type(): ClaimValueType
    {
        return ClaimValueType::Age;
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
            'from' => $this->from,
            'kind' => $this->kind->value,
            'raw' => $this->rawValue,
            'to' => $this->to,
            'unit' => $this->unit->value,
        ];
    }
}
