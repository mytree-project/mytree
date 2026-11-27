<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\ClaimId;
use App\Domain\Acquisition\DerivedClaimProposal;
use App\Domain\Acquisition\DerivedClaimReviewStatus;
use App\Domain\Acquisition\EvidenceStateId;
use App\Domain\Acquisition\SourceId;

interface DerivedClaimProposalRepository
{
    public function add(DerivedClaimProposal $proposal, string $actor): void;

    public function find(SourceId $sourceId, string $proposalId): ?DerivedClaimProposal;

    public function findByFingerprint(SourceId $sourceId, string $fingerprint): ?DerivedClaimProposal;

    /** @return list<DerivedClaimProposal> */
    public function forSource(SourceId $sourceId): array;

    /** @return list<DerivedClaimProposal> */
    public function forOutput(SourceId $sourceId, string $ruleId, string $outputKey): array;

    /**
     * Optimistic, audited status transition; caller runs in an acquisition
     * transaction. Rejected/invalidated proposals remain queryable forever.
     */
    public function transition(
        DerivedClaimProposal $proposal,
        DerivedClaimReviewStatus $next,
        string $actor,
        ?ClaimId $acceptedClaimId = null,
        ?EvidenceStateId $evidenceStateId = null,
    ): void;

    /**
     * @return list<array{status: string, actor: string, evidence_state_id: ?string}>
     */
    public function history(SourceId $sourceId, string $proposalId): array;
}
