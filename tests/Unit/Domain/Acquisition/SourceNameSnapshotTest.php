<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Acquisition;

use App\Domain\Acquisition\CanonicalJson;
use App\Domain\Acquisition\Source;
use App\Domain\Acquisition\SourceId;
use App\Domain\Acquisition\SourceMetadata;
use App\Domain\Acquisition\SourceRevisionSnapshot;
use App\Domain\Acquisition\SourceType;
use PHPUnit\Framework\TestCase;

final class SourceNameSnapshotTest extends TestCase
{
    public function test_source_name_is_normalized_and_round_trips_through_current_snapshot(): void
    {
        $source = new Source(
            id: new SourceId('11111111-1111-4111-8111-111111111111'),
            type: SourceType::generic(),
            metadata: SourceMetadata::empty(),
            name: '  Birth certificate Jan Kowalski 1880  ',
        );

        self::assertSame('Birth certificate Jan Kowalski 1880', $source->name);

        $snapshot = SourceRevisionSnapshot::capture($source, []);
        $state = $snapshot->reconstruct();

        self::assertSame(2, $snapshot->schemaVersion);
        self::assertSame('Birth certificate Jan Kowalski 1880', $state->source->name);
    }

    public function test_blank_source_name_is_normalized_to_null(): void
    {
        $source = new Source(
            id: new SourceId('11111111-1111-4111-8111-111111111111'),
            type: SourceType::generic(),
            metadata: SourceMetadata::empty(),
            name: '   ',
        );

        self::assertNull($source->name);
    }

    public function test_legacy_v1_snapshot_rehydrates_without_name(): void
    {
        $payload = CanonicalJson::encode([
            'schema' => 'mytree.source-revision.v1',
            'source' => [
                'id' => '11111111-1111-4111-8111-111111111111',
                'schema_version' => 1,
                'type' => [
                    'key' => 'generic',
                    'schema_version' => 1,
                ],
                'metadata' => [],
                'texts' => [],
                'assets' => [],
            ],
        ]);

        $snapshot = SourceRevisionSnapshot::rehydrate(
            schemaVersion: 1,
            canonicalPayload: $payload,
            payloadHash: hash('sha256', $payload),
        );

        self::assertNull($snapshot->reconstruct()->source->name);
    }
}
