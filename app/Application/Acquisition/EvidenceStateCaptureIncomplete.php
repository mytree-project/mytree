<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\SourceId;
use RuntimeException;

final class EvidenceStateCaptureIncomplete extends RuntimeException
{
    public static function missingSourceRevision(SourceId $sourceId): self
    {
        return new self(sprintf('Cannot capture EvidenceState: Source %s has no retained SourceRevision.', $sourceId->value));
    }

    public static function missingMentionRevision(string $mentionId): self
    {
        return new self(sprintf('Cannot capture EvidenceState: Mention %s has no retained MentionRevision.', $mentionId));
    }

    public static function missingClaimRevision(string $claimId): self
    {
        return new self(sprintf('Cannot capture EvidenceState: Claim %s has no retained ClaimRevision.', $claimId));
    }
}
