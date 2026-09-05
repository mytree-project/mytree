<?php

declare(strict_types=1);

namespace App\Domain\Acquisition;

use InvalidArgumentException;

final readonly class Mention
{
    public const SCHEMA_VERSION = 1;

    public MentionRawData $rawData;

    public function __construct(
        public MentionId $id,
        public SourceId $sourceId,
        public MentionKind $kind,
        public string $localKey,
        public ?string $role = null,
        public ?string $displayLabel = null,
        ?MentionRawData $rawData = null,
        public int $schemaVersion = self::SCHEMA_VERSION,
    ) {
        if ($schemaVersion !== self::SCHEMA_VERSION) {
            throw new InvalidArgumentException('Unsupported Mention schema version.');
        }

        if (trim($localKey) === '' || strlen($localKey) > 255) {
            throw new InvalidArgumentException('Mention local key must be non-empty and at most 255 bytes.');
        }

        if ($role !== null && (trim($role) === '' || strlen($role) > 120)) {
            throw new InvalidArgumentException('Mention role must be non-empty and at most 120 bytes when provided.');
        }

        if ($displayLabel !== null && trim($displayLabel) === '') {
            throw new InvalidArgumentException('Mention display label must not be empty when provided.');
        }

        $this->rawData = $rawData ?? MentionRawData::empty();
    }
}
