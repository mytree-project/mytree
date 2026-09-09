<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Acquisition;

use App\Domain\Acquisition\ImageBoundingBoxLocatorValue;
use App\Domain\Acquisition\MediaTimestampLocatorValue;
use App\Domain\Acquisition\PdfPageLocatorValue;
use App\Domain\Acquisition\QuotedFragmentLocatorValue;
use App\Domain\Acquisition\SourceLocatorValueSerializer;
use PHPUnit\Framework\TestCase;

final class SourceLocatorValueSerializationTest extends TestCase
{
    public function test_locator_values_round_trip_canonically(): void
    {
        $values = [
            new PdfPageLocatorValue(12),
            new ImageBoundingBoxLocatorValue(10, 20, 100, 40, 'pixels'),
            new MediaTimestampLocatorValue(1500, 3250),
            new QuotedFragmentLocatorValue('stawił się Jan Kowalski'),
        ];

        foreach ($values as $value) {
            $payload = SourceLocatorValueSerializer::serialize($value);
            $loaded = SourceLocatorValueSerializer::deserialize($payload);

            self::assertSame($value::class, $loaded::class);
            self::assertSame($payload, SourceLocatorValueSerializer::serialize($loaded));
        }
    }
}
