<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\ClaimOriginKind;
use App\Domain\Acquisition\DerivedClaimReviewStatus;
use App\Domain\Acquisition\SourceId;

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

                $active = $proposal->acceptedClaimId === null
                    ? null : $this->claims->find($sourceId, $proposal->acceptedClaimId);
                $matches = $active !== null
                    && $active->origin->kind === ClaimOriginKind::Derived
                    && $active->derivation?->fingerprint() === $proposal->derivation->fingerprint();

                if ($this->verifier->isCurrent($proposal)
                    && ($proposal->status !== DerivedClaimReviewStatus::Accepted || $matches)) {
                    continue;
                }

                if ($matches && $active !== null) {
                    $this->claims->remove($sourceId, $active->id);
                }

                $this->proposals->transition(
                    $proposal,
                    DerivedClaimReviewStatus::Invalidated,
                    'system:dependency-reconciliation',
                );
            }
        });
    }
}
