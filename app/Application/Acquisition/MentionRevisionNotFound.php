<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\MentionId;
use App\Domain\Acquisition\SourceId;
use RuntimeException;

final class MentionRevisionNotFound extends RuntimeException
{
    public static function forMentionAndNumber(
        SourceId $sourceId,
        MentionId $mentionId,
        int $revisionNumber,
    ): self {
        return new self(sprintf(
            'Mention revision %d was not found for Mention "%s" in Source "%s".',
            $revisionNumber,
            $mentionId->value,
            $sourceId->value,
        ));
    }
}
