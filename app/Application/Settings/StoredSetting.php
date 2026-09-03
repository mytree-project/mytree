<?php

declare(strict_types=1);

namespace App\Application\Settings;

final readonly class StoredSetting
{
    public function __construct(
        public SettingDefinition $definition,
        public string|int|bool $value,
        public int $revision,
        public string $valueHash,
        public ?string $changedBy,
    ) {
    }
}
