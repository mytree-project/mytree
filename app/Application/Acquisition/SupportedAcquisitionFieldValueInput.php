<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

final readonly class SupportedAcquisitionFieldValueInput
{
    public function __construct(
        public string $rawValue,
        public ?string $expressionKind = null,
        public string|int|null $from = null,
        public string|int|null $to = null,
        public ?string $ageUnit = null,
        public ?int $integerValue = null,
        public ?bool $booleanValue = null,
        public ?string $enumKey = null,
    ) {}
}
