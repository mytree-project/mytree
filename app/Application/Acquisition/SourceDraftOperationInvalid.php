<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use RuntimeException;

final class SourceDraftOperationInvalid extends RuntimeException
{
    public function __construct(
        public readonly string $path,
        string $message,
    ) {
        parent::__construct($message);
    }
}
