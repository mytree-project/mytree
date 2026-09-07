<?php

declare(strict_types=1);

namespace App\Domain\Acquisition;

use InvalidArgumentException;

final readonly class HistoricalDate
{
    public function __construct(
        public int $year,
        public ?int $month = null,
        public ?int $day = null,
    ) {
        if ($year < 1 || $year > 9999) {
            throw new InvalidArgumentException('Historical date year must be between 1 and 9999.');
        }

        if ($month !== null && ($month < 1 || $month > 12)) {
            throw new InvalidArgumentException('Historical date month must be between 1 and 12.');
        }

        if ($day !== null) {
            if ($month === null) {
                throw new InvalidArgumentException('Historical date day requires a month.');
            }

            if (! checkdate($month, $day, $year)) {
                throw new InvalidArgumentException('Historical date day is not valid for the supplied month and year.');
            }
        }
    }

    public static function year(int $year): self
    {
        return new self($year);
    }

    public static function month(int $year, int $month): self
    {
        return new self($year, $month);
    }

    public static function day(int $year, int $month, int $day): self
    {
        return new self($year, $month, $day);
    }

    public static function fromIsoString(string $value): self
    {
        if (preg_match('/^(?<year>\d{4})(?:-(?<month>\d{2})(?:-(?<day>\d{2}))?)?$/D', $value, $matches) !== 1) {
            throw new InvalidArgumentException('Historical date must use YYYY, YYYY-MM or YYYY-MM-DD.');
        }

        $month = isset($matches['month']) ? (int) $matches['month'] : null;
        $day = isset($matches['day']) ? (int) $matches['day'] : null;

        return new self(
            year: (int) $matches['year'],
            month: $month,
            day: $day,
        );
    }

    public function precision(): DatePrecision
    {
        if ($this->day !== null) {
            return DatePrecision::Day;
        }

        if ($this->month !== null) {
            return DatePrecision::Month;
        }

        return DatePrecision::Year;
    }

    public function toIsoString(): string
    {
        if ($this->day !== null) {
            return sprintf('%04d-%02d-%02d', $this->year, $this->month, $this->day);
        }

        if ($this->month !== null) {
            return sprintf('%04d-%02d', $this->year, $this->month);
        }

        return sprintf('%04d', $this->year);
    }

    public function lowerBoundOrdinal(): int
    {
        return ($this->year * 10000) + (($this->month ?? 1) * 100) + ($this->day ?? 1);
    }
}
