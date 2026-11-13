<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Acquisition;

use App\Domain\Acquisition\AgeAtEventBirthInterval;
use App\Domain\Acquisition\AgeClaimValue;
use App\Domain\Acquisition\AgeUnit;
use App\Domain\Acquisition\DateClaimDayBounds;
use App\Domain\Acquisition\DateClaimValue;
use App\Domain\Acquisition\HistoricalDate;
use Tests\TestCase;

final class AgeAtEventBirthIntervalTest extends TestCase
{
    public function test_completed_years_at_exact_event_day_give_conditional_birth_interval(): void
    {
        $window = (new AgeAtEventBirthInterval)->derive(
            AgeClaimValue::exact('40 years', 40),
            DateClaimValue::exact('10 May 1863', HistoricalDate::day(1863, 5, 10)),
        );

        self::assertInstanceOf(DateClaimValue::class, $window);
        self::assertSame('range', $window->kind->value);
        self::assertSame('1822-05-11', $window->from->toIsoString());
        self::assertSame('1823-05-10', $window->to?->toIsoString());
    }

    public function test_month_and_year_precision_expand_over_all_possible_event_days(): void
    {
        $calculator = new AgeAtEventBirthInterval;
        $month = $calculator->derive(
            AgeClaimValue::exact('40 years', 40),
            DateClaimValue::exact('May 1863', HistoricalDate::month(1863, 5)),
        );
        $year = $calculator->derive(
            AgeClaimValue::exact('40 years', 40),
            DateClaimValue::exact('1863', HistoricalDate::year(1863)),
        );

        self::assertSame('1822-05-02', $month?->from->toIsoString());
        self::assertSame('1823-05-31', $month->to?->toIsoString());
        self::assertSame('1822-01-02', $year?->from->toIsoString());
        self::assertSame('1823-12-31', $year->to?->toIsoString());
    }

    public function test_explicit_age_and_event_date_ranges_expand_without_arbitrary_tolerance(): void
    {
        $window = (new AgeAtEventBirthInterval)->derive(
            AgeClaimValue::range('aged 40–42', 40, 42),
            DateClaimValue::range(
                'between 10 and 20 May 1863',
                HistoricalDate::day(1863, 5, 10),
                HistoricalDate::day(1863, 5, 20),
            ),
        );

        self::assertSame('1820-05-11', $window?->from->toIsoString());
        self::assertSame('1823-05-20', $window->to?->toIsoString());
    }

    public function test_inexact_age_or_event_is_not_promoted_to_falsely_exact_bounds(): void
    {
        $calc = new AgeAtEventBirthInterval;
        $exactDate = DateClaimValue::exact('10 May 1863', HistoricalDate::day(1863, 5, 10));
        $exactAge = AgeClaimValue::exact('40 years', 40);

        self::assertNull($calc->derive(AgeClaimValue::approximate('about 40', 40), $exactDate));
        self::assertNull($calc->derive(AgeClaimValue::uncertain('possibly 40', 40), $exactDate));
        self::assertNull($calc->derive(AgeClaimValue::exact('40 months', 40, AgeUnit::Months), $exactDate));
        self::assertNull($calc->derive($exactAge, DateClaimValue::approximate('about 1863', HistoricalDate::year(1863))));
        self::assertNull($calc->derive($exactAge, DateClaimValue::uncertain('possibly 1863', HistoricalDate::year(1863))));
    }

    public function test_calendar_edge_cases_do_not_invent_nonexistent_anniversaries(): void
    {
        $calc = new AgeAtEventBirthInterval;

        self::assertNull($calc->derive(
            AgeClaimValue::exact('40 years', 40),
            DateClaimValue::exact('29 February 1864', HistoricalDate::day(1864, 2, 29)),
        ));
        self::assertNull($calc->derive(
            AgeClaimValue::exact('3000 years', 3000),
            DateClaimValue::exact('1 January 1863', HistoricalDate::day(1863, 1, 1)),
        ));

        $ordinary = $calc->derive(
            AgeClaimValue::exact('40 years', 40),
            DateClaimValue::exact('1 March 1864', HistoricalDate::day(1864, 3, 1)),
        );
        self::assertSame('1823-03-02', $ordinary?->from->toIsoString());
        self::assertSame('1824-03-01', $ordinary->to?->toIsoString());
    }

    public function test_direct_birth_compatibility_respects_precision_and_uncertainty(): void
    {
        $candidate = DateClaimValue::range(
            'conditional birth window',
            HistoricalDate::day(1822, 5, 11),
            HistoricalDate::day(1823, 5, 10),
        );

        self::assertFalse(DateClaimDayBounds::areDisjoint(
            DateClaimValue::exact('1823', HistoricalDate::year(1823)),
            $candidate,
        ));
        self::assertFalse(DateClaimDayBounds::areDisjoint(
            DateClaimValue::range('1822–1824', HistoricalDate::year(1822), HistoricalDate::year(1824)),
            $candidate,
        ));
        self::assertTrue(DateClaimDayBounds::areDisjoint(
            DateClaimValue::exact('1820', HistoricalDate::year(1820)),
            $candidate,
        ));
        self::assertNull(DateClaimDayBounds::areDisjoint(
            DateClaimValue::approximate('about 1820', HistoricalDate::year(1820)),
            $candidate,
        ));
    }
}
