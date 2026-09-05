<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Acquisition;

use App\Domain\Acquisition\AgeClaimValue;
use App\Domain\Acquisition\AgeUnit;
use App\Domain\Acquisition\BooleanClaimValue;
use App\Domain\Acquisition\ClaimValue;
use App\Domain\Acquisition\ClaimValueSerializer;
use App\Domain\Acquisition\DateClaimValue;
use App\Domain\Acquisition\EnumClaimValue;
use App\Domain\Acquisition\HistoricalDate;
use App\Domain\Acquisition\IntegerClaimValue;
use App\Domain\Acquisition\TextClaimValue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ClaimValueSerializationTest extends TestCase
{
    /** @return iterable<string, array{ClaimValue}> */
    public static function values(): iterable
    {
        yield 'text with source Cyrillic' => [new TextClaimValue('однодворец')];
        yield 'integer with distinct raw form' => [new IntegerClaimValue('005', 5)];
        yield 'boolean' => [new BooleanClaimValue('tak', true)];
        yield 'enum' => [new EnumClaimValue('praca', 'employment')];
        yield 'exact date' => [DateClaimValue::exact('12 lutego 1864', HistoricalDate::day(1864, 2, 12))];
        yield 'approximate date' => [DateClaimValue::approximate('około 1864', HistoricalDate::year(1864))];
        yield 'uncertain date' => [DateClaimValue::uncertain('1864?', HistoricalDate::year(1864))];
        yield 'date range' => [DateClaimValue::range('1864-1866', HistoricalDate::year(1864), HistoricalDate::year(1866))];
        yield 'exact age' => [AgeClaimValue::exact('20 lat', 20)];
        yield 'uncertain age' => [AgeClaimValue::uncertain('20?', 20)];
        yield 'age range' => [AgeClaimValue::range('20-22 lata', 20, 22)];
        yield 'age in months' => [AgeClaimValue::approximate('około 6 miesięcy', 6, AgeUnit::Months)];
    }

    #[DataProvider('values')]
    public function test_typed_values_round_trip_through_canonical_versioned_serialization(ClaimValue $value): void
    {
        $serialized = ClaimValueSerializer::serialize($value);
        $restored = ClaimValueSerializer::deserialize($serialized);

        self::assertSame($serialized, ClaimValueSerializer::serialize($restored));
        self::assertSame($value->type(), $restored->type());
        self::assertSame($value->raw(), $restored->raw());
        self::assertStringContainsString('"schema":"mytree.claim-value.v1"', $serialized);
        self::assertStringContainsString('"type_schema_version":1', $serialized);
    }

    public function test_partial_historical_dates_do_not_invent_precision(): void
    {
        $year = HistoricalDate::fromIsoString('1864');
        $month = HistoricalDate::fromIsoString('1864-02');
        $day = HistoricalDate::fromIsoString('1864-02-12');

        self::assertSame('1864', $year->toIsoString());
        self::assertSame('1864-02', $month->toIsoString());
        self::assertSame('1864-02-12', $day->toIsoString());
    }

    public function test_age_value_preserves_uncertainty_without_deriving_birth_date(): void
    {
        $age = AgeClaimValue::uncertain('20?', 20);

        self::assertSame('20?', $age->raw());
        self::assertSame([
            'from' => 20,
            'kind' => 'uncertain',
            'raw' => '20?',
            'to' => null,
            'unit' => 'years',
        ], $age->data());
    }
}
