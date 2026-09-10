<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\ClaimId;
use App\Domain\Acquisition\MentionId;
use App\Domain\Acquisition\SourceId;
use RuntimeException;

final class EvidenceStateCaptureIncomplete extends RuntimeException
{
    public static function missingSourceRevision(SourceId $sourceId): self
    {
        return new self(sprintf('Cannot capture EvidenceState: Source %s has no retained SourceRevision.', $sourceId->value));
    }

    public static function missingMentionRevision(MentionId $mentionId): self
    {
        return new self(sprintf('Cannot capture EvidenceState: Mention %s has no retained MentionRevision.', $mentionId->value));
    }

    public static function missingClaimRevision(ClaimId $claimId): self
    {
        return new self(sprintf('Cannot capture EvidenceState: Claim %s has no retained ClaimRevision.', $claimId->value));
    }
}
