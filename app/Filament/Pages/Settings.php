<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Application\Acquisition\CreateSourceTypeTemplate;
use App\Application\Acquisition\ListSourceTypeTemplates;
use App\Application\Acquisition\SourceTypeTemplateConflict;
use App\Application\Acquisition\SourceTypeTemplateDefinition;
use App\Application\Acquisition\SourceTypeTemplateId;
use App\Application\Acquisition\SourceTypeTemplateNotFound;
use App\Application\Acquisition\SourceTypeTemplateVersion;
use App\Application\Acquisition\SupportedAcquisitionFieldCatalog;
use App\Application\Acquisition\UpdateSourceTypeTemplate;
use App\Application\Settings\Application\ApplicationSettings;
use App\Application\Settings\Application\ApplicationSettingsProvider;
use App\Application\Settings\Application\UpdateApplicationSettings;
use App\Domain\Acquisition\SourceType;
use App\Filament\Support\SourceTypePresentationCatalog;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use InvalidArgumentException;
use UnexpectedValueException;

final class Settings extends Page
{
    protected string $view = 'filament.pages.settings';

    public string $language = 'en';

    public bool $editingLanguage = false;

    public bool $templateEditorOpen = false;

    /** @var array<string, mixed> */
    public array $templateEditor = [];

    public static function getNavigationLabel(): string
    {
        return __('ui.navigation.settings');
    }

    public function mount(): void
    {
        $this->reloadLanguage();
        $this->resetTemplateEditor();
    }

    public function startEditingLanguage(): void
    {
        $this->reloadLanguage();
        $this->editingLanguage = true;
    }

    public function cancelEditingLanguage(): void
    {
        $this->reloadLanguage();
        $this->editingLanguage = false;
        $this->resetValidation('language');
    }

    public function saveLanguage(): void
    {
        $this->validate([
            'language' => ['required', 'in:en,pl'],
        ]);

        app(UpdateApplicationSettings::class)->handle(
            settings: new ApplicationSettings(defaultLocale: $this->language),
            changedBy: $this->changedBy(),
        );

        app()->setLocale($this->language);
        $this->editingLanguage = false;

        Notification::make()
            ->title(__('ui.settings.language_saved'))
            ->success()
            ->send();
    }

    /** @return list<array<string, mixed>> */
    public function templates(): array
    {
        return array_map(
            fn (SourceTypeTemplateVersion $template): array => $this->serializeTemplateForList($template),
            app(ListSourceTypeTemplates::class)->handle(),
        );
    }

    public function createTemplate(): void
    {
        $this->templateEditor = $this->blankTemplate();
        $this->templateEditorOpen = true;
        $this->resetValidation();
    }

    public function editTemplate(string $templateId): void
    {
        foreach (app(ListSourceTypeTemplates::class)->handle() as $template) {
            if ($template->templateId->value !== $templateId) {
                continue;
            }

            $this->templateEditor = $this->serializeTemplate($template);
            $this->templateEditorOpen = true;
            $this->resetValidation();

            return;
        }

        throw new SourceTypeTemplateNotFound(new SourceTypeTemplateId($templateId));
    }

    public function closeTemplateEditor(): void
    {
        $this->templateEditorOpen = false;
        $this->resetTemplateEditor();
        $this->resetValidation();
    }

    public function addCompatibleSourceType(): void
    {
        $rows = $this->templateEditor['compatible_source_types'] ?? [];
        if (! is_array($rows)) {
            $rows = [];
        }

        $rows[] = ['key' => '', 'schema_version' => 1];
        $this->templateEditor['compatible_source_types'] = $rows;
    }

    public function compatibleSourceTypeChanged(int $index): void
    {
        $row = $this->templateEditor['compatible_source_types'][$index] ?? null;
        if (! is_array($row)) {
            return;
        }

        $key = $row['key'] ?? null;
        if (! is_string($key)) {
            return;
        }

        $schemaVersion = app(SourceTypePresentationCatalog::class)->schemaVersion($key);
        if ($schemaVersion !== null) {
            $this->templateEditor['compatible_source_types'][$index]['schema_version'] = $schemaVersion;
        }
    }

    public function removeCompatibleSourceType(int $index): void
    {
        $rows = $this->templateEditor['compatible_source_types'] ?? [];
        if (! is_array($rows) || ! array_key_exists($index, $rows)) {
            return;
        }

        unset($rows[$index]);
        $this->templateEditor['compatible_source_types'] = array_values($rows);
    }

    public function addDefaultField(): void
    {
        $rows = $this->templateEditor['default_fields'] ?? [];
        if (! is_array($rows)) {
            $rows = [];
        }

        $rows[] = ['key' => ''];
        $this->templateEditor['default_fields'] = $rows;
    }

    public function removeDefaultField(int $index): void
    {
        $rows = $this->templateEditor['default_fields'] ?? [];
        if (! is_array($rows) || ! array_key_exists($index, $rows)) {
            return;
        }

        unset($rows[$index]);
        $this->templateEditor['default_fields'] = array_values($rows);
    }

    public function saveTemplate(): void
    {
        $this->validate([
            'templateEditor.name' => ['required', 'string', 'max:160'],
            'templateEditor.description' => ['nullable', 'string'],
            'templateEditor.active' => ['boolean'],
            'templateEditor.compatible_source_types' => ['array'],
            'templateEditor.compatible_source_types.*.key' => [
                'required',
                'string',
                'regex:/^[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)*$/',
            ],
            'templateEditor.default_fields' => ['array'],
            'templateEditor.default_fields.*.key' => ['required', 'string'],
        ]);

        try {
            $definition = $this->templateDefinition($this->templateEditor);
            $templateId = $this->templateEditor['template_id'] ?? null;

            if ($templateId === null || $templateId === '') {
                app(CreateSourceTypeTemplate::class)->handle($definition, $this->changedBy());
            } else {
                if (! is_string($templateId)) {
                    throw new UnexpectedValueException('Source Type Template id form state must be a string.');
                }

                app(UpdateSourceTypeTemplate::class)->handle(
                    templateId: new SourceTypeTemplateId($templateId),
                    expectedVersion: $this->expectedVersion($this->templateEditor['version'] ?? null),
                    definition: $definition,
                    changedBy: $this->changedBy(),
                );
            }
        } catch (InvalidArgumentException|SourceTypeTemplateConflict|SourceTypeTemplateNotFound $exception) {
            $this->addError('templateEditor.name', $exception->getMessage());

            return;
        }

        $this->templateEditorOpen = false;
        $this->resetTemplateEditor();

        Notification::make()
            ->title(__('ui.settings.template_saved'))
            ->success()
            ->send();
    }

    /** @return array<string, array<string, string>> */
    public function supportedFieldOptions(): array
    {
        $options = [];

        foreach (app(SupportedAcquisitionFieldCatalog::class)->all() as $descriptor) {
            $options[$descriptor->group][$descriptor->key] = $descriptor->label;
        }

        return $options;
    }

    /** @return array<string, string> */
    public function sourceTypeOptions(?string $currentKey = null, mixed $currentSchemaVersion = 1): array
    {
        $current = null;
        if (is_string($currentKey) && $currentKey !== '' && (is_int($currentSchemaVersion) || is_numeric($currentSchemaVersion))) {
            try {
                $current = new SourceType($currentKey, (int) $currentSchemaVersion);
            } catch (InvalidArgumentException) {
                $current = null;
            }
        }

        return app(SourceTypePresentationCatalog::class)->options($current);
    }

    private function reloadLanguage(): void
    {
        $locale = app(ApplicationSettingsProvider::class)->current()->defaultLocale;
        $this->language = str_starts_with(strtolower($locale), 'pl') ? 'pl' : 'en';
    }

    private function changedBy(): ?string
    {
        $actorId = auth()->id();

        return $actorId === null ? null : (string) $actorId;
    }

    /** @return array<string, mixed> */
    private function blankTemplate(): array
    {
        return [
            'template_id' => null,
            'version' => null,
            'name' => '',
            'description' => null,
            'active' => true,
            'compatible_source_types' => [],
            'default_fields' => [],
        ];
    }

    private function resetTemplateEditor(): void
    {
        $this->templateEditor = $this->blankTemplate();
    }

    /** @return array<string, mixed> */
    private function serializeTemplateForList(SourceTypeTemplateVersion $template): array
    {
        $serialized = $this->serializeTemplate($template);
        $serialized['compatible_source_types'] = array_map(
            static function (array $sourceType): array {
                $type = new SourceType($sourceType['key'], (int) $sourceType['schema_version']);
                $sourceType['key'] = app(SourceTypePresentationCatalog::class)->display($type);

                return $sourceType;
            },
            $serialized['compatible_source_types'],
        );

        return $serialized;
    }

    /** @return array<string, mixed> */
    private function serializeTemplate(SourceTypeTemplateVersion $template): array
    {
        return [
            'template_id' => $template->templateId->value,
            'version' => $template->version,
            'name' => $template->definition->name,
            'description' => $template->definition->description,
            'active' => $template->definition->active,
            'compatible_source_types' => array_map(
                static fn (SourceType $sourceType): array => [
                    'key' => $sourceType->key,
                    'schema_version' => $sourceType->schemaVersion,
                ],
                $template->definition->compatibleSourceTypes,
            ),
            'default_fields' => array_map(
                static fn (string $fieldKey): array => ['key' => $fieldKey],
                $template->definition->defaultFieldKeys,
            ),
        ];
    }

    /** @param array<int|string, mixed> $state */
    private function templateDefinition(array $state): SourceTypeTemplateDefinition
    {
        $name = $state['name'] ?? null;
        $description = $state['description'] ?? null;
        $active = $state['active'] ?? false;

        if (! is_string($name)) {
            throw new UnexpectedValueException('Source Type Template name form state must be a string.');
        }
        if ($description !== null && ! is_string($description)) {
            throw new UnexpectedValueException('Source Type Template description form state must be a string or null.');
        }
        if (! is_bool($active)) {
            throw new UnexpectedValueException('Source Type Template active form state must be boolean.');
        }

        return new SourceTypeTemplateDefinition(
            name: $name,
            description: $description,
            compatibleSourceTypes: $this->sourceTypes($state['compatible_source_types'] ?? []),
            defaultFieldKeys: $this->fieldKeys($state['default_fields'] ?? []),
            active: $active,
        );
    }

    /** @return list<SourceType> */
    private function sourceTypes(mixed $rows): array
    {
        if (! is_array($rows)) {
            throw new UnexpectedValueException('Compatible Source types form state must be an array.');
        }

        $sourceTypes = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                throw new UnexpectedValueException('Compatible Source type form item must be an array.');
            }

            $key = $row['key'] ?? null;
            $schemaVersion = $row['schema_version'] ?? 1;
            if (! is_string($key) || (! is_int($schemaVersion) && ! is_string($schemaVersion))) {
                throw new UnexpectedValueException('Compatible Source type form state is invalid.');
            }

            $sourceTypes[] = new SourceType($key, (int) $schemaVersion);
        }

        return $sourceTypes;
    }

    /** @return list<string> */
    private function fieldKeys(mixed $rows): array
    {
        if (! is_array($rows)) {
            throw new UnexpectedValueException('Default field form state must be an array.');
        }

        $fieldKeys = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                throw new UnexpectedValueException('Default field form item must be an array.');
            }

            $fieldKey = $row['key'] ?? null;
            if (! is_string($fieldKey)) {
                throw new UnexpectedValueException('Default field key form state must be a string.');
            }

            $fieldKeys[] = $fieldKey;
        }

        return $fieldKeys;
    }

    private function expectedVersion(mixed $version): int
    {
        if ((! is_int($version) && ! is_string($version)) || ! is_numeric($version)) {
            throw new UnexpectedValueException('Source Type Template version form state must be an integer.');
        }

        $version = (int) $version;
        if ($version < 1) {
            throw new UnexpectedValueException('Source Type Template version form state must be at least 1.');
        }

        return $version;
    }
}
