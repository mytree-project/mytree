<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Acquisition\CreateSource;
use App\Application\Acquisition\DetachSourceAsset;
use App\Application\Acquisition\ReconstructSourceRevision;
use App\Application\Acquisition\SourceRevisionRepository;
use App\Application\Acquisition\SourceTextInput;
use App\Application\Acquisition\StoreSourceAsset;
use App\Application\Acquisition\StoreSourceAssetInput;
use App\Application\Acquisition\UpdateSource;
use App\Domain\Acquisition\SourceMetadata;
use App\Domain\Acquisition\SourceRevision;
use App\Domain\Acquisition\SourceRevisionSnapshot;
use App\Domain\Acquisition\SourceTextKind;
use App\Domain\Acquisition\SourceType;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class SourceRevisionApplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_source_mutations_create_reconstructable_revisions_and_detach_keeps_historical_asset(): void
    {
        config(['filesystems.default' => 'local']);
        Storage::fake('local');

        $source = app(CreateSource::class)->handle(
            type: SourceType::generic(),
            metadata: new SourceMetadata(['stage' => 'created']),
            changeNote: 'Initial entry',
            changedBy: 'researcher:test',
        );
        $asset = app(StoreSourceAsset::class)->handle(
            $source->id,
            new StoreSourceAssetInput(
                contents: 'historical-scan',
                originalFilename: 'record.jpg',
                mimeType: 'image/jpeg',
                retrievedAt: new DateTimeImmutable('2026-09-04T12:00:00+00:00'),
                metadata: ['quality' => 'high'],
                provenance: ['provider' => 'archive_a'],
            ),
        );
        app(UpdateSource::class)->handle(
            id: $source->id,
            type: new SourceType('civil.birth'),
            metadata: new SourceMetadata(['stage' => 'corrected']),
            texts: [
                new SourceTextInput(
                    kind: SourceTextKind::Transcription,
                    content: 'Original transcription',
                    language: 'en',
                ),
            ],
        );
        app(DetachSourceAsset::class)->handle($asset->id);

        $revisions = app(SourceRevisionRepository::class)->forSource($source->id);

        self::assertCount(4, $revisions);
        self::assertSame([1, 2, 3, 4], array_map(
            static fn (SourceRevision $revision): int => $revision->revisionNumber,
            $revisions,
        ));
        self::assertSame(SourceRevisionSnapshot::SCHEMA_VERSION, $revisions[0]->snapshot->schemaVersion);
        self::assertSame('Initial entry', $revisions[0]->changeNote);
        self::assertSame('researcher:test', $revisions[0]->changedBy);
        self::assertNotSame($revisions[0]->snapshot->payloadHash, $revisions[1]->snapshot->payloadHash);
        self::assertNotSame($revisions[2]->snapshot->payloadHash, $revisions[3]->snapshot->payloadHash);

        $historicalWithAsset = app(ReconstructSourceRevision::class)->handle($source->id, 2);
        $historicalAfterUpdate = app(ReconstructSourceRevision::class)->handle($source->id, 3);
        $historicalAfterDetach = app(ReconstructSourceRevision::class)->handle($source->id, 4);

        self::assertSame('created', $historicalWithAsset->source->metadata->toArray()['stage']);
        self::assertCount(1, $historicalWithAsset->assets);
        self::assertSame($asset->id->value, $historicalWithAsset->assets[0]->id->value);
        self::assertSame('archive_a', $historicalWithAsset->assets[0]->provenance['provider']);
        self::assertSame($asset->storage->path, $historicalWithAsset->assets[0]->storage->path);

        self::assertSame('corrected', $historicalAfterUpdate->source->metadata->toArray()['stage']);
        self::assertSame('Original transcription', $historicalAfterUpdate->source->texts[0]->content);
        self::assertCount(1, $historicalAfterUpdate->assets);
        self::assertCount(0, $historicalAfterDetach->assets);

        Storage::disk('local')->assertExists($asset->storage->path);
        $this->assertDatabaseHas('source_assets', [
            'id' => $asset->id->value,
            'source_id' => null,
        ]);
        $this->assertDatabaseHas('source_revisions', [
            'source_id' => $source->id->value,
            'revision_number' => 4,
            'snapshot_schema_version' => SourceRevisionSnapshot::SCHEMA_VERSION,
        ]);
    }
}
