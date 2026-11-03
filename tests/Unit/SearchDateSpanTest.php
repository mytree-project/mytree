<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Application\Search\SearchDateSpan;
use App\Domain\Acquisition\HistoricalDate;
use PHPUnit\Framework\TestCase;

final class SearchDateSpanTest extends TestCase
{
    public function test_year_and_month_precision_includes_the_last_calendar_day(): void
    {
        $leapFebruary = SearchDateSpan::forDate(HistoricalDate::month(1904, 2));
        self::assertSame(19040201, $leapFebruary->from);
        self::assertSame(19040229, $leapFebruary->to);

        $year = SearchDateSpan::forDate(HistoricalDate::year(1901));
        self::assertSame(19010101, $year->from);
        self::assertSame(19011231, $year->to);
        self::assertTrue($leapFebruary->overlaps(SearchDateSpan::between(
            HistoricalDate::day(1904, 2, 29), HistoricalDate::day(1904, 2, 29),
        )));
    }

    public function test_missing_filter_bound_means_open_interval(): void
    {
        self::assertTrue(SearchDateSpan::between(null, HistoricalDate::year(1900))
            ->overlaps(SearchDateSpan::forDate(HistoricalDate::year(1899))));
        self::assertFalse(SearchDateSpan::between(HistoricalDate::year(1901), null)
            ->overlaps(SearchDateSpan::forDate(HistoricalDate::year(1900))));
    }
}
