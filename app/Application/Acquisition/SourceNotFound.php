<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\SourceId;
use RuntimeException;

final class SourceNotFound extends RuntimeException
{
    public static function forId(SourceId $id): self
    {
        return new self(sprintf('Source "%s" was not found.', $id->value));
    }
}
