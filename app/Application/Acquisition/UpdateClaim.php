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
use App\Domain\Acquisition\SourceLinguisticRepresentation;

final readonly class UpdateClaim
{
    public function __construct(
        private ClaimRepository $claims,
        private ClaimReferenceValidator $references,
        private ClaimRevisionRecorder $revisions,
        private AcquisitionTransaction $transaction,
    ) {}

    /** @param list<SourceLinguisticRepresentation>|null $sourceLinguisticRepresentations */
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
        ?string $changeNote = null,
        ?string $changedBy = null,
        ?array $sourceLinguisticRepresentations = null,
    ): Claim {
        $current = $this->claims->find($sourceId, $claimId)
            ?? throw ClaimNotFound::forSourceAndId($sourceId, $claimId);
        $representations = $sourceLinguisticRepresentations ?? $current->sourceLinguisticRepresentations;
        $schemaVersion = $representations === []
            ? $current->schemaVersion
            : Claim::SCHEMA_VERSION;

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
            schemaVersion: $schemaVersion,
            sourceLinguisticRepresentations: $representations,
        );

        $this->references->validate($claim);
        $currentSnapshot = $this->revisions->capture($current);
        $updatedSnapshot = $this->revisions->capture($claim);

        if ($currentSnapshot->payloadHash === $updatedSnapshot->payloadHash) {
            return $current;
        }

        return $this->transaction->run(function () use ($claim, $updatedSnapshot, $changeNote, $changedBy): Claim {
            $this->claims->update($claim);
            $this->revisions->append($updatedSnapshot, $changeNote, $changedBy);

            return $claim;
        });
    }
}
