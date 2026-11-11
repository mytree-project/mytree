<?php

declare(strict_types=1);

namespace App\Domain\Acquisition;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Conditional arithmetic: "A completed years at event D" implies
 * D - (A + 1) years < birth <= D - A years.
 *
 * This is always soft historical evidence; the source age itself may be
 * estimated even if recorded as an exact integer.
 */
final readonly class AgeAtEventBirthInterval
{
    public function derive(AgeClaimValue $age, DateClaimValue $eventDate): ?DateClaimValue
    {
        if ($age->unit !== AgeUnit::Years) {
            return null;
        }

        // There is no defined numeric margin for "about"/uncertain ages.
        // Keep the original age evidence; do not manufacture bounds.
        if (! in_array($age->kind, [AgeExpressionKind::Exact, AgeExpressionKind::Range], true)) {
            return null;
        }

        $eventBounds = DateClaimDayBounds::for($eventDate);
        if ($eventBounds === null) {
            return null;
        }

        $maximumAge = $age->to ?? $age->from;
        if ($maximumAge >= $eventBounds[0]->year) {
            return null;
        }

        $earliestBase = self::subtractYears($eventBounds[0], $maximumAge + 1);
        $latestBirth = self::subtractYears($eventBounds[1], $age->from);

        // This covers invalid Gregorian anniversary shifts (especially leap
        // days), year underflow and historical calendars not represented here.
        if ($earliestBase === null || $latestBirth === null) {
            return null;
        }

        $earliestBirth = self::nextDay($earliestBase);
        if ($earliestBirth === null
            || $earliestBirth->lowerBoundOrdinal() > $latestBirth->lowerBoundOrdinal()) {
            return null;
        }

        return DateClaimValue::range(
            rawValue: sprintf(
                'Conditional birth interval from age "%s" at event "%s"',
                $age->raw(),
                $eventDate->raw(),
            ),
            from: $earliestBirth,
            to: $latestBirth,
        );
    }

    private static function subtractYears(HistoricalDate $date, int $years): ?HistoricalDate
    {
        $year = $date->year - $years;

        if ($year < 1 || $year > 9999) {
            return null;
        }

        $month = $date->month;
        $day = $date->day;
        if ($month === null || $day === null || ! checkdate($month, $day, $year)) {
            return null;
        }

        return HistoricalDate::day($year, $month, $day);
    }

    private static function nextDay(HistoricalDate $date): ?HistoricalDate
    {
        $day = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $date->toIsoString(),
            new DateTimeZone('UTC'),
        );
        if ($day === false) {
            return null;
        }

        $next = $day->modify('+1 day');
        $year = (int) $next->format('Y');
        if ($year < 1 || $year > 9999) {
            return null;
        }

        return HistoricalDate::day(
            $year,
            (int) $next->format('m'),
            (int) $next->format('d'),
        );
    }
}
