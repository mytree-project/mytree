<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use RuntimeException;

final class SourceDraftConflict extends RuntimeException
{
    public static function stale(): self
    {
        return new self('SourceDraft base state is stale; reload the current acquisition state before saving.');
    }

    public static function sourceAlreadyExists(): self
    {
        return new self('A new SourceDraft cannot be saved because its Source identity already exists.');
    }
}
