<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

final readonly class SourceDraftValidationResult
{
    /** @var list<SourceDraftValidationIssue> */
    public array $issues;

    /** @param list<SourceDraftValidationIssue> $issues */
    public function __construct(array $issues = [])
    {
        $this->issues = $issues;
    }

    public function isValid(): bool
    {
        foreach ($this->issues as $issue) {
            if ($issue->severity === SourceDraftValidationIssue::ERROR) {
                return false;
            }
        }

        return true;
    }
}
