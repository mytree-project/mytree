<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\Claim;
use App\Domain\Acquisition\ClaimOriginKind;
use App\Domain\Acquisition\DerivedClaimProposal;
use App\Domain\Acquisition\DerivedClaimReviewStatus;
use App\Domain\Acquisition\SourceId;
use App\Application\Settings\Acquisition\DerivedClaimRulePolicies;
use App\Application\Settings\Acquisition\DerivedClaimRuleRegistry;
use InvalidArgumentException;

/**
 * A current EvidenceState never includes a reviewed Derived Claim whose
 * exact Direct dependency revisions have been superseded. Rule discovery and
 * background all-Source version migrations are separate #238 work.
 */
final readonly class ReconcileReviewedDerivedClaims
{
    public function __construct(
        private DerivedClaimProposalRepository $proposals,
        private DerivedClaimProposalVerifier $verifier,
        private ClaimRepository $claims,
        private DerivedClaimRulePolicies $policies,
        private AcquisitionTransaction $transaction,
    ) {}

    public function reconcile(SourceId $sourceId): void
    {
        $this->transaction->run(function () use ($sourceId): void {
            foreach ($this->proposals->forSource($sourceId) as $proposal) {
                if (! in_array($proposal->status, [
                    DerivedClaimReviewStatus::Pending,
                    DerivedClaimReviewStatus::Accepted,
                ], true)) {
                    continue;
                }

                $policy = $this->policies->current($proposal->derivation->ruleId);

                // A complete age calculation and its replacement are handled
                // atomically in the following age-rule reconciliation. Do not
                // prematurely remove the old Claim identity/revision.
                if ($policy->enabled && $policy->autoApprove
                    && $proposal->derivation->ruleId === DerivedClaimRuleRegistry::AGE_AT_EVENT) {
                    continue;
                }

                $active = $proposal->acceptedClaimId === null
                    ? null : $this->claims->find($sourceId, $proposal->acceptedClaimId);
                $matches = $active !== null && $this->verifier->matchesAcceptedClaim($proposal, $active);

                if ($this->verifier->isCurrent($proposal)
                    && ! $this->verifier->hasIdenticalDirect($proposal)
                    && ($proposal->status !== DerivedClaimReviewStatus::Accepted || $matches)) {
                    continue;
                }

                // Remove the old accepted Claim by identity even if its value
                // or lineage was modified without a new review decision.
                if ($active !== null && $active->origin->kind === ClaimOriginKind::Derived) {
                    $this->claims->remove($sourceId, $active->id);
                }

                $this->proposals->transition(
                    $proposal,
                    DerivedClaimReviewStatus::Invalidated,
                    'system:dependency-reconciliation',
                );
            }

            // Defensive guard for review-gated materializations created outside
            // the explicit approval service (e.g. an accidental direct upsert).
            // They cannot enter the accepted Source composition.
            foreach ($this->claims->forSource($sourceId) as $claim) {
                $derivation = $claim->derivation;
                if ($derivation === null) {
                    continue;
                }

                try {
                    $policy = $this->policies->current($derivation->ruleId);
                } catch (InvalidArgumentException) {
                    // Older unrelated test/future rules are not automatically
                    // enabled or interpreted as registered policy entries.
                    $policy = null;
                }

                if ($policy !== null && ! $policy->enabled) {
                    $this->claims->remove($sourceId, $claim->id);

                    continue;
                }

                if ($policy !== null && $policy->autoApprove
                    && $derivation->ruleId === DerivedClaimRuleRegistry::AGE_AT_EVENT) {
                    continue; // #235 recomputes/retires within this capture.
                }

                $reviews = $this->proposals->forOutput(
                    $sourceId,
                    $derivation->ruleId,
                    $derivation->outputKey,
                );
                if ($reviews === [] && ($derivation->assumptions['requires_review'] ?? false) !== true) {
                    continue; // Existing strictly automatic #235 outputs.
                }

                if (! $this->hasCurrentApproval($claim, $reviews)) {
                    $this->claims->remove($sourceId, $claim->id);
                }
            }
        });
    }

    /** @param list<DerivedClaimProposal> $reviews */
    private function hasCurrentApproval(Claim $claim, array $reviews): bool
    {
        foreach ($reviews as $proposal) {
            if ($proposal->status === DerivedClaimReviewStatus::Accepted
                && $this->verifier->isCurrent($proposal)
                && ! $this->verifier->hasIdenticalDirect($proposal)
                && $this->verifier->matchesAcceptedClaim($proposal, $claim)) {
                return true;
            }
        }

        return false;
    }
}
