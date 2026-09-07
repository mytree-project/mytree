<?php

declare(strict_types=1);

namespace App\Domain\Acquisition;

use InvalidArgumentException;

final readonly class SourceLocator
{
    public const SCHEMA_VERSION = 1;

    public function __construct(
        public SourceLocatorId $id,
        public SourceId $sourceId,
        public ClaimId $claimId,
        public SourceLocatorValue $value,
        public ?SourceAssetId $sourceAssetId = null,
        public int $schemaVersion = self::SCHEMA_VERSION,
    ) {
        if ($schemaVersion !== self::SCHEMA_VERSION) {
            throw new InvalidArgumentException('Unsupported Source locator schema version.');
        }
    }
}
