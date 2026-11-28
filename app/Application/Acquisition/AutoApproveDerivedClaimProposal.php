<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Application\Settings\Acquisition\DerivedClaimRulePolicies;
use App\Domain\Acquisition\Claim;
use App\Domain\Acquisition\DerivedClaimReviewStatus;
use App\Domain\Acquisition\SourceId;
use InvalidArgumentException;

/**
 * This is the *inner* approval operation used by an ongoing Source capture.
 * The enclosing capture atomically publishes the resulting EvidenceState and
 * links accepted system decisions to that snapshot, without recursion.
 */
final readonly class AutoApproveDerivedClaimProposal
{
    public function __construct(
        private DerivedClaimRulePolicies $policies,
        private DerivedClaimProposalRepository $proposals,
        private DerivedClaimProposalVerifier $verifier,
        private UpsertDerivedClaim $upsert,
        private AcquisitionTransaction $transaction,
    ) {}

    public function handle(SourceId $sourceId, string $proposalId): ?Claim
    {
        return $this->transaction->run(function () use ($sourceId, $proposalId): ?Claim {
            $proposal = $this->proposals->find($sourceId, $proposalId)
                ?? throw new InvalidArgumentException('Derived Claim proposal not found in Source.');
            $policy = $this->policies->current($proposal->derivation->ruleId);

            if (! $policy->enabled || ! $policy->autoApprove) {
                throw new InvalidArgumentException('Derived Claim rule is not enabled for automatic approval.');
            }

            // Never override an explicit rejection of the same complete roots.
            if (in_array($proposal->status, [
                DerivedClaimReviewStatus::Rejected,
                DerivedClaimReviewStatus::Invalidated,
            ], true)) {
                return null;
            }

            if ($proposal->status === DerivedClaimReviewStatus::Accepted) {
                return null; // The prior decision and Claim remain authoritative.
            }

            $this->verifier->assertCurrent($proposal);
            $this->verifier->assertNotIdenticalDirect($proposal);
            $actor = $policy->automaticActor();
            $claim = $this->upsert->handle(
                sourceId: $sourceId,
                subjectMentionId: $proposal->subjectMentionId,
                predicate: $proposal->predicate,
                derivation: $proposal->derivation,
                objectMentionId: $proposal->objectMentionId,
                value: $proposal->value,
                qualifiers: $proposal->qualifiers,
                changedBy: $actor,
            );

            $this->proposals->transition(
                $proposal,
                DerivedClaimReviewStatus::Accepted,
                $actor,
                acceptedClaimId: $claim->id,
            );

            return $claim;
        });
    }
}
