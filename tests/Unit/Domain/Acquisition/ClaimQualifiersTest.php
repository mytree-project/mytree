<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Acquisition;

use App\Domain\Acquisition\ClaimQualifiers;
use App\Domain\Acquisition\DateClaimValue;
use App\Domain\Acquisition\HistoricalDate;
use App\Domain\Acquisition\MentionId;
use PHPUnit\Framework\TestCase;

final class ClaimQualifiersTest extends TestCase
{
    public function test_empty_qualifiers_do_not_imply_an_effective_time_or_place(): void
    {
        $qualifiers = ClaimQualifiers::empty();

        self::assertTrue($qualifiers->isEmpty());
        self::assertNull($qualifiers->effectiveTime);
        self::assertNull($qualifiers->placeMentionId);
        self::assertNull($qualifiers->workplaceMentionId);
    }

    public function test_effective_period_and_contextual_places_round_trip_deterministically(): void
    {
        $qualifiers = new ClaimQualifiers(
            effectiveTime: DateClaimValue::range(
                'od 1864 do 1866',
                HistoricalDate::year(1864),
                HistoricalDate::year(1866),
            ),
            placeMentionId: new MentionId('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa'),
            workplaceMentionId: new MentionId('bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb'),
        );

        $serialized = $qualifiers->serialize();
        $restored = ClaimQualifiers::deserialize($serialized);

        self::assertSame($serialized, $restored->serialize());
        self::assertSame('range', $restored->effectiveTime?->kind->value);
        self::assertSame('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', $restored->placeMentionId?->value);
        self::assertSame('bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb', $restored->workplaceMentionId?->value);
        self::assertFalse($restored->isEmpty());
    }
}
