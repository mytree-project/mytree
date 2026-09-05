<?php

declare(strict_types=1);

namespace App\Domain\Acquisition;

use InvalidArgumentException;

final readonly class MentionRawData
{
    /** @var array<string, mixed> */
    private array $values;

    /** @param array<string, mixed> $values */
    public function __construct(array $values)
    {
        foreach ($values as $key => $value) {
            if ($key === '') {
                throw new InvalidArgumentException('Mention raw-data keys must not be empty.');
            }

            self::assertJsonCompatible($value);
        }

        $this->values = $values;
    }

    public static function empty(): self
    {
        return new self([]);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->values;
    }

    private static function assertJsonCompatible(mixed $value): void
    {
        if ($value === null || is_string($value) || is_int($value) || is_float($value) || is_bool($value)) {
            return;
        }

        if (is_array($value)) {
            foreach ($value as $nestedValue) {
                self::assertJsonCompatible($nestedValue);
            }

            return;
        }

        throw new InvalidArgumentException('Mention raw data must contain only JSON-compatible values.');
    }
}
