<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\ClaimRevisionId;
use App\Domain\Acquisition\EvidenceStateId;
use App\Domain\Acquisition\EvidenceStateSnapshot;
use App\Domain\Acquisition\MentionRevisionId;
use App\Domain\Acquisition\SourceRevisionId;

final readonly class SourceDraftBaseState
{
    /**
     * @param  list<MentionRevisionId>  $mentionRevisionIds
     * @param  list<ClaimRevisionId>  $claimRevisionIds
     */
    public function __construct(
        public SourceRevisionId $sourceRevisionId,
        public array $mentionRevisionIds,
        public array $claimRevisionIds,
        public string $semanticHash,
        public ?EvidenceStateId $evidenceStateId = null,
    ) {}

    /**
     * @param  list<MentionRevisionId>  $mentionRevisionIds
     * @param  list<ClaimRevisionId>  $claimRevisionIds
     */
    public static function capture(
        SourceRevisionId $sourceRevisionId,
        array $mentionRevisionIds,
        array $claimRevisionIds,
        ?EvidenceStateId $evidenceStateId = null,
    ): self {
        $snapshot = EvidenceStateSnapshot::capture(
            sourceRevisionIds: [$sourceRevisionId],
            mentionRevisionIds: $mentionRevisionIds,
            claimRevisionIds: $claimRevisionIds,
        );

        return new self(
            sourceRevisionId: $sourceRevisionId,
            mentionRevisionIds: $snapshot->mentionRevisionIds,
            claimRevisionIds: $snapshot->claimRevisionIds,
            semanticHash: $snapshot->payloadHash,
            evidenceStateId: $evidenceStateId,
        );
    }

    public function matches(self $other): bool
    {
        return hash_equals($this->semanticHash, $other->semanticHash);
    }
}
