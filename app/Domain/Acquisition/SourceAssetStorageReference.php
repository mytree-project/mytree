<?php

declare(strict_types=1);

namespace App\Domain\Acquisition;

use InvalidArgumentException;

final readonly class SourceAssetStorageReference
{
    public function __construct(
        public string $disk,
        public string $path,
    ) {
        if (trim($disk) === '') {
            throw new InvalidArgumentException('Source asset storage disk must not be empty.');
        }

        if (trim($path) === '') {
            throw new InvalidArgumentException('Source asset storage path must not be empty.');
        }
    }
}
