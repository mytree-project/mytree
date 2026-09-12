<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

final readonly class SourceDraftValidationIssue
{
    public const ERROR = 'error';

    public const WARNING = 'warning';

    public function __construct(
        public string $code,
        public string $severity,
        public string $path,
        public string $message,
    ) {}
}
