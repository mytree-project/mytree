<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Acquisition;

use App\Application\Acquisition\SourceTypeTemplateDefinition;
use App\Application\Acquisition\SourceTypeTemplateId;
use App\Application\Acquisition\SourceTypeTemplateNotFound;
use App\Application\Acquisition\SourceTypeTemplateRepository;
use App\Application\Acquisition\SourceTypeTemplateVersion;
use App\Domain\Acquisition\SourceType;
use App\Infrastructure\Persistence\Eloquent\Acquisition\Models\SourceTypeTemplateVersionRecord;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use UnexpectedValueException;

final class EloquentSourceTypeTemplateRepository implements SourceTypeTemplateRepository
{
    public function latest(): array
    {
        $records = SourceTypeTemplateVersionRecord::query()
            ->orderBy('template_id')
            ->orderByDesc('version')
            ->get();

        $templates = [];
        $seenTemplateIds = [];

        foreach ($records as $record) {
            $templateId = $this->templateId($record);
            if (isset($seenTemplateIds[$templateId])) {
                continue;
            }

            $seenTemplateIds[$templateId] = true;
            $templates[] = $this->hydrate($record);
        }

        return $templates;
    }

    public function findLatest(SourceTypeTemplateId $templateId): ?SourceTypeTemplateVersion
    {
        $record = SourceTypeTemplateVersionRecord::query()
            ->where('template_id', $templateId->value)
            ->orderByDesc('version')
            ->first();

        return $record === null ? null : $this->hydrate($record);
    }

    public function findVersion(SourceTypeTemplateId $templateId, int $version): ?SourceTypeTemplateVersion
    {
        $record = SourceTypeTemplateVersionRecord::query()
            ->where('template_id', $templateId->value)
            ->where('version', $version)
            ->first();

        return $record === null ? null : $this->hydrate($record);
    }

    public function create(
        SourceTypeTemplateDefinition $definition,
        ?string $changedBy = null,
    ): SourceTypeTemplateVersion {
        return $this->store(
            templateId: new SourceTypeTemplateId(Str::uuid()->toString()),
            version: 1,
            definition: $definition,
            changedBy: $changedBy,
        );
    }

    public function append(
        SourceTypeTemplateId $templateId,
        int $expectedVersion,
        SourceTypeTemplateDefinition $definition,
        ?string $changedBy = null,
    ): SourceTypeTemplateVersion {
        return DB::transaction(function () use ($templateId, $expectedVersion, $definition, $changedBy): SourceTypeTemplateVersion {
            $record = SourceTypeTemplateVersionRecord::query()
                ->where('template_id', $templateId->value)
                ->orderByDesc('version')
                ->lockForUpdate()
                ->first();

            if ($record === null) {
                throw new SourceTypeTemplateNotFound($templateId);
            }

            $currentVersion = $this->version($record);
            if ($currentVersion !== $expectedVersion) {
                throw new \App\Application\Acquisition\SourceTypeTemplateConflict(
                    templateId: $templateId,
                    expectedVersion: $expectedVersion,
                    currentVersion: $currentVersion,
                );
            }

            return $this->store(
                templateId: $templateId,
                version: $currentVersion + 1,
                definition: $definition,
                changedBy: $changedBy,
            );
        });
    }

    private function store(
        SourceTypeTemplateId $templateId,
        int $version,
        SourceTypeTemplateDefinition $definition,
        ?string $changedBy,
    ): SourceTypeTemplateVersion {
        $record = SourceTypeTemplateVersionRecord::query()->create([
            'template_id' => $templateId->value,
            'version' => $version,
            'name' => $definition->name,
            'description' => $definition->description,
            'compatible_source_types' => array_map(
                static fn (SourceType $sourceType): array => [
                    'key' => $sourceType->key,
                    'schema_version' => $sourceType->schemaVersion,
                ],
                $definition->compatibleSourceTypes,
            ),
            'default_field_keys' => $definition->defaultFieldKeys,
            'is_active' => $definition->active,
            'changed_by' => $changedBy,
        ]);

        return $this->hydrate($record);
    }

    private function hydrate(SourceTypeTemplateVersionRecord $record): SourceTypeTemplateVersion
    {
        $compatibleSourceTypes = $record->getAttribute('compatible_source_types');
        if (! is_array($compatibleSourceTypes)) {
            throw new UnexpectedValueException('Stored compatible Source types must be an array.');
        }

        $sourceTypes = [];
        foreach ($compatibleSourceTypes as $item) {
            if (! is_array($item)) {
                throw new UnexpectedValueException('Stored compatible Source type must be an object-like array.');
            }

            $key = $item['key'] ?? null;
            $schemaVersion = $item['schema_version'] ?? null;
            if (! is_string($key) || ! is_int($schemaVersion)) {
                throw new UnexpectedValueException('Stored compatible Source type is invalid.');
            }

            $sourceTypes[] = new SourceType($key, $schemaVersion);
        }

        $defaultFieldKeys = $record->getAttribute('default_field_keys');
        if (! is_array($defaultFieldKeys)) {
            throw new UnexpectedValueException('Stored default field keys must be an array.');
        }

        $fieldKeys = [];
        foreach ($defaultFieldKeys as $fieldKey) {
            if (! is_string($fieldKey)) {
                throw new UnexpectedValueException('Stored default field key must be a string.');
            }

            $fieldKeys[] = $fieldKey;
        }

        $name = $record->getAttribute('name');
        $description = $record->getAttribute('description');
        $isActive = $record->getAttribute('is_active');
        $changedBy = $record->getAttribute('changed_by');
        $createdAt = $record->getAttribute('created_at');

        if (! is_string($name)) {
            throw new UnexpectedValueException('Stored Source Type Template name must be a string.');
        }
        if ($description !== null && ! is_string($description)) {
            throw new UnexpectedValueException('Stored Source Type Template description must be a string or null.');
        }
        if (! is_bool($isActive)) {
            throw new UnexpectedValueException('Stored Source Type Template active state must be boolean.');
        }
        if ($changedBy !== null && ! is_string($changedBy)) {
            throw new UnexpectedValueException('Stored Source Type Template changed_by must be a string or null.');
        }
        if (! $createdAt instanceof DateTimeInterface) {
            throw new UnexpectedValueException('Stored Source Type Template created_at must be a date/time value.');
        }

        return new SourceTypeTemplateVersion(
            templateId: new SourceTypeTemplateId($this->templateId($record)),
            version: $this->version($record),
            definition: new SourceTypeTemplateDefinition(
                name: $name,
                description: $description,
                compatibleSourceTypes: $sourceTypes,
                defaultFieldKeys: $fieldKeys,
                active: $isActive,
            ),
            changedBy: $changedBy,
            createdAt: DateTimeImmutable::createFromInterface($createdAt),
        );
    }

    private function templateId(SourceTypeTemplateVersionRecord $record): string
    {
        $templateId = $record->getAttribute('template_id');
        if (! is_string($templateId)) {
            throw new UnexpectedValueException('Stored Source Type Template id must be a string.');
        }

        return $templateId;
    }

    private function version(SourceTypeTemplateVersionRecord $record): int
    {
        $version = $record->getAttribute('version');
        if (! is_int($version)) {
            throw new UnexpectedValueException('Stored Source Type Template version must be an integer.');
        }

        return $version;
    }
}
