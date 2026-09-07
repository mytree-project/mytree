<?php

declare(strict_types=1);

namespace App\Domain\Acquisition;

use InvalidArgumentException;

final readonly class PdfPageLocatorValue implements SourceLocatorValue
{
    public const SCHEMA_VERSION = 1;

    public function __construct(
        public int $page,
        public int $valueSchemaVersion = self::SCHEMA_VERSION,
    ) {
        if ($valueSchemaVersion !== self::SCHEMA_VERSION) {
            throw new InvalidArgumentException('Unsupported PDF page locator value schema version.');
        }

        if ($page < 1) {
            throw new InvalidArgumentException('PDF page locator page must be at least 1.');
        }
    }

    public function type(): SourceLocatorType
    {
        return SourceLocatorType::PdfPage;
    }

    public function schemaVersion(): int
    {
        return $this->valueSchemaVersion;
    }

    public function data(): array
    {
        return ['page' => $this->page];
    }
}
