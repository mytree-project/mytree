<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\ClaimId;

final readonly class SupportedAcquisitionClaimInput
{
    public function __construct(
        public ?ClaimId $id,
        public string $fieldKey,
        public string $subjectLocalKey,
        public ?string $objectLocalKey = null,
        public ?SupportedAcquisitionFieldValueInput $value = null,
        public ?SupportedAcquisitionFieldValueInput $effectiveTime = null,
        public ?string $rawText = null,
        public string $transcriptionCertainty = 'unspecified',
        public string $interpretationCertainty = 'unspecified',
    ) {}
}
