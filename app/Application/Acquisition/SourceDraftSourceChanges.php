<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\SourceMetadata;
use App\Domain\Acquisition\SourceText;
use App\Domain\Acquisition\SourceTextId;
use App\Domain\Acquisition\SourceType;

final readonly class SourceDraftSourceChanges
{
    /**
     * @param list<SourceText> $addTexts
     * @param list<SourceText> $updateTexts
     * @param list<SourceTextId> $removeTextIds
     */
    public function __construct(
        public ?SourceType $type = null,
        public ?SourceMetadata $metadata = null,
        public array $addTexts = [],
        public array $updateTexts = [],
        public array $removeTextIds = [],
    ) {}
}
