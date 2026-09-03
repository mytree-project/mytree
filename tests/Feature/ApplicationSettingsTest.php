<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Settings\Application\ApplicationSettings;
use App\Application\Settings\Application\ApplicationSettingsProvider;
use App\Application\Settings\Application\ApplicationSettingsSection;
use App\Application\Settings\Application\UpdateApplicationSettings;
use App\Application\Settings\SettingsRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use UnexpectedValueException;

final class ApplicationSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_application_settings_have_typed_defaults_without_persisting_a_row(): void
    {
        $settings = app(ApplicationSettingsProvider::class)->current();

        self::assertSame('en', $settings->defaultLocale);
        self::assertSame(0, DB::table('application_settings')->count());
    }

    public function test_application_settings_are_persisted_with_version_attribution_and_stable_hash(): void
    {
        $updater = app(UpdateApplicationSettings::class);

        $updater->handle(new ApplicationSettings('pl-PL'), 'user:42');

        $this->assertDatabaseHas('application_settings', [
            'section' => 'application',
            'key' => 'default_locale',
            'value_type' => 'string',
            'schema_version' => 1,
            'value' => 'pl-PL',
            'revision' => 1,
            'changed_by' => 'user:42',
        ]);

        $firstHash = (string) DB::table('application_settings')
            ->where('section', 'application')
            ->where('key', 'default_locale')
            ->value('value_hash');

        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $firstHash);

        $updater->handle(new ApplicationSettings('en-GB'), 'user:43');

        $this->assertDatabaseHas('application_settings', [
            'section' => 'application',
            'key' => 'default_locale',
            'value' => 'en-GB',
            'revision' => 2,
            'changed_by' => 'user:43',
        ]);

        $secondHash = (string) DB::table('application_settings')
            ->where('section', 'application')
            ->where('key', 'default_locale')
            ->value('value_hash');

        self::assertNotSame($firstHash, $secondHash);
        self::assertSame(
            'en-GB',
            app(ApplicationSettingsProvider::class)->current()->defaultLocale,
        );
    }

    public function test_incompatible_stored_schema_version_is_rejected(): void
    {
        app(UpdateApplicationSettings::class)
            ->handle(new ApplicationSettings('pl'), 'user:42');

        DB::table('application_settings')
            ->where('section', 'application')
            ->where('key', 'default_locale')
            ->update(['schema_version' => 99]);

        $this->expectException(UnexpectedValueException::class);

        app(ApplicationSettingsProvider::class)->current();
    }

    public function test_application_section_is_discovered_through_the_registry(): void
    {
        $registry = app(SettingsRegistry::class);

        self::assertSame(
            ApplicationSettingsSection::KEY,
            $registry->sections()[0]->key(),
        );
    }
}
