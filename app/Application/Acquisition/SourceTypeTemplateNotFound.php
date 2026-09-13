<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use RuntimeException;

final class SourceTypeTemplateNotFound extends RuntimeException
{
    public function __construct(SourceTypeTemplateId $templateId, ?int $version = null)
    {
        parent::__construct($version === null
            ? sprintf('Source Type Template "%s" was not found.', $templateId->value)
            : sprintf('Source Type Template "%s" version %d was not found.', $templateId->value, $version));
    }
}
