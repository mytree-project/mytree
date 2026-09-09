<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Acquisition;

use App\Domain\Acquisition\ClaimQualifiers;
use App\Domain\Acquisition\DateClaimValue;
use App\Domain\Acquisition\HistoricalDate;
use PHPUnit\Framework\TestCase;

final class ClaimQualifiersTest extends TestCase
{
    public function test_empty_qualifiers_do_not_imply_an_effective_time(): void
    {
        $qualifiers = ClaimQualifiers::empty();

        self::assertTrue($qualifiers->isEmpty());
        self::assertNull($qualifiers->effectiveTime);
    }

    public function test_typed_effective_time_variants_round_trip_deterministically(): void
    {
        $values = [
            DateClaimValue::exact('1890', HistoricalDate::year(1890)),
            DateClaimValue::approximate('około 1890', HistoricalDate::year(1890)),
            DateClaimValue::uncertain('1890?', HistoricalDate::year(1890)),
            DateClaimValue::range(
                'od 1864 do 1866',
                HistoricalDate::year(1864),
                HistoricalDate::year(1866),
            ),
        ];

        foreach ($values as $effectiveTime) {
            $qualifiers = new ClaimQualifiers(effectiveTime: $effectiveTime);
            $serialized = $qualifiers->serialize();
            $restored = ClaimQualifiers::deserialize($serialized);
            $restoredEffectiveTime = $restored->effectiveTime;

            self::assertSame($serialized, $restored->serialize());
            self::assertNotNull($restoredEffectiveTime);
            self::assertSame($effectiveTime->kind->value, $restoredEffectiveTime->kind->value);
            self::assertSame($effectiveTime->rawValue, $restoredEffectiveTime->rawValue);
            self::assertFalse($restored->isEmpty());
        }
    }
}
