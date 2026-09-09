<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\MentionId;
use App\Domain\Acquisition\SourceId;
use RuntimeException;

final class ClaimRevisionMentionStateNotFound extends RuntimeException
{
    public static function forMention(SourceId $sourceId, MentionId $mentionId): self
    {
        return new self(sprintf(
            'Cannot capture Claim revision because Mention "%s" in Source "%s" has no retained MentionRevision.',
            $mentionId->value,
            $sourceId->value,
        ));
    }
}
