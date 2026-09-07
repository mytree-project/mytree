<?php

declare(strict_types=1);

namespace App\Domain\Acquisition;

use InvalidArgumentException;

final readonly class ClaimCertainty
{
    public const SCHEMA_VERSION = 1;

    public function __construct(
        public string $code,
        public int $schemaVersion = self::SCHEMA_VERSION,
    ) {
        if ($schemaVersion !== self::SCHEMA_VERSION) {
            throw new InvalidArgumentException('Unsupported Claim certainty schema version.');
        }

        if (preg_match('/^[a-z][a-z0-9._-]{0,63}$/D', $code) !== 1) {
            throw new InvalidArgumentException('Claim certainty code must be a stable lowercase identifier.');
        }
    }

    public static function unspecified(): self
    {
        return new self('unspecified');
    }
}
