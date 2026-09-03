<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use RuntimeException;

final class LayerDependencyTest extends TestCase
{
    public function test_framework_independent_layers_do_not_use_framework_dependencies_or_container_helpers(): void
    {
        $root = dirname(__DIR__, 2);
        $files = array_merge(
            self::phpFiles($root.'/app/Domain'),
            self::phpFiles($root.'/app/Application'),
        );

        foreach ($files as $file) {
            $source = self::readSource($file);

            self::assertStringNotContainsString(
                'Illuminate\\',
                $source,
                sprintf('%s must remain independent from Laravel.', self::relativePath($root, $file)),
            );
            self::assertStringNotContainsString(
                'Filament\\',
                $source,
                sprintf('%s must remain independent from Filament.', self::relativePath($root, $file)),
            );
            self::assertSame(
                0,
                preg_match('/\b(?:app|config|env|resolve)\s*\(/', $source),
                sprintf('%s must not use Laravel container/configuration helpers.', self::relativePath($root, $file)),
            );
        }

        self::addToAssertionCount(1);
    }

    public function test_eloquent_dependencies_live_in_the_explicit_persistence_boundary(): void
    {
        $root = dirname(__DIR__, 2);
        $persistencePath = '/app/Infrastructure/Persistence/Eloquent/';

        foreach (self::phpFiles($root.'/app') as $file) {
            $source = self::readSource($file);
            $usesEloquent = str_contains($source, 'Illuminate\\Database\\Eloquent\\')
                || str_contains($source, 'Illuminate\\Foundation\\Auth\\User');

            if (! $usesEloquent) {
                continue;
            }

            self::assertStringContainsString(
                $persistencePath,
                str_replace('\\', '/', $file),
                sprintf('%s uses Eloquent outside the persistence boundary.', self::relativePath($root, $file)),
            );
        }

        self::assertFileExists($root.$persistencePath.'Models/User.php');
    }

    /** @return list<string> */
    private static function phpFiles(string $directory): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $entries = scandir($directory);

        if ($entries === false) {
            throw new RuntimeException(sprintf('Cannot read directory %s.', $directory));
        }

        $files = [];

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory.'/'.$entry;

            if (is_dir($path)) {
                array_push($files, ...self::phpFiles($path));

                continue;
            }

            if (str_ends_with($entry, '.php')) {
                $files[] = $path;
            }
        }

        sort($files);

        return $files;
    }

    private static function readSource(string $file): string
    {
        $source = file_get_contents($file);

        if ($source === false) {
            throw new RuntimeException(sprintf('Cannot read file %s.', $file));
        }

        return $source;
    }

    private static function relativePath(string $root, string $file): string
    {
        return ltrim(str_replace($root, '', $file), '/');
    }
}
