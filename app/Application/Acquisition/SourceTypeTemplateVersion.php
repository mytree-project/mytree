<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class SourceTypeTemplateVersion
{
    public function __construct(
        public SourceTypeTemplateId $templateId,
        public int $version,
        public SourceTypeTemplateDefinition $definition,
        public ?string $changedBy,
        public DateTimeImmutable $createdAt,
    ) {
        if ($version < 1) {
            throw new InvalidArgumentException('Source Type Template version must be at least 1.');
        }
    }
}
