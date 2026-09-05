<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\Mention;
use App\Domain\Acquisition\SourceId;

final readonly class ListSourceMentions
{
    public function __construct(
        private SourceRepository $sources,
        private MentionRepository $mentions,
    ) {}

    /** @return list<Mention> */
    public function handle(SourceId $sourceId): array
    {
        if ($this->sources->find($sourceId) === null) {
            throw SourceNotFound::forId($sourceId);
        }

        return $this->mentions->forSource($sourceId);
    }
}
