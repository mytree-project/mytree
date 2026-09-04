<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class StoreSourceAssetInput
{
    /**
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>  $provenance
     */
    public function __construct(
        public string $contents,
        public string $originalFilename,
        public string $mimeType,
        public DateTimeImmutable $retrievedAt,
        public array $metadata = [],
        public array $provenance = [],
    ) {
        if ($contents === '') {
            throw new InvalidArgumentException('Source asset contents must not be empty.');
        }
    }
}
