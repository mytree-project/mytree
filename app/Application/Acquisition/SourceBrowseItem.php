<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\SourceId;
use App\Domain\Acquisition\SourceMetadata;
use App\Domain\Acquisition\SourceType;

final readonly class SourceBrowseItem
{
    public function __construct(
        public SourceId $id,
        public SourceType $type,
        public SourceMetadata $metadata,
        public int $revisionNumber,
    ) {}
}
