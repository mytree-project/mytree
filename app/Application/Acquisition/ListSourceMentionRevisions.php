<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\MentionRevision;
use App\Domain\Acquisition\SourceId;

final readonly class ListSourceMentionRevisions
{
    public function __construct(private MentionRevisionRepository $revisions) {}

    /** @return list<MentionRevision> */
    public function handle(SourceId $sourceId): array
    {
        return $this->revisions->forSource($sourceId);
    }
}
