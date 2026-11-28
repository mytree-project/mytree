<?php

declare(strict_types=1);

namespace App\Domain\Acquisition;

use InvalidArgumentException;

/**
 * A reviewable candidate and its durable lifecycle. Persistence here is not a Claim,
 * ClaimRevision, or member of EvidenceState; acceptance is a separate action.
 */
final readonly class DerivedClaimProposal
{
    public ClaimQualifiers $qualifiers;

    public string $fingerprint;

    public function __construct(
        public string $id,
        public SourceId $sourceId,
        public MentionId $subjectMentionId,
        public Predicate $predicate,
        public ClaimDerivation $derivation,
        public DerivedClaimReviewStatus $status,
        public ?MentionId $objectMentionId = null,
        public ?ClaimValue $value = null,
        ?ClaimQualifiers $qualifiers = null,
        public ?ClaimId $acceptedClaimId = null,
    ) {
        // Reuse the established UUID check without introducing a second ID
        // family into the existing SourceIdentifierGenerator contract.
        new ClaimId($id);

        if (($status === DerivedClaimReviewStatus::Accepted && $acceptedClaimId === null)
            || (in_array($status, [DerivedClaimReviewStatus::Pending, DerivedClaimReviewStatus::Rejected], true)
                && $acceptedClaimId !== null)) {
            throw new InvalidArgumentException('A proposal may reference an accepted Claim only after approval.');
        }

        if ($predicate->literalValueType !== null) {
            if ($objectMentionId !== null || $value === null) {
                throw new InvalidArgumentException('Literal Derived proposal requires a value and no object Mention.');
            }
            $predicate->assertLiteralValue($value);
        } elseif ($objectMentionId === null || $value !== null) {
            throw new InvalidArgumentException('Object Derived proposal requires an object Mention and no literal.');
        }

        $this->qualifiers = $qualifiers ?? ClaimQualifiers::empty();
        $this->fingerprint = hash('sha256', CanonicalJson::encode($this->fingerprintPayload()));
    }

    /** @return array<string, mixed> */
    public function fingerprintPayload(): array
    {
        return [
            'schema' => 'mytree.derived-claim-proposal.v1',
            'source_id' => $this->sourceId->value,
            'subject_mention_id' => $this->subjectMentionId->value,
            'predicate_key' => $this->predicate->key->value,
            'predicate_schema_version' => $this->predicate->schemaVersion,
            'object_mention_id' => $this->objectMentionId?->value,
            'value' => $this->value === null ? null : ClaimValueSerializer::toArray($this->value),
            'qualifiers' => $this->qualifiers->toArray(),
            'derivation' => $this->derivation->toArray(),
        ];
    }
}
