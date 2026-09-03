<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Settings\Application\ApplicationSettingsProvider;
use App\Application\Settings\Application\ApplicationSettingsSection;
use App\Application\Settings\Application\ReadApplicationSettings;
use App\Application\Settings\SettingsRegistry;
use App\Application\Settings\SettingsSection;
use App\Application\Settings\SettingsStore;
use App\Infrastructure\Persistence\Eloquent\Settings\EloquentSettingsStore;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ApplicationSettingsSection::class);
        $this->app->tag(
            [ApplicationSettingsSection::class],
            SettingsSection::REGISTRY_TAG,
        );

        $this->app->singleton(SettingsRegistry::class, function (): SettingsRegistry {
            /** @var iterable<SettingsSection> $sections */
            $sections = $this->app->tagged(SettingsSection::REGISTRY_TAG);

            return new SettingsRegistry($sections);
        });

        $this->app->bind(SettingsStore::class, EloquentSettingsStore::class);
        $this->app->bind(ApplicationSettingsProvider::class, ReadApplicationSettings::class);
    }

    public function boot(): void
    {
        //
    }
}
