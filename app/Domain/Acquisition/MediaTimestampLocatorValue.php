<?php

declare(strict_types=1);

namespace App\Domain\Acquisition;

use InvalidArgumentException;

final readonly class MediaTimestampLocatorValue implements SourceLocatorValue
{
    public const SCHEMA_VERSION = 1;

    public function __construct(
        public int $startMilliseconds,
        public ?int $endMilliseconds = null,
        public int $valueSchemaVersion = self::SCHEMA_VERSION,
    ) {
        if ($valueSchemaVersion !== self::SCHEMA_VERSION) {
            throw new InvalidArgumentException('Unsupported media timestamp locator value schema version.');
        }

        if ($startMilliseconds < 0) {
            throw new InvalidArgumentException('Media timestamp start must not be negative.');
        }

        if ($endMilliseconds !== null && $endMilliseconds < $startMilliseconds) {
            throw new InvalidArgumentException('Media timestamp end must not precede the start.');
        }
    }

    public function type(): SourceLocatorType
    {
        return SourceLocatorType::MediaTimestamp;
    }

    public function schemaVersion(): int
    {
        return $this->valueSchemaVersion;
    }

    public function data(): array
    {
        return [
            'end_milliseconds' => $this->endMilliseconds,
            'start_milliseconds' => $this->startMilliseconds,
        ];
    }
}
