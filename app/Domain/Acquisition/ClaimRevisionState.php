<?php

declare(strict_types=1);

namespace App\Domain\Acquisition;

use InvalidArgumentException;

final readonly class ClaimRevisionState
{
    /**
     * @param  list<SourceLocator>  $sourceLocators
     */
    public function __construct(
        public Claim $claim,
        public MentionRevisionId $subjectMentionRevisionId,
        public ?MentionRevisionId $objectMentionRevisionId,
        public array $sourceLocators,
    ) {
        if (($claim->objectMentionId === null) !== ($objectMentionRevisionId === null)) {
            throw new InvalidArgumentException('ClaimRevision object Mention and object Mention revision must either both be present or both be absent.');
        }

        $seenLocatorIds = [];

        foreach ($sourceLocators as $locator) {
            if ($locator->sourceId->value !== $claim->sourceId->value) {
                throw new InvalidArgumentException('ClaimRevision SourceLocator must belong to the same Source.');
            }

            if ($locator->claimId->value !== $claim->id->value) {
                throw new InvalidArgumentException('ClaimRevision SourceLocator must belong to the same Claim.');
            }

            if (isset($seenLocatorIds[$locator->id->value])) {
                throw new InvalidArgumentException('ClaimRevision SourceLocator identities must be unique.');
            }

            $seenLocatorIds[$locator->id->value] = true;
        }
    }
}
