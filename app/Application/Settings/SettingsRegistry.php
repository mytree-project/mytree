<?php

declare(strict_types=1);

namespace App\Application\Settings;

use InvalidArgumentException;

final class SettingsRegistry
{
    /** @var array<string, SettingsSection> */
    private array $sections = [];

    /** @param iterable<SettingsSection> $sections */
    public function __construct(iterable $sections)
    {
        foreach ($sections as $section) {
            $sectionKey = $section->key();

            if (isset($this->sections[$sectionKey])) {
                throw new InvalidArgumentException(sprintf(
                    'Settings section "%s" is already registered.',
                    $sectionKey,
                ));
            }

            $definitionKeys = [];

            foreach ($section->definitions() as $definition) {
                if ($definition->section !== $sectionKey) {
                    throw new InvalidArgumentException(sprintf(
                        'Setting "%s" does not belong to section "%s".',
                        $definition->qualifiedKey(),
                        $sectionKey,
                    ));
                }

                if (isset($definitionKeys[$definition->key])) {
                    throw new InvalidArgumentException(sprintf(
                        'Setting "%s" is already registered in section "%s".',
                        $definition->key,
                        $sectionKey,
                    ));
                }

                $definitionKeys[$definition->key] = true;
            }

            $this->sections[$sectionKey] = $section;
        }

        ksort($this->sections);
    }

    /** @return list<SettingsSection> */
    public function sections(): array
    {
        return array_values($this->sections);
    }

    public function section(string $key): SettingsSection
    {
        return $this->sections[$key]
            ?? throw new InvalidArgumentException(sprintf('Unknown settings section "%s".', $key));
    }
}
