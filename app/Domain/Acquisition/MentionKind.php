<?php

declare(strict_types=1);

namespace App\Domain\Acquisition;

use InvalidArgumentException;

final readonly class MentionKind
{
    public const PERSON = 'person';

    public const EVENT = 'event';

    public const PLACE = 'place';

    public const ORGANIZATION = 'organization';

    public const OTHER = 'other';

    public function __construct(
        public string $key,
    ) {
        if (
            strlen($key) > 64
            || preg_match('/^[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*$/D', $key) !== 1
        ) {
            throw new InvalidArgumentException('Mention kind must be a lowercase extensible key.');
        }
    }

    public static function person(): self
    {
        return new self(self::PERSON);
    }

    public static function event(): self
    {
        return new self(self::EVENT);
    }

    public static function place(): self
    {
        return new self(self::PLACE);
    }

    public static function organization(): self
    {
        return new self(self::ORGANIZATION);
    }

    public static function other(): self
    {
        return new self(self::OTHER);
    }

    public function isCanonical(): bool
    {
        return in_array($this->key, [
            self::PERSON,
            self::EVENT,
            self::PLACE,
            self::ORGANIZATION,
            self::OTHER,
        ], true);
    }
}
