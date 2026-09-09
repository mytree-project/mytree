<?php

declare(strict_types=1);

namespace App\Domain\Acquisition;

use InvalidArgumentException;

final readonly class QuotedFragmentLocatorValue implements SourceLocatorValue
{
    public const SCHEMA_VERSION = 1;

    public function __construct(
        public string $text,
        public int $valueSchemaVersion = self::SCHEMA_VERSION,
    ) {
        if ($valueSchemaVersion !== self::SCHEMA_VERSION) {
            throw new InvalidArgumentException('Unsupported quoted-fragment locator value schema version.');
        }

        if (trim($text) === '') {
            throw new InvalidArgumentException('Quoted-fragment locator text must not be empty.');
        }
    }

    public function type(): SourceLocatorType
    {
        return SourceLocatorType::QuotedFragment;
    }

    public function schemaVersion(): int
    {
        return $this->valueSchemaVersion;
    }

    public function data(): array
    {
        return ['text' => $this->text];
    }
}
