<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Acquisition\CreateSource;
use App\Application\Acquisition\DetachSourceAsset;
use App\Application\Acquisition\SourceAssetRepository;
use App\Application\Acquisition\StoreSourceAsset;
use App\Application\Acquisition\StoreSourceAssetInput;
use App\Domain\Acquisition\SourceMetadata;
use App\Domain\Acquisition\SourceType;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class SourceAssetApplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_multiple_assets_keep_independent_provenance_and_same_bytes_do_not_imply_source_identity(): void
    {
        config(['filesystems.default' => 'local']);
        Storage::fake('local');

        $firstSource = app(CreateSource::class)->handle(
            type: new SourceType('civil.birth'),
            metadata: SourceMetadata::empty(),
        );
        $secondSource = app(CreateSource::class)->handle(
            type: new SourceType('civil.birth'),
            metadata: SourceMetadata::empty(),
        );
        $store = app(StoreSourceAsset::class);
        $retrievedAt = new DateTimeImmutable('2026-09-04T12:00:00+00:00');

        $firstAsset = $store->handle(
            $firstSource->id,
            new StoreSourceAssetInput(
                contents: 'same-scan-bytes',
                originalFilename: 'archive-a.jpg',
                mimeType: 'image/jpeg',
                retrievedAt: $retrievedAt,
                metadata: ['quality' => 'high'],
                provenance: [
                    'origin' => 'archive_a',
                    'origin_url' => 'https://archive-a.example/scan/7',
                ],
            ),
        );
        $secondAsset = $store->handle(
            $firstSource->id,
            new StoreSourceAssetInput(
                contents: 'second-scan-bytes',
                originalFilename: 'archive-b.jpg',
                mimeType: 'image/jpeg',
                retrievedAt: $retrievedAt,
                provenance: ['origin' => 'archive_b'],
            ),
        );
        $sameBytesOtherSource = $store->handle(
            $secondSource->id,
            new StoreSourceAssetInput(
                contents: 'same-scan-bytes',
                originalFilename: 'independent-copy.jpg',
                mimeType: 'image/jpeg',
                retrievedAt: $retrievedAt,
                provenance: ['origin' => 'independent_copy'],
            ),
        );

        $repository = app(SourceAssetRepository::class);
        $firstSourceAssets = $repository->forSource($firstSource->id);

        self::assertCount(2, $firstSourceAssets);
        self::assertSame($firstAsset->sha256, $sameBytesOtherSource->sha256);
        self::assertNotSame($firstAsset->id->value, $sameBytesOtherSource->id->value);
        self::assertSame($firstSource->id->value, $firstAsset->sourceId?->value);
        self::assertSame($secondSource->id->value, $sameBytesOtherSource->sourceId?->value);
        $loadedFirstAsset = $repository->find($firstAsset->id);
        $loadedSecondAsset = $repository->find($secondAsset->id);

        self::assertNotNull($loadedFirstAsset);
        self::assertNotNull($loadedSecondAsset);
        self::assertSame('archive_a', $loadedFirstAsset->provenance['origin']);
        self::assertSame('archive_b', $loadedSecondAsset->provenance['origin']);

        Storage::disk('local')->assertExists($firstAsset->storage->path);
        Storage::disk('local')->assertExists($secondAsset->storage->path);
        Storage::disk('local')->assertExists($sameBytesOtherSource->storage->path);

        self::assertFalse(Schema::hasColumn('source_assets', 'contents'));
        self::assertFalse(Schema::hasColumn('source_assets', 'bytes'));

        $this->assertDatabaseHas('source_assets', [
            'id' => $firstAsset->id->value,
            'source_id' => $firstSource->id->value,
            'storage_disk' => 'local',
            'original_filename' => 'archive-a.jpg',
            'mime_type' => 'image/jpeg',
            'byte_size' => strlen('same-scan-bytes'),
            'sha256' => hash('sha256', 'same-scan-bytes'),
        ]);
    }

    public function test_detaching_asset_keeps_metadata_record_and_stored_bytes(): void
    {
        config(['filesystems.default' => 'local']);
        Storage::fake('local');

        $source = app(CreateSource::class)->handle(
            type: SourceType::generic(),
            metadata: SourceMetadata::empty(),
        );
        $asset = app(StoreSourceAsset::class)->handle(
            $source->id,
            new StoreSourceAssetInput(
                contents: 'historical-scan',
                originalFilename: 'record.jpg',
                mimeType: 'image/jpeg',
                retrievedAt: new DateTimeImmutable('2026-09-04T12:00:00+00:00'),
            ),
        );

        $detached = app(DetachSourceAsset::class)->handle($asset->id);
        $repository = app(SourceAssetRepository::class);

        self::assertNull($detached->sourceId);
        self::assertCount(0, $repository->forSource($source->id));
        self::assertNotNull($repository->find($asset->id));
        Storage::disk('local')->assertExists($asset->storage->path);
        $this->assertDatabaseHas('source_assets', [
            'id' => $asset->id->value,
            'source_id' => null,
            'storage_path' => $asset->storage->path,
        ]);
    }
}
