<?php

declare(strict_types=1);

namespace App\Domain\Acquisition;

use InvalidArgumentException;

final readonly class DateClaimValue implements ClaimValue
{
    public const SCHEMA_VERSION = 1;

    public function __construct(
        public string $rawValue,
        public DateExpressionKind $kind,
        public HistoricalDate $from,
        public ?HistoricalDate $to = null,
        public int $valueSchemaVersion = self::SCHEMA_VERSION,
    ) {
        if (trim($rawValue) === '') {
            throw new InvalidArgumentException('Date Claim value raw representation must not be empty.');
        }

        if ($kind === DateExpressionKind::Range && $to === null) {
            throw new InvalidArgumentException('Range date Claim value requires an end date.');
        }

        if ($kind !== DateExpressionKind::Range && $to !== null) {
            throw new InvalidArgumentException('Only a range date Claim value may have an end date.');
        }

        if ($to !== null && $from->lowerBoundOrdinal() > $to->lowerBoundOrdinal()) {
            throw new InvalidArgumentException('Range date Claim value start must not be after its end.');
        }

        if ($valueSchemaVersion !== self::SCHEMA_VERSION) {
            throw new InvalidArgumentException('Unsupported Date Claim value schema version.');
        }
    }

    public static function exact(string $rawValue, HistoricalDate $date): self
    {
        return new self($rawValue, DateExpressionKind::Exact, $date);
    }

    public static function approximate(string $rawValue, HistoricalDate $date): self
    {
        return new self($rawValue, DateExpressionKind::Approximate, $date);
    }

    public static function uncertain(string $rawValue, HistoricalDate $date): self
    {
        return new self($rawValue, DateExpressionKind::Uncertain, $date);
    }

    public static function range(string $rawValue, HistoricalDate $from, HistoricalDate $to): self
    {
        return new self($rawValue, DateExpressionKind::Range, $from, $to);
    }

    public function type(): ClaimValueType
    {
        return ClaimValueType::Date;
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
            'from' => $this->from->toIsoString(),
            'kind' => $this->kind->value,
            'raw' => $this->rawValue,
            'to' => $this->to?->toIsoString(),
        ];
    }
}
