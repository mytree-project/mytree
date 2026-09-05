<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\Mention;
use App\Domain\Acquisition\MentionId;
use App\Domain\Acquisition\MentionKind;
use App\Domain\Acquisition\MentionRawData;
use App\Domain\Acquisition\SourceId;

final readonly class UpdateMention
{
    public function __construct(
        private MentionRepository $mentions,
    ) {}

    public function handle(
        SourceId $sourceId,
        MentionId $mentionId,
        MentionKind $kind,
        ?string $role = null,
        ?string $displayLabel = null,
        ?MentionRawData $rawData = null,
    ): Mention {
        $current = $this->mentions->find($sourceId, $mentionId)
            ?? throw MentionNotFound::forSourceAndId($sourceId, $mentionId);

        $mention = new Mention(
            id: $current->id,
            sourceId: $current->sourceId,
            kind: $kind,
            localKey: $current->localKey,
            role: $role,
            displayLabel: $displayLabel,
            rawData: $rawData ?? $current->rawData,
            schemaVersion: $current->schemaVersion,
        );

        $this->mentions->update($mention);

        return $mention;
    }
}
