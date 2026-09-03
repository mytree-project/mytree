<?php

declare(strict_types=1);

namespace App\Application\Settings;

interface SettingsSection
{
    public const REGISTRY_TAG = 'mytree.settings.sections';

    public function key(): string;

    public function label(): string;

    /** @return list<SettingDefinition> */
    public function definitions(): array;
}
