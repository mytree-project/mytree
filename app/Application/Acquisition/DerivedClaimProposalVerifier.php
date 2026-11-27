<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\Claim;
use App\Domain\Acquisition\ClaimId;
use App\Domain\Acquisition\ClaimOrigin;
use App\Domain\Acquisition\ClaimOriginKind;
use App\Domain\Acquisition\ClaimValueSerializer;
use App\Domain\Acquisition\DerivedClaimProposal;
use App\Domain\Acquisition\DerivedClaimReviewStatus;
use InvalidArgumentException;

/**
 * Review is against the exact currently accepted Direct revisions, not
 * mutable values reconstructed from a newer Source/Claim snapshot.
 */
final readonly class DerivedClaimProposalVerifier
{
    public function __construct(
        private ClaimReferenceValidator $references,
        private ClaimRepository $claims,
        private MentionRevisionRepository $mentionRevisions,
    ) {}

    public function isCurrent(DerivedClaimProposal $proposal): bool
    {
        try {
            $candidate = new Claim(
                id: new ClaimId($proposal->id),
                sourceId: $proposal->sourceId,
                subjectMentionId: $proposal->subjectMentionId,
                predicate: $proposal->predicate,
                objectMentionId: $proposal->objectMentionId,
                value: $proposal->value,
                qualifiers: $proposal->qualifiers,
                origin: ClaimOrigin::derived(),
                derivation: $proposal->derivation,
            );
            $this->references->validate($candidate);
        } catch (InvalidArgumentException|MentionNotFound|SourceNotFound $exception) {
            return false;
        }

        foreach ($proposal->derivation->inputMentionRevisionIds as $inputId) {
            $revision = $this->mentionRevisions->find($inputId);
            if ($revision === null || $revision->sourceId->value !== $proposal->sourceId->value) {
                return false;
            }

            $latest = $this->mentionRevisions->latestForMention($proposal->sourceId, $revision->mentionId);
            if ($latest?->id->value !== $inputId->value) {
                return false;
            }
        }

        return true;
    }

    public function assertCurrent(DerivedClaimProposal $proposal): void
    {
        if (! $this->isCurrent($proposal)) {
            throw new InvalidArgumentException('Derived Claim proposal dependencies no longer match accepted Direct revisions.');
        }
    }

    /**
     * A reviewed Claim may only remain active if its complete accepted output
     * matches the approved fingerprint, not merely its input derivation.
     */
    public function matchesAcceptedClaim(DerivedClaimProposal $proposal, Claim $claim): bool
    {
        if ($proposal->acceptedClaimId?->value !== $claim->id->value
            || $proposal->sourceId->value !== $claim->sourceId->value
            || $claim->origin->kind !== ClaimOriginKind::Derived
            || $claim->derivation === null) {
            return false;
        }

        $candidate = new DerivedClaimProposal(
            id: $proposal->id,
            sourceId: $claim->sourceId,
            subjectMentionId: $claim->subjectMentionId,
            predicate: $claim->predicate,
            derivation: $claim->derivation,
            status: DerivedClaimReviewStatus::Pending,
            objectMentionId: $claim->objectMentionId,
            value: $claim->value,
            qualifiers: $claim->qualifiers,
        );

        return hash_equals($proposal->fingerprint, $candidate->fingerprint);
    }

    /**
     * A literally identical Direct Claim is never a reason to materialize a
     * second assertion. Predicate-specific fuzzy compatibility stays with the
     * rule producer (#237), not this generic review workflow.
     */
    public function assertNotIdenticalDirect(DerivedClaimProposal $proposal): void
    {
        foreach ($this->claims->forSource($proposal->sourceId) as $claim) {
            if ($claim->origin->kind === ClaimOriginKind::Derived
                || $claim->subjectMentionId->value !== $proposal->subjectMentionId->value
                || $claim->predicate->key !== $proposal->predicate->key
                || $claim->qualifiers->serialize() !== $proposal->qualifiers->serialize()) {
                continue;
            }

            $sameObject = $claim->objectMentionId?->value === $proposal->objectMentionId?->value;
            $sameValue = $claim->value === null
                ? $proposal->value === null
                : ($proposal->value !== null
                    && ClaimValueSerializer::serialize($claim->value) === ClaimValueSerializer::serialize($proposal->value));

            if ($sameObject && $sameValue) {
                throw new InvalidArgumentException('An identical accepted Direct Claim already expresses this assertion.');
            }
        }
    }
}
