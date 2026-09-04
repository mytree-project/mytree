<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Acquisition;

use App\Domain\Acquisition\SourceAsset;
use App\Domain\Acquisition\SourceAssetId;
use App\Domain\Acquisition\SourceAssetStorageReference;
use App\Domain\Acquisition\SourceId;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class SourceAssetDomainTest extends TestCase
{
    public function test_detaching_asset_preserves_storage_metadata_and_provenance(): void
    {
        $asset = new SourceAsset(
            id: new SourceAssetId('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa'),
            sourceId: new SourceId('bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb'),
            storage: new SourceAssetStorageReference('local', 'sources/b/assets/a'),
            originalFilename: 'birth-record.jpg',
            mimeType: 'image/jpeg',
            byteSize: 4,
            sha256: hash('sha256', 'scan'),
            retrievedAt: new DateTimeImmutable('2026-09-04T12:00:00+00:00'),
            metadata: ['width' => 1200],
            provenance: ['origin' => 'manual_archive_copy'],
        );

        $detached = $asset->detached();

        self::assertNull($detached->sourceId);
        self::assertSame($asset->id->value, $detached->id->value);
        self::assertSame($asset->storage->disk, $detached->storage->disk);
        self::assertSame($asset->storage->path, $detached->storage->path);
        self::assertSame($asset->sha256, $detached->sha256);
        self::assertSame($asset->metadata, $detached->metadata);
        self::assertSame($asset->provenance, $detached->provenance);
    }

    public function test_asset_requires_valid_sha256(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SourceAsset(
            id: new SourceAssetId('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa'),
            sourceId: null,
            storage: new SourceAssetStorageReference('local', 'source-assets/a'),
            originalFilename: 'record.jpg',
            mimeType: 'image/jpeg',
            byteSize: 1,
            sha256: 'not-a-hash',
            retrievedAt: new DateTimeImmutable('2026-09-04T12:00:00+00:00'),
        );
    }
}
