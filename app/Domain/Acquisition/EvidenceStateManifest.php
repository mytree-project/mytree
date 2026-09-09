<?php

declare(strict_types=1);

namespace App\Domain\Acquisition;

use InvalidArgumentException;

final readonly class EvidenceStateManifest
{
    /**
     * @param  list<EvidenceStateSourceRevision>  $sourceRevisions
     * @param  list<MentionRevisionId>  $mentionRevisionIds
     * @param  list<ClaimRevisionId>  $claimRevisionIds
     */
    public function __construct(
        public array $sourceRevisions,
        public array $mentionRevisionIds,
        public array $claimRevisionIds,
    ) {
        $sourceIds = array_map(
            static fn (EvidenceStateSourceRevision $revision): string => $revision->sourceId->value,
            $sourceRevisions,
        );

        if (count(array_unique($sourceIds)) !== count($sourceIds)) {
            throw new InvalidArgumentException('EvidenceState may contain only one SourceRevision per Source.');
        }

        $mentionIds = array_map(
            static fn (MentionRevisionId $id): string => $id->value,
            $mentionRevisionIds,
        );

        if (count(array_unique($mentionIds)) !== count($mentionIds)) {
            throw new InvalidArgumentException('EvidenceState MentionRevision identities must be unique.');
        }

        $claimIds = array_map(
            static fn (ClaimRevisionId $id): string => $id->value,
            $claimRevisionIds,
        );

        if (count(array_unique($claimIds)) !== count($claimIds)) {
            throw new InvalidArgumentException('EvidenceState ClaimRevision identities must be unique.');
        }
    }
}
