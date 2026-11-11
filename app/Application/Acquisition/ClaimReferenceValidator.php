<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\Claim;
use App\Domain\Acquisition\ClaimOriginKind;
use InvalidArgumentException;

final readonly class ClaimReferenceValidator
{
    public function __construct(
        private SourceRepository $sources,
        private MentionRepository $mentions,
        private ClaimRepository $claims,
        private ClaimRevisionRepository $claimRevisions,
        private MentionRevisionRepository $mentionRevisions,
    ) {}

    public function validate(Claim $claim): void
    {
        if ($this->sources->find($claim->sourceId) === null) {
            throw SourceNotFound::forId($claim->sourceId);
        }

        $subject = $this->mentions->find($claim->sourceId, $claim->subjectMentionId)
            ?? throw MentionNotFound::forSourceAndId($claim->sourceId, $claim->subjectMentionId);
        $claim->predicate->assertSubjectKind($subject->kind);

        if ($claim->objectMentionId !== null) {
            $object = $this->mentions->find($claim->sourceId, $claim->objectMentionId)
                ?? throw MentionNotFound::forSourceAndId($claim->sourceId, $claim->objectMentionId);
            $claim->predicate->assertObjectKind($object->kind);
        }

        if ($claim->origin->kind !== ClaimOriginKind::Derived) {
            return;
        }

        $derivation = $claim->derivation
            ?? throw new InvalidArgumentException('Derived Claim has no derivation lineage.');

        foreach ($derivation->inputClaimRevisionIds as $revisionId) {
            $input = $this->claimRevisions->find($revisionId);
            if ($input === null || $input->sourceId->value !== $claim->sourceId->value) {
                throw new InvalidArgumentException('Derived Claim input ClaimRevision must belong to the same Source.');
            }
            if ($input->claimId->value === $claim->id->value) {
                throw new InvalidArgumentException('Derived Claim cannot refer to its own previous Direct revision.');
            }
            if ($input->reconstruct()->claim->origin->kind === ClaimOriginKind::Derived) {
                throw new InvalidArgumentException('Derived Claims cannot depend on other Derived Claims.');
            }

            $currentInput = $this->claims->find($claim->sourceId, $input->claimId);
            $latestInput = $this->claimRevisions->latestForClaim($claim->sourceId, $input->claimId);
            if ($currentInput === null || $latestInput?->id->value !== $revisionId->value) {
                throw new InvalidArgumentException('Derived Claim input must be a current accepted Direct ClaimRevision.');
            }
        }

        foreach ($derivation->inputMentionRevisionIds as $revisionId) {
            $input = $this->mentionRevisions->find($revisionId);
            if ($input === null || $input->sourceId->value !== $claim->sourceId->value) {
                throw new InvalidArgumentException('Derived Claim input MentionRevision must belong to the same Source.');
            }
            if ($this->mentions->find($claim->sourceId, $input->mentionId) === null) {
                throw new InvalidArgumentException('Derived Claim input Mention is no longer current.');
            }
        }
    }
}
