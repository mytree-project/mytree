<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\MentionId;
use App\Domain\Acquisition\MentionKind;
use App\Domain\Acquisition\MentionRawData;

final readonly class SupportedAcquisitionMentionInput
{
    public function __construct(
        public ?MentionId $id,
        public MentionKind $kind,
        public string $localKey,
        public ?string $role = null,
        public ?string $displayLabel = null,
        public ?MentionRawData $rawData = null,
    ) {}
}
