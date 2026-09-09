<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\Claim;
use App\Domain\Acquisition\ClaimCertainty;
use App\Domain\Acquisition\ClaimOrigin;
use App\Domain\Acquisition\ClaimQualifiers;
use App\Domain\Acquisition\ClaimValue;
use App\Domain\Acquisition\MentionId;
use App\Domain\Acquisition\Predicate;
use App\Domain\Acquisition\SourceId;

final readonly class CreateClaim
{
    public function __construct(
        private ClaimRepository $claims,
        private SourceIdentifierGenerator $identifiers,
        private ClaimReferenceValidator $references,
        private ClaimRevisionRecorder $revisions,
        private AcquisitionTransaction $transaction,
    ) {}

    public function handle(
        SourceId $sourceId,
        MentionId $subjectMentionId,
        Predicate $predicate,
        ?MentionId $objectMentionId = null,
        ?ClaimValue $value = null,
        ?ClaimQualifiers $qualifiers = null,
        ?string $rawText = null,
        ?ClaimOrigin $origin = null,
        ?ClaimCertainty $transcriptionCertainty = null,
        ?ClaimCertainty $interpretationCertainty = null,
        ?string $changeNote = null,
        ?string $changedBy = null,
    ): Claim {
        $claim = new Claim(
            id: $this->identifiers->claimId(),
            sourceId: $sourceId,
            subjectMentionId: $subjectMentionId,
            predicate: $predicate,
            objectMentionId: $objectMentionId,
            value: $value,
            qualifiers: $qualifiers,
            rawText: $rawText,
            origin: $origin,
            transcriptionCertainty: $transcriptionCertainty,
            interpretationCertainty: $interpretationCertainty,
        );

        $this->references->validate($claim);
        $snapshot = $this->revisions->capture($claim);

        return $this->transaction->run(function () use ($claim, $snapshot, $changeNote, $changedBy): Claim {
            $this->claims->add($claim);
            $this->revisions->append($snapshot, $changeNote, $changedBy);

            return $claim;
        });
    }
}
