<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Settings;

use App\Application\Settings\Application\ApplicationSettingsSection;
use App\Application\Settings\SettingsRegistry;
use App\Application\Settings\SettingsSection;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class SettingsRegistryTest extends TestCase
{
    public function test_registered_sections_are_exposed_deterministically(): void
    {
        $registry = new SettingsRegistry([
            new ApplicationSettingsSection(),
        ]);

        self::assertSame(
            [ApplicationSettingsSection::KEY],
            array_map(
                static fn (SettingsSection $section): string => $section->key(),
                $registry->sections(),
            ),
        );
        self::assertSame(
            'application.default_locale',
            $registry
                ->section(ApplicationSettingsSection::KEY)
                ->definitions()[0]
                ->qualifiedKey(),
        );
    }

    public function test_duplicate_section_keys_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SettingsRegistry([
            new ApplicationSettingsSection(),
            new ApplicationSettingsSection(),
        ]);
    }

    public function test_setting_fingerprint_is_stable_for_the_same_semantic_value(): void
    {
        $definition = (new ApplicationSettingsSection())->defaultLocale();

        self::assertSame(
            $definition->fingerprint('pl-PL'),
            $definition->fingerprint('pl-PL'),
        );
        self::assertNotSame(
            $definition->fingerprint('pl-PL'),
            $definition->fingerprint('en'),
        );
    }
}
