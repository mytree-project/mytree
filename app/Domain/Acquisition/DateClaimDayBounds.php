<?php

declare(strict_types=1);

namespace App\Domain\Acquisition;

/**
 * A Date Claim denotes a possible instant, not an event lasting for this span.
 * Only exact and explicitly bounded dates have enforceable ordinal bounds.
 */
final readonly class DateClaimDayBounds
{
    /**
     * @return array{HistoricalDate, HistoricalDate}|null
     */
    public static function for(DateClaimValue $value): ?array
    {
        if (! in_array($value->kind, [DateExpressionKind::Exact, DateExpressionKind::Range], true)) {
            return null;
        }

        $end = $value->to ?? $value->from;

        return [self::earliest($value->from), self::latest($end)];
    }

    public static function earliest(HistoricalDate $date): HistoricalDate
    {
        return HistoricalDate::day($date->year, $date->month ?? 1, $date->day ?? 1);
    }

    public static function latest(HistoricalDate $date): HistoricalDate
    {
        $month = $date->month ?? 12;

        $day = $date->day;
        if ($day === null) {
            $day = 31;
            while (! checkdate($month, $day, $date->year)) {
                $day--;
            }
        }

        return HistoricalDate::day($date->year, $month, $day);
    }

    /**
     * null means that the dates do not permit a defensible comparison.
     */
    public static function areDisjoint(DateClaimValue $left, DateClaimValue $right): ?bool
    {
        $a = self::for($left);
        $b = self::for($right);

        if ($a === null || $b === null) {
            return null;
        }

        return $a[1]->lowerBoundOrdinal() < $b[0]->lowerBoundOrdinal()
            || $b[1]->lowerBoundOrdinal() < $a[0]->lowerBoundOrdinal();
    }
}
