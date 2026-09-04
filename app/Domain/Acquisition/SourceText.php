<?php

declare(strict_types=1);

namespace App\Domain\Acquisition;

use InvalidArgumentException;

final readonly class SourceText
{
    public const SCHEMA_VERSION = 1;

    public function __construct(
        public SourceTextId $id,
        public SourceTextKind $kind,
        public string $content,
        public ?string $language = null,
        public int $schemaVersion = self::SCHEMA_VERSION,
    ) {
        if ($schemaVersion !== self::SCHEMA_VERSION) {
            throw new InvalidArgumentException('Unsupported SourceText schema version.');
        }

        if (trim($content) === '') {
            throw new InvalidArgumentException('Source text content must not be empty.');
        }

        if (
            $language !== null
            && (strlen($language) > 35 || preg_match('/^[A-Za-z]{2,8}(?:-[A-Za-z0-9]{1,8})*$/D', $language) !== 1)
        ) {
            throw new InvalidArgumentException('Source text language must be a valid language identifier.');
        }
    }

    public function isDerivedRepresentation(): bool
    {
        return $this->kind === SourceTextKind::Translation
            || $this->kind === SourceTextKind::Summary;
    }
}
