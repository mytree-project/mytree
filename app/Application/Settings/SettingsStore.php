<?php

declare(strict_types=1);

namespace App\Application\Settings;

/**
 * Persistence boundary for ordinary semantic/application configuration.
 *
 * Secrets and deployment credentials are intentionally outside this contract.
 */
interface SettingsStore
{
    public function read(SettingDefinition $definition): StoredSetting;

    public function write(
        SettingDefinition $definition,
        string|int|bool $value,
        ?string $changedBy,
    ): StoredSetting;
}
