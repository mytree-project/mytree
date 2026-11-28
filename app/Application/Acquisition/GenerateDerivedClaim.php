<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Application\Settings\Acquisition\DerivedClaimRulePolicies;
use App\Domain\Acquisition\ClaimDerivation;
use App\Domain\Acquisition\ClaimQualifiers;
use App\Domain\Acquisition\ClaimValue;
use App\Domain\Acquisition\DerivedClaimProposal;
use App\Domain\Acquisition\DerivedClaimReviewStatus;
use App\Domain\Acquisition\MentionId;
use App\Domain\Acquisition\Predicate;
use App\Domain\Acquisition\SourceId;

/**
 * Shared entry point for registered deterministic rules. Both strict date
 * calculations (#235) and future contextual surname rules (#237) use the
 * same policy gates, rejection fingerprint, and decision history.
 *
 * An outer Source capture is responsible for publishing EvidenceState.
 */
final readonly class GenerateDerivedClaim
{
    public function __construct(
        private DerivedClaimRulePolicies $policies,
        private ProposeDerivedClaim $propose,
        private AutoApproveDerivedClaimProposal $approveAutomatically,
        private DerivedClaimProposalRepository $proposals,
    ) {}

    public function handle(
        SourceId $sourceId,
        MentionId $subjectMentionId,
        Predicate $predicate,
        ClaimDerivation $derivation,
        ?ClaimValue $value = null,
        ?MentionId $objectMentionId = null,
        ?ClaimQualifiers $qualifiers = null,
    ): ?DerivedClaimProposal {
        $policy = $this->policies->current($derivation->ruleId);
        if (! $policy->enabled) {
            return null;
        }

        $candidate = $this->propose->handle(
            sourceId: $sourceId,
            subjectMentionId: $subjectMentionId,
            predicate: $predicate,
            derivation: $derivation,
            value: $value,
            objectMentionId: $objectMentionId,
            qualifiers: $qualifiers,
        );

        if ($policy->autoApprove && $candidate->status === DerivedClaimReviewStatus::Pending) {
            $this->approveAutomatically->handle($sourceId, $candidate->id);

            return $this->proposals->find($sourceId, $candidate->id);
        }

        return $candidate;
    }
}
