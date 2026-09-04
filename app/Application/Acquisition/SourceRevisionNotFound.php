<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\SourceId;
use RuntimeException;

final class SourceRevisionNotFound extends RuntimeException
{
    public static function forRevision(SourceId $sourceId, int $revisionNumber): self
    {
        return new self(sprintf(
            'Source "%s" revision %d was not found.',
            $sourceId->value,
            $revisionNumber,
        ));
    }
}
