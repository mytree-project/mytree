<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\Mention;
use App\Domain\Acquisition\MentionId;
use App\Domain\Acquisition\SourceId;

final readonly class GetMention
{
    public function __construct(
        private MentionRepository $mentions,
    ) {}

    public function handle(SourceId $sourceId, MentionId $mentionId): Mention
    {
        return $this->mentions->find($sourceId, $mentionId)
            ?? throw MentionNotFound::forSourceAndId($sourceId, $mentionId);
    }
}
