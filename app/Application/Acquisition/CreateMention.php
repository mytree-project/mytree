<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\Mention;
use App\Domain\Acquisition\MentionKind;
use App\Domain\Acquisition\MentionRawData;
use App\Domain\Acquisition\SourceId;

final readonly class CreateMention
{
    public function __construct(
        private SourceRepository $sources,
        private MentionRepository $mentions,
        private SourceIdentifierGenerator $identifiers,
    ) {}

    public function handle(
        SourceId $sourceId,
        MentionKind $kind,
        string $localKey,
        ?string $role = null,
        ?string $displayLabel = null,
        ?MentionRawData $rawData = null,
    ): Mention {
        if ($this->sources->find($sourceId) === null) {
            throw SourceNotFound::forId($sourceId);
        }

        $mention = new Mention(
            id: $this->identifiers->mentionId(),
            sourceId: $sourceId,
            kind: $kind,
            localKey: $localKey,
            role: $role,
            displayLabel: $displayLabel,
            rawData: $rawData,
        );

        $this->mentions->add($mention);

        return $mention;
    }
}
