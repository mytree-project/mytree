<?php

declare(strict_types=1);

namespace App\Application\Settings;

use InvalidArgumentException;
use UnexpectedValueException;

enum SettingValueType: string
{
    case String = 'string';
    case Integer = 'integer';
    case Boolean = 'boolean';

    public function accepts(mixed $value): bool
    {
        return match ($this) {
            self::String => is_string($value),
            self::Integer => is_int($value),
            self::Boolean => is_bool($value),
        };
    }

    public function serialize(string|int|bool $value): string
    {
        if (! $this->accepts($value)) {
            throw new InvalidArgumentException(sprintf(
                'Setting value must be of type %s.',
                $this->value,
            ));
        }

        return match ($this) {
            self::String, self::Integer => (string) $value,
            self::Boolean => $value === true ? 'true' : 'false',
        };
    }

    public function deserialize(string $value): string|int|bool
    {
        return match ($this) {
            self::String => $value,
            self::Integer => $this->deserializeInteger($value),
            self::Boolean => match ($value) {
                'true' => true,
                'false' => false,
                default => throw new UnexpectedValueException('Stored setting is not a valid boolean.'),
            },
        };
    }

    private function deserializeInteger(string $value): int
    {
        $parsed = filter_var(
            $value,
            FILTER_VALIDATE_INT,
            FILTER_NULL_ON_FAILURE,
        );

        if (! is_int($parsed)) {
            throw new UnexpectedValueException('Stored setting is not a valid integer.');
        }

        return $parsed;
    }
}
