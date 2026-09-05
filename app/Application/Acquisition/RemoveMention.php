<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\Mention;
use App\Domain\Acquisition\MentionId;
use App\Domain\Acquisition\SourceId;

final readonly class RemoveMention
{
    public function __construct(
        private MentionRepository $mentions,
    ) {}

    public function handle(SourceId $sourceId, MentionId $mentionId): Mention
    {
        $mention = $this->mentions->find($sourceId, $mentionId)
            ?? throw MentionNotFound::forSourceAndId($sourceId, $mentionId);

        $this->mentions->remove($sourceId, $mentionId);

        return $mention;
    }
}
