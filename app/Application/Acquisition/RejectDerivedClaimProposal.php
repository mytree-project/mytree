<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\DerivedClaimReviewStatus;
use App\Domain\Acquisition\SourceId;
use InvalidArgumentException;

final readonly class RejectDerivedClaimProposal
{
    public function __construct(
        private DerivedClaimProposalRepository $proposals,
        private AcquisitionTransaction $transaction,
    ) {}

    public function handle(SourceId $sourceId, string $proposalId, string $rejectedBy): void
    {
        if (trim($rejectedBy) === '') {
            throw new InvalidArgumentException('Derived Claim rejection requires an attributed reviewer.');
        }

        $this->transaction->run(function () use ($sourceId, $proposalId, $rejectedBy): void {
            $proposal = $this->proposals->find($sourceId, $proposalId)
                ?? throw new InvalidArgumentException('Derived Claim proposal not found in Source.');
            if ($proposal->status !== DerivedClaimReviewStatus::Pending) {
                throw new InvalidArgumentException('Only pending Derived Claim proposals may be rejected.');
            }

            $this->proposals->transition($proposal, DerivedClaimReviewStatus::Rejected, $rejectedBy);
        });
    }
}
