<?php

declare(strict_types=1);

namespace App\Application\Search;

use App\Domain\Acquisition\HistoricalDate;
use InvalidArgumentException;

final readonly class SearchDateSpan
{
    public function __construct(
        public int $from,
        public int $to,
    ) {
        if ($from < 10101 || $to > 99991231 || $from > $to) {
            throw new InvalidArgumentException('Invalid Search date interval.');
        }
    }

    public static function forDate(HistoricalDate $date): self
    {
        $month = $date->month ?? 1;
        $day = $date->day ?? 1;
        $lastMonth = $date->month ?? 12;
        $lastDay = $date->day ?? self::lastDay($date->year, $lastMonth);

        return new self(
            $date->lowerBoundOrdinal(),
            $date->year * 10000 + $lastMonth * 100 + $lastDay,
        );
    }

    public static function between(?HistoricalDate $from, ?HistoricalDate $to): self
    {
        $start = $from === null ? 10101 : self::forDate($from)->from;
        $end = $to === null ? 99991231 : self::forDate($to)->to;

        return new self($start, $end);
    }

    public function overlaps(self $other): bool
    {
        return $this->from <= $other->to && $this->to >= $other->from;
    }

    private static function lastDay(int $year, int $month): int
    {
        for ($day = 31; $day >= 28; $day--) {
            if (checkdate($month, $day, $year)) {
                return $day;
            }
        }

        throw new InvalidArgumentException('Invalid Search month.');
    }
}
