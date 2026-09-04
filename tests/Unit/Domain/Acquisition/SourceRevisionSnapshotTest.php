<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Acquisition;

use App\Domain\Acquisition\Source;
use App\Domain\Acquisition\SourceAsset;
use App\Domain\Acquisition\SourceAssetId;
use App\Domain\Acquisition\SourceAssetStorageReference;
use App\Domain\Acquisition\SourceId;
use App\Domain\Acquisition\SourceMetadata;
use App\Domain\Acquisition\SourceRevisionSnapshot;
use App\Domain\Acquisition\SourceText;
use App\Domain\Acquisition\SourceTextId;
use App\Domain\Acquisition\SourceTextKind;
use App\Domain\Acquisition\SourceType;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class SourceRevisionSnapshotTest extends TestCase
{
    public function test_equivalent_semantic_states_have_identical_payload_and_hash(): void
    {
        $sourceId = new SourceId('11111111-1111-4111-8111-111111111111');
        $sourceA = new Source(
            id: $sourceId,
            type: new SourceType('civil.birth', 2),
            metadata: new SourceMetadata([
                'raw_act_number' => '7?',
                'archive' => ['year' => 1899, 'name' => 'Archive'],
            ]),
            texts: [
                new SourceText(
                    id: new SourceTextId('22222222-2222-4222-8222-222222222222'),
                    kind: SourceTextKind::Transcription,
                    content: 'Иосиф Гайда',
                    language: 'ru',
                ),
            ],
        );
        $sourceB = new Source(
            id: $sourceId,
            type: new SourceType('civil.birth', 2),
            metadata: new SourceMetadata([
                'archive' => ['name' => 'Archive', 'year' => 1899],
                'raw_act_number' => '7?',
            ]),
            texts: $sourceA->texts,
        );

        $assetA = $this->asset(
            sourceId: $sourceId,
            id: '33333333-3333-4333-8333-333333333333',
            retrievedAt: new DateTimeImmutable('2026-09-04T14:00:00+02:00'),
            metadata: ['quality' => 'high', 'dimensions' => ['height' => 2000, 'width' => 1500]],
            provenance: ['origin_url' => 'https://example.test/a', 'provider' => 'archive_a'],
        );
        $assetB = $this->asset(
            sourceId: $sourceId,
            id: '44444444-4444-4444-8444-444444444444',
            retrievedAt: new DateTimeImmutable('2026-09-04T12:00:00+00:00'),
            metadata: [],
            provenance: ['provider' => 'archive_b'],
        );
        $equivalentAssetA = $this->asset(
            sourceId: $sourceId,
            id: '33333333-3333-4333-8333-333333333333',
            retrievedAt: new DateTimeImmutable('2026-09-04T12:00:00+00:00'),
            metadata: ['dimensions' => ['width' => 1500, 'height' => 2000], 'quality' => 'high'],
            provenance: ['provider' => 'archive_a', 'origin_url' => 'https://example.test/a'],
        );

        $snapshotA = SourceRevisionSnapshot::capture($sourceA, [$assetB, $assetA]);
        $snapshotB = SourceRevisionSnapshot::capture($sourceB, [$equivalentAssetA, $assetB]);

        self::assertSame($snapshotA->canonicalPayload, $snapshotB->canonicalPayload);
        self::assertSame($snapshotA->payloadHash, $snapshotB->payloadHash);
    }

    public function test_snapshot_reconstructs_source_text_and_asset_without_current_state(): void
    {
        $sourceId = new SourceId('11111111-1111-4111-8111-111111111111');
        $source = new Source(
            id: $sourceId,
            type: new SourceType('civil.birth'),
            metadata: new SourceMetadata(['raw_act_number' => '7?']),
            texts: [
                new SourceText(
                    id: new SourceTextId('22222222-2222-4222-8222-222222222222'),
                    kind: SourceTextKind::Translation,
                    content: 'Józef Gajda',
                    language: 'pl',
                ),
            ],
        );
        $asset = $this->asset(
            sourceId: $sourceId,
            id: '33333333-3333-4333-8333-333333333333',
            retrievedAt: new DateTimeImmutable('2026-09-04T12:00:00+00:00'),
            metadata: [],
            provenance: ['provider' => 'archive_a'],
        );

        $state = SourceRevisionSnapshot::capture($source, [$asset])->reconstruct();

        self::assertSame('civil.birth', $state->source->type->key);
        self::assertSame('7?', $state->source->metadata->toArray()['raw_act_number']);
        self::assertSame('Józef Gajda', $state->source->texts[0]->content);
        self::assertCount(1, $state->assets);
        self::assertSame($asset->id->value, $state->assets[0]->id->value);
        self::assertSame('archive_a', $state->assets[0]->provenance['provider']);
        self::assertSame($asset->storage->path, $state->assets[0]->storage->path);
    }

    public function test_incompatible_snapshot_schema_version_is_rejected_explicitly(): void
    {
        $source = new Source(
            id: new SourceId('11111111-1111-4111-8111-111111111111'),
            type: SourceType::generic(),
            metadata: SourceMetadata::empty(),
        );
        $snapshot = SourceRevisionSnapshot::capture($source, []);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported SourceRevision snapshot schema version 2.');

        SourceRevisionSnapshot::rehydrate(
            schemaVersion: 2,
            canonicalPayload: $snapshot->canonicalPayload,
            payloadHash: $snapshot->payloadHash,
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>  $provenance
     */
    private function asset(
        SourceId $sourceId,
        string $id,
        DateTimeImmutable $retrievedAt,
        array $metadata,
        array $provenance,
    ): SourceAsset {
        return new SourceAsset(
            id: new SourceAssetId($id),
            sourceId: $sourceId,
            storage: new SourceAssetStorageReference('local', sprintf('sources/%s/%s.jpg', $sourceId->value, $id)),
            originalFilename: 'record.jpg',
            mimeType: 'image/jpeg',
            byteSize: 123,
            sha256: str_repeat('a', 64),
            retrievedAt: $retrievedAt,
            metadata: $metadata,
            provenance: $provenance,
        );
    }
}
