<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\MentionId;
use App\Domain\Acquisition\MentionRevision;
use App\Domain\Acquisition\SourceId;

final readonly class GetMentionRevision
{
    public function __construct(private MentionRevisionRepository $revisions) {}

    public function handle(SourceId $sourceId, MentionId $mentionId, int $revisionNumber): MentionRevision
    {
        return $this->revisions->findForMention($sourceId, $mentionId, $revisionNumber)
            ?? throw MentionRevisionNotFound::forMentionAndNumber($sourceId, $mentionId, $revisionNumber);
    }
}
