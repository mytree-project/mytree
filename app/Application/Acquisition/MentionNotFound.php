<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\MentionId;
use App\Domain\Acquisition\SourceId;
use RuntimeException;

final class MentionNotFound extends RuntimeException
{
    public static function forSourceAndId(SourceId $sourceId, MentionId $mentionId): self
    {
        return new self(sprintf(
            'Mention "%s" was not found in Source "%s".',
            $mentionId->value,
            $sourceId->value,
        ));
    }
}
