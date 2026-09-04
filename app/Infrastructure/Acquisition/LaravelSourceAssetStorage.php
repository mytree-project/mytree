<?php

declare(strict_types=1);

namespace App\Infrastructure\Acquisition;

use App\Application\Acquisition\SourceAssetStorage;
use App\Domain\Acquisition\SourceAssetId;
use App\Domain\Acquisition\SourceAssetStorageReference;
use App\Domain\Acquisition\SourceId;
use Illuminate\Filesystem\FilesystemManager;
use RuntimeException;
use UnexpectedValueException;

final class LaravelSourceAssetStorage implements SourceAssetStorage
{
    private string $disk;

    public function __construct(
        private readonly FilesystemManager $filesystems,
    ) {
        $disk = config('filesystems.default');

        if (! is_string($disk) || trim($disk) === '') {
            throw new UnexpectedValueException('The default filesystem disk must be a non-empty string.');
        }

        $this->disk = $disk;
    }

    public function referenceFor(SourceId $sourceId, SourceAssetId $assetId): SourceAssetStorageReference
    {
        return new SourceAssetStorageReference(
            disk: $this->disk,
            path: sprintf('sources/%s/assets/%s', $sourceId->value, $assetId->value),
        );
    }

    public function write(SourceAssetStorageReference $reference, string $contents): void
    {
        $stored = $this->filesystems->disk($reference->disk)->put($reference->path, $contents);

        if (! $stored) {
            throw new RuntimeException(sprintf('Unable to store Source asset on disk "%s".', $reference->disk));
        }
    }
}
