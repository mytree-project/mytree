<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Settings;

use App\Application\Settings\SettingDefinition;
use App\Application\Settings\SettingsStore;
use App\Application\Settings\StoredSetting;
use App\Infrastructure\Persistence\Eloquent\Settings\Models\ApplicationSettingRecord;
use Illuminate\Support\Facades\DB;
use UnexpectedValueException;

final class EloquentSettingsStore implements SettingsStore
{
    public function read(SettingDefinition $definition): StoredSetting
    {
        $record = ApplicationSettingRecord::query()
            ->where('section', $definition->section)
            ->where('key', $definition->key)
            ->first();

        if ($record === null) {
            return new StoredSetting(
                definition: $definition,
                value: $definition->defaultValue,
                revision: 0,
                valueHash: $definition->fingerprint($definition->defaultValue),
                changedBy: null,
            );
        }

        return $this->toStoredSetting($definition, $record);
    }

    public function write(
        SettingDefinition $definition,
        string|int|bool $value,
        ?string $changedBy,
    ): StoredSetting {
        $serializedValue = $definition->serialize($value);
        $valueHash = $definition->fingerprint($value);

        return DB::transaction(function () use (
            $definition,
            $serializedValue,
            $value,
            $valueHash,
            $changedBy,
        ): StoredSetting {
            $record = ApplicationSettingRecord::query()
                ->where('section', $definition->section)
                ->where('key', $definition->key)
                ->lockForUpdate()
                ->first();

            if ($record === null) {
                $record = ApplicationSettingRecord::query()->create([
                    'section' => $definition->section,
                    'key' => $definition->key,
                    'value_type' => $definition->type->value,
                    'schema_version' => $definition->schemaVersion,
                    'value' => $serializedValue,
                    'revision' => 1,
                    'value_hash' => $valueHash,
                    'changed_by' => $changedBy,
                ]);
            } else {
                $record->fill([
                    'value_type' => $definition->type->value,
                    'schema_version' => $definition->schemaVersion,
                    'value' => $serializedValue,
                    'revision' => ((int) $record->revision) + 1,
                    'value_hash' => $valueHash,
                    'changed_by' => $changedBy,
                ])->save();
            }

            return new StoredSetting(
                definition: $definition,
                value: $value,
                revision: (int) $record->revision,
                valueHash: $valueHash,
                changedBy: $changedBy,
            );
        });
    }

    private function toStoredSetting(
        SettingDefinition $definition,
        ApplicationSettingRecord $record,
    ): StoredSetting {
        if ($record->value_type !== $definition->type->value) {
            throw new UnexpectedValueException(sprintf(
                'Stored type for setting "%s" is incompatible with its registered contract.',
                $definition->qualifiedKey(),
            ));
        }

        if ((int) $record->schema_version !== $definition->schemaVersion) {
            throw new UnexpectedValueException(sprintf(
                'Stored schema version for setting "%s" is incompatible with its registered contract.',
                $definition->qualifiedKey(),
            ));
        }

        $serializedValue = (string) $record->value;
        $value = $definition->type->deserialize($serializedValue);
        $expectedHash = $definition->fingerprint($value);

        if (! hash_equals($expectedHash, (string) $record->value_hash)) {
            throw new UnexpectedValueException(sprintf(
                'Stored value hash for setting "%s" is invalid.',
                $definition->qualifiedKey(),
            ));
        }

        return new StoredSetting(
            definition: $definition,
            value: $value,
            revision: (int) $record->revision,
            valueHash: $expectedHash,
            changedBy: $record->changed_by === null ? null : (string) $record->changed_by,
        );
    }
}
