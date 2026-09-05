<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\Mention;
use App\Domain\Acquisition\MentionId;
use App\Domain\Acquisition\SourceId;

interface MentionRepository
{
    public function add(Mention $mention): void;

    public function update(Mention $mention): void;

    public function find(SourceId $sourceId, MentionId $mentionId): ?Mention;

    /** @return list<Mention> */
    public function forSource(SourceId $sourceId): array;

    public function remove(SourceId $sourceId, MentionId $mentionId): void;
}
