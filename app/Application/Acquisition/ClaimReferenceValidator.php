<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\Claim;

final readonly class ClaimReferenceValidator
{
    public function __construct(
        private SourceRepository $sources,
        private MentionRepository $mentions,
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
    }
}
