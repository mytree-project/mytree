<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\DerivedClaimReviewStatus;
use App\Domain\Acquisition\EvidenceState;
use App\Domain\Acquisition\SourceId;
use InvalidArgumentException;

final readonly class ApproveDerivedClaimProposal
{
    public function __construct(
        private DerivedClaimProposalRepository $proposals,
        private DerivedClaimProposalVerifier $verifier,
        private UpsertDerivedClaim $upsert,
        private CaptureEvidenceStateForSource $captureEvidenceState,
        private AcquisitionTransaction $transaction,
    ) {}

    public function handle(SourceId $sourceId, string $proposalId, string $approvedBy): EvidenceState
    {
        if (trim($approvedBy) === '') {
            throw new InvalidArgumentException('Derived Claim approval requires an attributed reviewer.');
        }

        return $this->transaction->run(function () use ($sourceId, $proposalId, $approvedBy): EvidenceState {
            $proposal = $this->proposals->find($sourceId, $proposalId)
                ?? throw new InvalidArgumentException('Derived Claim proposal not found in Source.');

            if ($proposal->status !== DerivedClaimReviewStatus::Pending) {
                throw new InvalidArgumentException('Only pending Derived Claim proposals may be approved.');
            }

            // Never approve stale roots or a redundant identical Direct Claim.
            $this->verifier->assertCurrent($proposal);
            $this->verifier->assertNotIdenticalDirect($proposal);

            $claim = $this->upsert->handle(
                sourceId: $sourceId,
                subjectMentionId: $proposal->subjectMentionId,
                predicate: $proposal->predicate,
                derivation: $proposal->derivation,
                objectMentionId: $proposal->objectMentionId,
                value: $proposal->value,
                qualifiers: $proposal->qualifiers,
                changedBy: $approvedBy,
            );

            // Approval and the new EvidenceState commit atomically: an
            // unapproved proposal can never be captured as accepted evidence.
            $this->proposals->transition(
                $proposal,
                DerivedClaimReviewStatus::Accepted,
                $approvedBy,
                acceptedClaimId: $claim->id,
            );

            return $this->captureEvidenceState->capture(
                $sourceId,
                'Approved reviewed Derived Claim proposal',
                $approvedBy,
            );
        });
    }
}
