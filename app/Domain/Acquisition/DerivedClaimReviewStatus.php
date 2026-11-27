<?php

declare(strict_types=1);

namespace App\Domain\Acquisition;

/**
 * Proposal/decision lifecycle, separate from ClaimRevision acceptance.
 * An invalidated accepted proposal is historical; a new fingerprint needs
 * another pending proposal and another explicit approval.
 */
enum DerivedClaimReviewStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Invalidated = 'invalidated';
}
