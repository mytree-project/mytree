<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use RuntimeException;

final class InvalidSourceDraft extends RuntimeException
{
    public function __construct(public readonly SourceDraftValidationResult $validation)
    {
        parent::__construct('SourceDraft validation failed.');
    }
}
