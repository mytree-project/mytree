<?php

declare(strict_types=1);

namespace App\Application\Settings;

use InvalidArgumentException;

final readonly class SettingDefinition
{
    public function __construct(
        public string $section,
        public string $key,
        public SettingValueType $type,
        public int $schemaVersion,
        public string|int|bool $defaultValue,
    ) {
        if (preg_match('/^[a-z][a-z0-9_]*$/D', $section) !== 1) {
            throw new InvalidArgumentException('Setting section must use lowercase snake_case.');
        }

        if (preg_match('/^[a-z][a-z0-9_]*$/D', $key) !== 1) {
            throw new InvalidArgumentException('Setting key must use lowercase snake_case.');
        }

        if ($schemaVersion < 1) {
            throw new InvalidArgumentException('Setting schema version must be at least 1.');
        }

        if (! $type->accepts($defaultValue)) {
            throw new InvalidArgumentException('Setting default value does not match its declared type.');
        }
    }

    public function qualifiedKey(): string
    {
        return $this->section.'.'.$this->key;
    }

    public function serialize(string|int|bool $value): string
    {
        return $this->type->serialize($value);
    }

    public function fingerprint(string|int|bool $value): string
    {
        return hash('sha256', implode("\0", [
            $this->qualifiedKey(),
            $this->type->value,
            (string) $this->schemaVersion,
            $this->serialize($value),
        ]));
    }
}
