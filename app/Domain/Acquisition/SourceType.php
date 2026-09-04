<?php

declare(strict_types=1);

namespace App\Domain\Acquisition;

use InvalidArgumentException;

final readonly class SourceType
{
    public function __construct(
        public string $key,
        public int $schemaVersion = 1,
    ) {
        if (preg_match('/^[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)*$/D', $key) !== 1) {
            throw new InvalidArgumentException('Source type key must use lowercase dot-separated identifiers.');
        }

        if ($schemaVersion < 1) {
            throw new InvalidArgumentException('Source type schema version must be at least 1.');
        }
    }

    public static function generic(): self
    {
        return new self('generic');
    }
}
