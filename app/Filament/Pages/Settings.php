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
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use UnexpectedValueException;

/**
 * @property-read Schema $form
 */
final class Settings extends Page
{
    protected string $view = 'filament.pages.settings';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->fillForm();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('default_locale')
                    ->label('Default locale')
                    ->helperText('Default application locale, for example en or pl-PL.')
                    ->required()
                    ->maxLength(35)
                    ->rules([
                        'regex:/^[A-Za-z]{2,8}(?:-[A-Za-z0-9]{1,8})*$/',
                    ]),
                Repeater::make('templates')
                    ->label('Acquisition · Source type templates')
                    ->helperText('Templates choose ordered default fields for convenient data entry. They never limit which supported fields or Source/Mention/Claim data a Source may contain. Deactivate a template instead of deleting its retained history.')
                    ->schema([
                        Hidden::make('template_id'),
                        Hidden::make('version'),
                        TextInput::make('name')
                            ->required()
                            ->maxLength(160),
                        Textarea::make('description')
                            ->rows(2),
                        Toggle::make('active')
                            ->default(true),
                        Repeater::make('compatible_source_types')
                            ->label('Compatible Source types')
                            ->helperText('Optional presentation filter. Leave empty to offer the template for every Source type.')
                            ->schema([
                                TextInput::make('key')
                                    ->label('Source type key')
                                    ->helperText('Lowercase dot-separated key, for example civil.birth.')
                                    ->required()
                                    ->rules([
                                        'regex:/^[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)*$/',
                                    ]),
                                Hidden::make('schema_version')
                                    ->default(1),
                            ])
                            ->defaultItems(0)
                            ->addActionLabel('Add compatible Source type')
                            ->columnSpanFull(),
                        Repeater::make('default_fields')
                            ->label('Ordered default structured fields')
                            ->helperText('Drag rows to change presentation order. Zero fields is a valid blank template.')
                            ->schema([
                                Select::make('key')
                                    ->label('Supported field')
                                    ->options($this->supportedFieldOptions())
                                    ->searchable()
                                    ->required(),
                            ])
                            ->defaultItems(0)
                            ->addActionLabel('Add default field')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->addActionLabel('Create Source Type Template')
                    ->deletable(false),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        /** @var array<string, mixed> $data */
        $data = $this->form->getState();
        $defaultLocale = $data['default_locale'] ?? null;

        if (! is_string($defaultLocale)) {
            throw new UnexpectedValueException('Default locale form state must be a string.');
        }

        $actorId = auth()->id();
        $changedBy = $actorId === null ? null : (string) $actorId;

        try {
            $this->saveTemplates($data['templates'] ?? [], $changedBy);
        } catch (InvalidArgumentException|SourceTypeTemplateConflict|SourceTypeTemplateNotFound $exception) {
            throw ValidationException::withMessages([
                'data.templates' => $exception->getMessage(),
            ]);
        }

        app(UpdateApplicationSettings::class)->handle(
            settings: new ApplicationSettings(
                defaultLocale: $defaultLocale,
            ),
            changedBy: $changedBy,
        );

        $this->fillForm();

        Notification::make()
            ->title('Settings saved')
            ->success()
            ->send();
    }

    private function fillForm(): void
    {
        $settings = app(ApplicationSettingsProvider::class)->current();
        $templates = app(ListSourceTypeTemplates::class)->handle();

        $this->form->fill([
            'default_locale' => $settings->defaultLocale,
            'templates' => array_map(
                fn (SourceTypeTemplateVersion $template): array => $this->serializeTemplate($template),
                $templates,
            ),
        ]);
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

    private function saveTemplates(mixed $templates, ?string $changedBy): void
    {
        if (! is_array($templates)) {
            throw new UnexpectedValueException('Source Type Template form state must be an array.');
        }

        foreach ($templates as $templateState) {
            if (! is_array($templateState)) {
                throw new UnexpectedValueException('Source Type Template form item must be an array.');
            }

            $definition = $this->templateDefinition($templateState);
            $templateId = $templateState['template_id'] ?? null;

            if ($templateId === null || $templateId === '') {
                app(CreateSourceTypeTemplate::class)->handle($definition, $changedBy);

                continue;
            }

            if (! is_string($templateId)) {
                throw new UnexpectedValueException('Source Type Template id form state must be a string.');
            }

            app(UpdateSourceTypeTemplate::class)->handle(
                templateId: new SourceTypeTemplateId($templateId),
                expectedVersion: $this->expectedVersion($templateState['version'] ?? null),
                definition: $definition,
                changedBy: $changedBy,
            );
        }
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

    /** @return array<string, array<string, string>> */
    private function supportedFieldOptions(): array
    {
        $options = [];

        foreach (app(SupportedAcquisitionFieldCatalog::class)->all() as $descriptor) {
            $options[$descriptor->group][$descriptor->key] = $descriptor->label;
        }

        return $options;
    }
}
