<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\SourceTextId;
use App\Domain\Acquisition\SourceTextKind;

final readonly class SourceTextInput
{
    public function __construct(
        public SourceTextKind $kind,
        public string $content,
        public ?string $language = null,
        public ?SourceTextId $id = null,
    ) {}
}
