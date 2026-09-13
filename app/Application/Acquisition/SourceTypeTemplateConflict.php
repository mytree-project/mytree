<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use RuntimeException;

final class SourceTypeTemplateConflict extends RuntimeException
{
    public function __construct(
        SourceTypeTemplateId $templateId,
        int $expectedVersion,
        int $currentVersion,
    ) {
        parent::__construct(sprintf(
            'Source Type Template "%s" changed from version %d to %d. Reload Settings before saving again.',
            $templateId->value,
            $expectedVersion,
            $currentVersion,
        ));
    }
}
