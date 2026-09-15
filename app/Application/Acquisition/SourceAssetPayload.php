<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

final readonly class SourceAssetPayload
{
    public function __construct(
        public string $contents,
        public string $filename,
        public string $mimeType,
    ) {}
}
