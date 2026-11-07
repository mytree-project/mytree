<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\Claim;
use App\Domain\Acquisition\ClaimCertainty;
use App\Domain\Acquisition\ClaimDerivation;
use App\Domain\Acquisition\ClaimOrigin;
use App\Domain\Acquisition\ClaimQualifiers;
use App\Domain\Acquisition\ClaimValue;
use App\Domain\Acquisition\MentionId;
use App\Domain\Acquisition\Predicate;
use App\Domain\Acquisition\SourceId;

/**
 * Persist an *already accepted* deterministic derived output.
 *
 * Pending/rejected review proposals are not Claim records; #239 owns their
 * workflow. Rule execution and suppression belong to the later #235-#238 tasks.
 */
final readonly class UpsertDerivedClaim
{
    public function __construct(
        private ClaimRepository $claims,
        private CreateClaim $createClaim,
        private UpdateClaim $updateClaim,
        private AcquisitionTransaction $transaction,
    ) {}

    public function handle(
        SourceId $sourceId,
        MentionId $subjectMentionId,
        Predicate $predicate,
        ClaimDerivation $derivation,
        ?MentionId $objectMentionId = null,
        ?ClaimValue $value = null,
        ?ClaimQualifiers $qualifiers = null,
        ?ClaimCertainty $transcriptionCertainty = null,
        ?ClaimCertainty $interpretationCertainty = null,
        ?string $changeNote = null,
        ?string $changedBy = null,
    ): Claim {
        return $this->transaction->run(function () use (
            $sourceId,
            $subjectMentionId,
            $predicate,
            $derivation,
            $objectMentionId,
            $value,
            $qualifiers,
            $transcriptionCertainty,
            $interpretationCertainty,
            $changeNote,
            $changedBy,
        ): Claim {
            foreach ($this->claims->forSource($sourceId) as $existing) {
                if ($existing->derivation?->ruleId !== $derivation->ruleId
                    || $existing->derivation->outputKey !== $derivation->outputKey) {
                    continue;
                }

                return $this->updateClaim->handle(
                    sourceId: $sourceId,
                    claimId: $existing->id,
                    subjectMentionId: $subjectMentionId,
                    predicate: $predicate,
                    objectMentionId: $objectMentionId,
                    value: $value,
                    qualifiers: $qualifiers ?? $existing->qualifiers,
                    rawText: null,
                    origin: ClaimOrigin::derived(),
                    transcriptionCertainty: $transcriptionCertainty ?? $existing->transcriptionCertainty,
                    interpretationCertainty: $interpretationCertainty ?? $existing->interpretationCertainty,
                    changeNote: $changeNote,
                    changedBy: $changedBy,
                    derivation: $derivation,
                );
            }

            return $this->createClaim->handle(
                sourceId: $sourceId,
                subjectMentionId: $subjectMentionId,
                predicate: $predicate,
                objectMentionId: $objectMentionId,
                value: $value,
                qualifiers: $qualifiers,
                origin: ClaimOrigin::derived(),
                transcriptionCertainty: $transcriptionCertainty,
                interpretationCertainty: $interpretationCertainty,
                changeNote: $changeNote,
                changedBy: $changedBy,
                derivation: $derivation,
            );
        });
    }
}
