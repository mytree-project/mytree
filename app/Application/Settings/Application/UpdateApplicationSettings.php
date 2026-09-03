<?php

declare(strict_types=1);

namespace App\Application\Settings\Application;

use App\Application\Settings\SettingsStore;

final readonly class UpdateApplicationSettings
{
    public function __construct(
        private SettingsStore $store,
        private ApplicationSettingsSection $section,
    ) {
    }

    public function handle(ApplicationSettings $settings, ?string $changedBy): ApplicationSettings
    {
        $this->store->write(
            definition: $this->section->defaultLocale(),
            value: $settings->defaultLocale,
            changedBy: $changedBy,
        );

        return $settings;
    }
}
