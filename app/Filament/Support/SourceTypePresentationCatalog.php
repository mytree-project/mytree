<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Domain\Acquisition\SourceType;
use Illuminate\Support\Facades\Lang;

/**
 * UI-only catalog for the Source types currently supported by MyTree's
 * presentation layer.
 *
 * SourceType deliberately remains an open domain value object. Adding entries
 * here improves the ordinary UI without restricting persisted/custom keys.
 */
final class SourceTypePresentationCatalog
{
    /** @var array<string, int> */
    private const SUPPORTED_SCHEMA_VERSIONS = [
        'generic' => 1,
        'civil.birth' => 1,
        'civil.marriage' => 1,
        'civil.death' => 1,
        'oral_testimony' => 1,
        'family_tradition' => 1,
    ];

    /** @return array<string, string> */
    public function options(?SourceType $current = null): array
    {
        $options = [];
        foreach (self::SUPPORTED_SCHEMA_VERSIONS as $key => $schemaVersion) {
            $options[$key] = $this->display(new SourceType($key, $schemaVersion));
        }

        if ($current !== null && ! array_key_exists($current->key, $options)) {
            $options[$current->key] = $this->display($current);
        }

        return $options;
    }

    public function schemaVersion(string $key): ?int
    {
        return self::SUPPORTED_SCHEMA_VERSIONS[$key] ?? null;
    }

    public function label(string $key): string
    {
        $translationKey = 'ui.source_types.'.str_replace('.', '_', $key);

        if (Lang::has($translationKey)) {
            return __($translationKey);
        }

        return __('ui.source_types.fallback', ['key' => $key]);
    }

    public function display(SourceType $sourceType): string
    {
        return __('ui.source_types.with_version', [
            'label' => $this->label($sourceType->key),
            'version' => $sourceType->schemaVersion,
        ]);
    }

    public function diagnostic(SourceType $sourceType): string
    {
        return sprintf('%s@%d', $sourceType->key, $sourceType->schemaVersion);
    }
}
