<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\Claim;
use App\Domain\Acquisition\ClaimCertainty;
use App\Domain\Acquisition\ClaimId;
use App\Domain\Acquisition\ClaimOrigin;
use App\Domain\Acquisition\ClaimQualifiers;
use App\Domain\Acquisition\ClaimValue;
use App\Domain\Acquisition\MentionId;
use App\Domain\Acquisition\Predicate;
use App\Domain\Acquisition\SourceId;

final readonly class UpdateClaim
{
    public function __construct(
        private ClaimRepository $claims,
        private ClaimReferenceValidator $references,
    ) {}

    public function handle(
        SourceId $sourceId,
        ClaimId $claimId,
        MentionId $subjectMentionId,
        Predicate $predicate,
        ?MentionId $objectMentionId = null,
        ?ClaimValue $value = null,
        ?ClaimQualifiers $qualifiers = null,
        ?string $rawText = null,
        ?ClaimOrigin $origin = null,
        ?ClaimCertainty $transcriptionCertainty = null,
        ?ClaimCertainty $interpretationCertainty = null,
    ): Claim {
        $current = $this->claims->find($sourceId, $claimId)
            ?? throw ClaimNotFound::forSourceAndId($sourceId, $claimId);

        $claim = new Claim(
            id: $current->id,
            sourceId: $current->sourceId,
            subjectMentionId: $subjectMentionId,
            predicate: $predicate,
            objectMentionId: $objectMentionId,
            value: $value,
            qualifiers: $qualifiers ?? $current->qualifiers,
            rawText: $rawText,
            origin: $origin ?? $current->origin,
            transcriptionCertainty: $transcriptionCertainty ?? $current->transcriptionCertainty,
            interpretationCertainty: $interpretationCertainty ?? $current->interpretationCertainty,
            schemaVersion: $current->schemaVersion,
        );

        $this->references->validate($claim);
        $this->claims->update($claim);

        return $claim;
    }
}
