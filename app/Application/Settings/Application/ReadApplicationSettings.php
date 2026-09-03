<?php

declare(strict_types=1);

namespace App\Application\Settings\Application;

use App\Application\Settings\SettingsStore;

final readonly class ReadApplicationSettings implements ApplicationSettingsProvider
{
    public function __construct(
        private SettingsStore $store,
        private ApplicationSettingsSection $section,
    ) {
    }

    public function current(): ApplicationSettings
    {
        $locale = $this->store->read($this->section->defaultLocale())->value;

        if (! is_string($locale)) {
            throw new \UnexpectedValueException('Application default locale must be a string.');
        }

        return new ApplicationSettings(defaultLocale: $locale);
    }
}
