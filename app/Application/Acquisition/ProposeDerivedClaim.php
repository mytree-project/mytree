<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\ClaimDerivation;
use App\Domain\Acquisition\ClaimOriginKind;
use App\Domain\Acquisition\ClaimQualifiers;
use App\Domain\Acquisition\ClaimValue;
use App\Domain\Acquisition\DerivedClaimProposal;
use App\Domain\Acquisition\DerivedClaimReviewStatus;
use App\Domain\Acquisition\MentionId;
use App\Domain\Acquisition\Predicate;
use App\Domain\Acquisition\SourceId;
use InvalidArgumentException;

/**
 * Rules with contextual uncertainty emit proposals; they do not call
 * UpsertDerivedClaim or append any accepted ClaimRevision themselves.
 */
final readonly class ProposeDerivedClaim
{
    public function __construct(
        private DerivedClaimProposalRepository $proposals,
        private DerivedClaimProposalVerifier $verifier,
        private SourceIdentifierGenerator $identifiers,
        private ClaimRepository $claims,
        private AcquisitionTransaction $transaction,
    ) {}

    public function handle(
        SourceId $sourceId,
        MentionId $subjectMentionId,
        Predicate $predicate,
        ClaimDerivation $derivation,
        ?ClaimValue $value = null,
        ?MentionId $objectMentionId = null,
        ?ClaimQualifiers $qualifiers = null,
        string $proposedBy = 'system:derivation-rule',
        bool $preserveAcceptedForImmediateReplacement = false,
    ): DerivedClaimProposal {
        if (trim($proposedBy) === '') {
            throw new InvalidArgumentException('Derived Claim proposal attribution is required.');
        }

        return $this->transaction->run(function () use (
            $sourceId, $subjectMentionId, $predicate, $derivation, $value,
            $objectMentionId, $qualifiers, $proposedBy, $preserveAcceptedForImmediateReplacement,
        ): DerivedClaimProposal {
            $candidate = new DerivedClaimProposal(
                id: $this->identifiers->derivedClaimProposalId(),
                sourceId: $sourceId,
                subjectMentionId: $subjectMentionId,
                predicate: $predicate,
                derivation: $derivation,
                status: DerivedClaimReviewStatus::Pending,
                objectMentionId: $objectMentionId,
                value: $value,
                qualifiers: $qualifiers,
            );

            $this->verifier->assertCurrent($candidate);
            $this->verifier->assertNotIdenticalDirect($candidate);

            $previous = $this->proposals->findByFingerprint($sourceId, $candidate->fingerprint);
            if ($previous !== null && $previous->status !== DerivedClaimReviewStatus::Invalidated) {
                // Exact rejections permanently suppress the same input/rule;
                // accepted and pending fingerprints are also idempotent.
                return $previous;
            }
            foreach ($this->proposals->forOutput($sourceId, $derivation->ruleId, $derivation->outputKey) as $prior) {
                if (! in_array($prior->status, [
                    DerivedClaimReviewStatus::Pending,
                    DerivedClaimReviewStatus::Accepted,
                ], true)) {
                    continue;
                }

                if ($prior->status === DerivedClaimReviewStatus::Accepted
                    && $prior->acceptedClaimId !== null) {
                    $current = $this->claims->find($sourceId, $prior->acceptedClaimId);
                    if (! $preserveAcceptedForImmediateReplacement
                        && $current !== null && $current->origin->kind === ClaimOriginKind::Derived) {
                        $this->claims->remove($sourceId, $current->id);
                    }
                }

                $this->proposals->transition(
                    $prior,
                    DerivedClaimReviewStatus::Invalidated,
                    'system:proposal-superseded',
                );
            }

            if ($previous !== null) {
                // Re-enabling a disabled rule, or revisiting a changed input
                // that returns to its old fingerprint, must be reviewable.
                // Rejected fingerprints never take this branch.
                $this->proposals->transition(
                    $previous,
                    DerivedClaimReviewStatus::Pending,
                    'system:proposal-reconsidered',
                );

                return $this->proposals->find($sourceId, $previous->id)
                    ?? throw new \LogicException('Reopened Derived Claim proposal was not persisted.');
            }

            $this->proposals->add($candidate, $proposedBy);

            // The outer source capture/reconciliation publishes a coherent
            // EvidenceState. Never recursively capture inside rule evaluation.
            return $candidate;
        });
    }
}
