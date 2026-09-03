<?php

declare(strict_types=1);

namespace App\Application\Settings\Application;

use App\Application\Settings\SettingDefinition;
use App\Application\Settings\SettingsSection;
use App\Application\Settings\SettingValueType;

final class ApplicationSettingsSection implements SettingsSection
{
    public const KEY = 'application';

    private readonly SettingDefinition $defaultLocale;

    public function __construct()
    {
        $this->defaultLocale = new SettingDefinition(
            section: self::KEY,
            key: 'default_locale',
            type: SettingValueType::String,
            schemaVersion: 1,
            defaultValue: 'en',
        );
    }

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return 'Application';
    }

    public function defaultLocale(): SettingDefinition
    {
        return $this->defaultLocale;
    }

    public function definitions(): array
    {
        return [
            $this->defaultLocale,
        ];
    }
}
