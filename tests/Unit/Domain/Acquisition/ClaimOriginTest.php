<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Acquisition;

use App\Domain\Acquisition\ClaimOrigin;
use App\Domain\Acquisition\ClaimOriginKind;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ClaimOriginTest extends TestCase
{
    #[Test]
    public function empty_object_fields_round_trip_without_losing_default_origin(): void
    {
        $origin = ClaimOrigin::manualDirectSource();

        $loaded = ClaimOrigin::deserialize($origin->serialize());

        self::assertSame(ClaimOriginKind::ManualDirectSource, $loaded->kind);
        self::assertSame([], $loaded->requestContext);
        self::assertSame([], $loaded->metadata);
    }

    #[Test]
    public function provider_provenance_maps_round_trip_with_nested_values(): void
    {
        $origin = new ClaimOrigin(
            kind: ClaimOriginKind::ProviderObservation,
            observationId: 'import-17',
            providerKey: 'provider-a',
            providerRecordId: 'record-99',
            sourceUrl: 'https://example.test/index',
            requestContext: ['query' => ['surname' => 'Kowalski']],
            parserVersion: '1.2.3',
            providerVersion: '2026-09',
            metadata: ['confidence' => 0.91],
        );

        $loaded = ClaimOrigin::deserialize($origin->serialize());

        self::assertSame($origin->toArray(), $loaded->toArray());
    }
}
