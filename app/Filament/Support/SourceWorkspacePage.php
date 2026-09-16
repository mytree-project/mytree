<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Application\Acquisition\BrowseSources;
use App\Application\Acquisition\InvalidSourceDraft;
use App\Application\Acquisition\ListSourceTypeTemplates;
use App\Application\Acquisition\LoadSourceDraft;
use App\Application\Acquisition\SaveSourceDraft;
use App\Application\Acquisition\SourceDraft;
use App\Application\Acquisition\SourceDraftBaseState;
use App\Application\Acquisition\SourceDraftChanges;
use App\Application\Acquisition\SourceDraftConflict;
use App\Application\Acquisition\SourceDraftSourceChanges;
use App\Application\Acquisition\SourceDraftValidationResult;
use App\Application\Acquisition\SourceIdentifierGenerator;
use App\Application\Acquisition\SourceNotFound;
use App\Application\Acquisition\SourceTypeTemplateVersion;
use App\Application\Acquisition\StageSourceAsset;
use App\Application\Acquisition\StoreSourceAssetInput;
use App\Application\Acquisition\SupportedAcquisitionDraftEditor;
use App\Application\Acquisition\ValidateSourceDraft;
use App\Domain\Acquisition\ClaimRevisionId;
use App\Domain\Acquisition\EvidenceStateId;
use App\Domain\Acquisition\MentionRevisionId;
use App\Domain\Acquisition\SourceAssetId;
use App\Domain\Acquisition\SourceId;
use App\Domain\Acquisition\SourceMetadata;
use App\Domain\Acquisition\SourceRevisionId;
use App\Domain\Acquisition\SourceText;
use App\Domain\Acquisition\SourceTextId;
use App\Domain\Acquisition\SourceTextKind;
use App\Domain\Acquisition\SourceType;
use App\Filament\Pages\Acquisition\Support\StructuredAcquisitionFormAdapter;
use DateTimeImmutable;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

/**
 * Source-scoped acquisition workspace.
 *
 * Source details, source-text representations and structured Mention/Claim data
 * share one SourceDraft save. The split-pane UI is presentation only and never
 * creates a second persistence path.
 *
 * @property-read Schema $form
 * @property-read Schema $evidenceForm
 */
abstract class SourceWorkspacePage extends Page
{
    /** @var list<string> */
    private const WORKSPACE_MODES = ['asset', 'transcription', 'translation', 'evidence'];

    protected static ?string $slug = 'acquisition/source';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.acquisition.source-editor';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    /** @var array<string, mixed>|null */
    public ?array $evidenceData = [];

    /** @var list<array{id: ?string, kind: string, language: ?string, content: string}> */
    public array $sourceTexts = [];

    public ?string $sourceId = null;

    public ?int $revisionNumber = null;

    public string $sourceTypeContext = '';

    public string $leftPanel = 'asset';

    public string $rightPanel = 'evidence';

    /** @var array<string, string> */
    public array $assetChoices = [];

    /** @var list<array{id: string, filename: string, mime_type: string, size: string, url: string}> */
    public array $assetPreviews = [];

    /** @var array<string, mixed>|null */
    public ?array $baseState = null;

    public function mount(?string $source = null): void
    {
        $requestedSource = $source ?? request()->query('source');

        if ($requestedSource === null || trim((string) $requestedSource) === '') {
            $draft = app(LoadSourceDraft::class)->blank(SourceType::generic());
            $this->fillFromDraft($draft);
            $this->applyRequestedTemplate();

            return;
        }

        try {
            $sourceId = new SourceId((string) $requestedSource);
        } catch (InvalidArgumentException) {
            abort(404);
        }

        try {
            $draft = app(LoadSourceDraft::class)->handle($sourceId);
        } catch (SourceNotFound) {
            abort(404);
        }

        $summary = app(BrowseSources::class)->find($sourceId);
        if ($summary === null) {
            abort(404);
        }

        $this->sourceId = $sourceId->value;
        $this->revisionNumber = $summary->revisionNumber;
        $this->sourceTypeContext = app(SourceTypePresentationCatalog::class)->display($summary->type);
        $this->baseState = $this->serializeBaseState($draft->baseState);
        $this->fillFromDraft($draft);
        $this->applyRequestedTemplate();
    }

    public function getTitle(): string
    {
        return $this->sourceId === null
            ? __('ui.workspace.create_title')
            : __('ui.workspace.edit_title');
    }

    public function getSubheading(): string
    {
        if ($this->sourceId === null) {
            return __('ui.workspace.subheading_new', [
                'type' => $this->sourceTypeContext,
            ]);
        }

        return __('ui.workspace.subheading_existing', [
            'id' => $this->sourceId,
            'type' => $this->sourceTypeContext,
            'revision' => $this->revisionNumber ?? 0,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('ui.workspace.form.source_name'))
                    ->helperText(__('ui.workspace.form.source_name_help'))
                    ->placeholder(__('ui.workspace.form.source_name_placeholder'))
                    ->maxLength(255),
                Select::make('source_type_key')
                    ->label(__('ui.source_types.field_label'))
                    ->helperText(__('ui.source_types.field_help'))
                    ->options(fn (): array => $this->sourceTypeOptions())
                    ->searchable()
                    ->required()
                    ->rules(['regex:/^[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)*$/'])
                    ->live()
                    ->afterStateUpdated(function (): void {
                        $this->sourceTypeChanged();
                    }),
                Hidden::make('source_type_schema_version')
                    ->required(),
                Select::make('template_id')
                    ->label(__('ui.workspace.form.template'))
                    ->helperText(__('ui.workspace.form.template_help'))
                    ->placeholder(__('ui.workspace.form.template_none'))
                    ->options(fn (): array => $this->templateOptions())
                    ->live()
                    ->afterStateUpdated(function (?string $state): void {
                        $this->templateChanged($state);
                    }),
                Repeater::make('metadata')
                    ->label(__('ui.workspace.form.metadata'))
                    ->helperText(__('ui.workspace.form.metadata_help'))
                    ->schema([
                        TextInput::make('key')
                            ->required()
                            ->maxLength(120),
                        Select::make('type')
                            ->required()
                            ->options([
                                'string' => __('ui.workspace.form.type_text'),
                                'integer' => __('ui.workspace.form.type_integer'),
                                'number' => __('ui.workspace.form.type_number'),
                                'boolean' => __('ui.workspace.form.type_boolean'),
                                'null' => __('ui.workspace.form.type_null'),
                            ]),
                        TextInput::make('value')
                            ->helperText(__('ui.workspace.form.metadata_value_help')),
                    ])
                    ->columns(3)
                    ->defaultItems(0)
                    ->addActionLabel(__('ui.workspace.form.add_metadata')),
                CheckboxList::make('detach_asset_ids')
                    ->label(__('ui.workspace.form.attached_assets'))
                    ->helperText(__('ui.workspace.form.attached_assets_help'))
                    ->options(fn (): array => $this->assetChoices)
                    ->columns(1),
                FileUpload::make('uploads')
                    ->label(__('ui.workspace.form.attach_assets'))
                    ->helperText(__('ui.workspace.form.attach_assets_help'))
                    ->multiple()
                    ->storeFiles(false)
                    ->previewable(false),
            ])
            ->statePath('data');
    }

    public function evidenceForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('add_supported_field')
                    ->label(__('ui.workspace.evidence.add_field'))
                    ->helperText(__('ui.workspace.evidence.add_field_help'))
                    ->placeholder(__('ui.workspace.evidence.choose_field'))
                    ->options(fn (): array => app(StructuredAcquisitionFormAdapter::class)->pickerOptions())
                    ->live()
                    ->afterStateUpdated(function (?string $state): void {
                        $this->supportedFieldSelected($state);
                    }),
                ...app(StructuredAcquisitionFormAdapter::class)->components(),
            ])
            ->statePath('evidenceData');
    }

    public function sourceTypeChanged(): void
    {
        $state = is_array($this->data) ? $this->data : [];
        $key = $state['source_type_key'] ?? null;
        if (is_string($key)) {
            $schemaVersion = app(SourceTypePresentationCatalog::class)->schemaVersion($key);
            if ($schemaVersion !== null) {
                $state['source_type_schema_version'] = $schemaVersion;
                $this->data = $state;
                $this->form->fill($state);
            }
        }

        $sourceType = $this->currentFormSourceType();
        if ($sourceType !== null) {
            $this->sourceTypeContext = app(SourceTypePresentationCatalog::class)->display($sourceType);
        }

        $selectedTemplateId = $this->selectedTemplateId();
        if ($selectedTemplateId !== null && $this->findSelectableTemplate($selectedTemplateId) === null) {
            $this->templateChanged(null);
        }
    }

    public function templateChanged(?string $templateId): void
    {
        $templateId = $this->optionalString($templateId);
        $template = $templateId === null ? null : $this->findSelectableTemplate($templateId);
        if ($templateId !== null && $template === null) {
            $this->addError('data.template_id', __('ui.workspace.template_incompatible'));
            $templateId = null;
        } else {
            $this->resetErrorBag('data.template_id');
        }

        $sourceState = is_array($this->data) ? $this->data : [];
        $sourceState['template_id'] = $templateId;
        $this->data = $sourceState;
        $this->form->fill($sourceState);

        $evidenceState = is_array($this->evidenceData) ? $this->evidenceData : [];
        $evidenceState = app(StructuredAcquisitionFormAdapter::class)->applyTemplatePresentation(
            $evidenceState,
            $template?->definition->defaultFieldKeys ?? [],
        );
        $evidenceState['add_supported_field'] = null;
        $this->evidenceData = $evidenceState;
        $this->evidenceForm->fill($evidenceState);
    }

    public function supportedFieldSelected(?string $fieldKey): void
    {
        $fieldKey = $this->optionalString($fieldKey);
        if ($fieldKey === null) {
            return;
        }

        $state = is_array($this->evidenceData) ? $this->evidenceData : [];
        $state = app(StructuredAcquisitionFormAdapter::class)->addSupportedField($state, $fieldKey);
        $state['add_supported_field'] = null;
        $this->evidenceData = $state;
        $this->evidenceForm->fill($state);
    }

    public function setWorkspacePanel(string $side, string $mode): void
    {
        if (! in_array($mode, self::WORKSPACE_MODES, true)) {
            return;
        }

        if ($side === 'left') {
            if ($mode === $this->rightPanel) {
                $this->rightPanel = $this->leftPanel;
            }
            $this->leftPanel = $mode;

            return;
        }

        if ($side === 'right') {
            if ($mode === $this->leftPanel) {
                $this->leftPanel = $this->rightPanel;
            }
            $this->rightPanel = $mode;
        }
    }

    public function addSourceText(string $kind): void
    {
        if (! in_array($kind, array_column(SourceTextKind::cases(), 'value'), true)) {
            return;
        }

        $this->sourceTexts[] = [
            'id' => null,
            'kind' => $kind,
            'language' => null,
            'content' => '',
        ];
    }

    public function removeSourceText(int $index): void
    {
        if (! array_key_exists($index, $this->sourceTexts)) {
            return;
        }

        array_splice($this->sourceTexts, $index, 1);
    }

    public function save(): void
    {
        /** @var array<string, mixed> $data */
        $data = $this->form->getState();
        /** @var array<string, mixed> $evidenceData */
        $evidenceData = $this->evidenceForm->getState();
        $data['texts'] = $this->sourceTexts;

        try {
            $draft = $this->draftForSave();
        } catch (SourceDraftConflict $exception) {
            $this->reportConflict($exception);

            return;
        }

        try {
            $sourceChanges = $this->sourceChanges($draft, $data);
            $detachAssetIds = $this->detachAssetIds($data);
            $structuredInput = app(StructuredAcquisitionFormAdapter::class)->editInput($evidenceData);
            $structuredChanges = app(SupportedAcquisitionDraftEditor::class)->changes($draft, $structuredInput);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw ValidationException::withMessages([
                'data' => $exception->getMessage(),
            ]);
        }

        $preflightChanges = $this->combinedChanges(
            sourceChanges: $sourceChanges,
            structuredChanges: $structuredChanges,
            detachAssetIds: $detachAssetIds,
        );
        $preflight = $draft->withChanges($preflightChanges);
        $validation = app(ValidateSourceDraft::class)->handle($preflight);

        if (! $validation->isValid()) {
            $this->reportDraftValidation($validation);

            return;
        }

        $attachAssetIds = [];
        foreach ($this->uploadedFiles($data) as $file) {
            $contents = file_get_contents($file->getRealPath());
            if ($contents === false || $contents === '') {
                throw ValidationException::withMessages([
                    'data.uploads' => 'Uploaded Source asset could not be read.',
                ]);
            }

            $asset = app(StageSourceAsset::class)->handle(
                futureSourceId: $draft->current->source->id,
                input: new StoreSourceAssetInput(
                    contents: $contents,
                    originalFilename: $file->getClientOriginalName(),
                    mimeType: $file->getMimeType() ?: 'application/octet-stream',
                    retrievedAt: new DateTimeImmutable,
                    provenance: ['origin' => 'manual_upload'],
                ),
            );
            $attachAssetIds[] = $asset->id;
        }

        $actorId = auth()->id();
        $finalDraft = $draft->withChanges(
            changes: $this->combinedChanges(
                sourceChanges: $sourceChanges,
                structuredChanges: $structuredChanges,
                attachAssetIds: $attachAssetIds,
                detachAssetIds: $detachAssetIds,
            ),
            changedBy: $actorId === null ? null : (string) $actorId,
        );

        try {
            $result = app(SaveSourceDraft::class)->handle($finalDraft);
        } catch (InvalidSourceDraft $exception) {
            $this->reportDraftValidation($exception->validation);

            return;
        } catch (SourceDraftConflict $exception) {
            $this->reportConflict($exception);

            return;
        }

        $notification = Notification::make()
            ->title($result->changed ? __('ui.workspace.saved') : __('ui.workspace.no_changes'))
            ->body($result->changed
                ? __('ui.workspace.saved_body')
                : __('ui.workspace.no_changes_body'));

        if ($result->changed) {
            $notification->success();
        } else {
            $notification->info();
        }

        $notification->send();

        $parameters = [
            'source' => $result->draft->current->source->id->value,
        ];
        $templateId = $this->selectedTemplateId();
        if ($templateId !== null) {
            $parameters['template'] = $templateId;
        }

        $this->redirect(static::getUrl($parameters));
    }

    /**
     * @param  list<SourceAssetId>  $attachAssetIds
     * @param  list<SourceAssetId>  $detachAssetIds
     */
    private function combinedChanges(
        SourceDraftSourceChanges $sourceChanges,
        SourceDraftChanges $structuredChanges,
        array $attachAssetIds = [],
        array $detachAssetIds = [],
    ): SourceDraftChanges {
        return new SourceDraftChanges(
            source: $sourceChanges,
            attachAssetIds: $attachAssetIds,
            detachAssetIds: $detachAssetIds,
            addMentions: $structuredChanges->addMentions,
            updateMentions: $structuredChanges->updateMentions,
            removeMentionIds: $structuredChanges->removeMentionIds,
            addClaims: $structuredChanges->addClaims,
            updateClaims: $structuredChanges->updateClaims,
            removeClaimIds: $structuredChanges->removeClaimIds,
            addLocators: $structuredChanges->addLocators,
            updateLocators: $structuredChanges->updateLocators,
            removeLocatorIds: $structuredChanges->removeLocatorIds,
        );
    }

    private function draftForSave(): SourceDraft
    {
        if ($this->sourceId === null) {
            return app(LoadSourceDraft::class)->blank(SourceType::generic());
        }

        $loaded = app(LoadSourceDraft::class)->handle(new SourceId($this->sourceId));
        $baseState = $this->restoreBaseState();
        if ($loaded->baseState === null || ! $baseState->matches($loaded->baseState)) {
            throw SourceDraftConflict::stale();
        }

        return new SourceDraft(
            current: $loaded->current,
            baseState: $baseState,
            changes: SourceDraftChanges::none(),
            isNew: false,
        );
    }

    /** @param  array<string, mixed>  $data */
    private function sourceChanges(SourceDraft $draft, array $data): SourceDraftSourceChanges
    {
        $name = $data['name'] ?? null;
        $typeKey = $data['source_type_key'] ?? null;
        $schemaVersion = $data['source_type_schema_version'] ?? null;

        if ($name !== null && ! is_string($name)) {
            throw ValidationException::withMessages([
                'data.name' => 'Source name must be text.',
            ]);
        }

        if (! is_string($typeKey) || (! is_int($schemaVersion) && ! is_numeric($schemaVersion))) {
            throw ValidationException::withMessages([
                'data.source_type_key' => 'Source type and schema version are required.',
            ]);
        }

        [$addTexts, $updateTexts, $removeTextIds] = $this->textChanges($draft, $data);

        return new SourceDraftSourceChanges(
            type: new SourceType(trim($typeKey), (int) $schemaVersion),
            metadata: new SourceMetadata($this->metadataValues($draft, $data)),
            addTexts: $addTexts,
            updateTexts: $updateTexts,
            removeTextIds: $removeTextIds,
            name: $name,
            replaceName: true,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: list<SourceText>, 1: list<SourceText>, 2: list<SourceTextId>}
     */
    private function textChanges(SourceDraft $draft, array $data): array
    {
        $current = [];
        foreach ($draft->current->source->texts as $text) {
            $current[$text->id->value] = $text;
        }

        $add = [];
        $update = [];
        $seen = [];
        $rows = $data['texts'] ?? [];

        if (! is_array($rows)) {
            throw ValidationException::withMessages(['sourceTexts' => 'Source text must be a list.']);
        }

        foreach (array_values($rows) as $index => $row) {
            if (! is_array($row)) {
                throw ValidationException::withMessages(["sourceTexts.$index" => 'Invalid Source text row.']);
            }

            $id = $row['id'] ?? null;
            $kind = $row['kind'] ?? null;
            $content = $row['content'] ?? null;
            $language = $row['language'] ?? null;

            if (! is_string($kind) || ! is_string($content)) {
                throw ValidationException::withMessages(["sourceTexts.$index" => 'Source text kind and content are required.']);
            }

            $language = is_string($language) && trim($language) !== '' ? trim($language) : null;
            $textId = null;

            if (is_string($id) && $id !== '') {
                if (! isset($current[$id]) || isset($seen[$id])) {
                    throw ValidationException::withMessages(["sourceTexts.$index.id" => 'Source text identity is invalid.']);
                }
                $textId = new SourceTextId($id);
                $seen[$id] = true;
            }

            try {
                $text = new SourceText(
                    id: $textId ?? app(SourceIdentifierGenerator::class)->sourceTextId(),
                    kind: SourceTextKind::from($kind),
                    content: $content,
                    language: $language,
                );
            } catch (InvalidArgumentException|\ValueError $exception) {
                throw ValidationException::withMessages(["sourceTexts.$index" => $exception->getMessage()]);
            }

            if ($textId === null) {
                $add[] = $text;
            } else {
                $update[] = $text;
            }
        }

        $remove = [];
        foreach ($current as $id => $text) {
            if (! isset($seen[$id])) {
                $remove[] = $text->id;
            }
        }

        return [$add, $update, $remove];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function metadataValues(SourceDraft $draft, array $data): array
    {
        $values = [];
        foreach ($draft->current->source->metadata->toArray() as $key => $value) {
            if (is_array($value)) {
                $values[$key] = $value;
            }
        }

        $rows = $data['metadata'] ?? [];
        if (! is_array($rows)) {
            throw ValidationException::withMessages(['data.metadata' => 'Source metadata must be a list.']);
        }

        foreach (array_values($rows) as $index => $row) {
            if (! is_array($row)) {
                throw ValidationException::withMessages(["data.metadata.$index" => 'Invalid metadata row.']);
            }

            $key = $row['key'] ?? null;
            $type = $row['type'] ?? null;
            $rawValue = $row['value'] ?? null;

            if (! is_string($key) || trim($key) === '' || ! is_string($type)) {
                throw ValidationException::withMessages(["data.metadata.$index" => 'Metadata key and type are required.']);
            }

            $key = trim($key);
            if (array_key_exists($key, $values)) {
                throw ValidationException::withMessages([
                    "data.metadata.$index.key" => 'Metadata keys must be unique and cannot replace preserved structured metadata.',
                ]);
            }

            $values[$key] = $this->metadataValue($type, $rawValue, $index);
        }

        return $values;
    }

    private function metadataValue(string $type, mixed $rawValue, int $index): string|int|float|bool|null
    {
        $value = is_scalar($rawValue) ? trim((string) $rawValue) : '';

        return match ($type) {
            'string' => $value,
            'integer' => preg_match('/^-?\d+$/D', $value) === 1
                ? (int) $value
                : throw ValidationException::withMessages(["data.metadata.$index.value" => 'Enter a valid integer.']),
            'number' => is_numeric($value)
                ? (float) $value
                : throw ValidationException::withMessages(["data.metadata.$index.value" => 'Enter a valid number.']),
            'boolean' => match (strtolower($value)) {
                'true', '1', 'yes' => true,
                'false', '0', 'no' => false,
                default => throw ValidationException::withMessages(["data.metadata.$index.value" => 'Enter true or false.']),
            },
            'null' => null,
            default => throw ValidationException::withMessages(["data.metadata.$index.type" => 'Unsupported metadata value type.']),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<SourceAssetId>
     */
    private function detachAssetIds(array $data): array
    {
        $ids = $data['detach_asset_ids'] ?? [];
        if (! is_array($ids)) {
            throw ValidationException::withMessages(['data.detach_asset_ids' => 'Asset detach selection must be a list.']);
        }

        return array_map(
            static fn (mixed $id): SourceAssetId => new SourceAssetId((string) $id),
            array_values($ids),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<TemporaryUploadedFile>
     */
    private function uploadedFiles(array $data): array
    {
        $uploads = $data['uploads'] ?? [];
        if ($uploads instanceof TemporaryUploadedFile) {
            return [$uploads];
        }
        if (! is_array($uploads)) {
            throw ValidationException::withMessages(['data.uploads' => 'Uploaded assets are invalid.']);
        }

        $files = [];
        foreach ($uploads as $upload) {
            if (! $upload instanceof TemporaryUploadedFile) {
                throw ValidationException::withMessages(['data.uploads' => 'Uploaded assets are invalid.']);
            }
            $files[] = $upload;
        }

        return $files;
    }

    private function fillFromDraft(SourceDraft $draft): void
    {
        $metadataRows = [];
        foreach ($draft->current->source->metadata->toArray() as $key => $value) {
            if (is_array($value)) {
                continue;
            }

            $metadataRows[] = [
                'key' => $key,
                'type' => $this->metadataType($value),
                'value' => $this->metadataDisplayValue($value),
            ];
        }

        $this->sourceTexts = array_map(
            static fn (SourceText $text): array => [
                'id' => $text->id->value,
                'kind' => $text->kind->value,
                'language' => $text->language,
                'content' => $text->content,
            ],
            $draft->current->source->texts,
        );

        $this->assetChoices = [];
        $this->assetPreviews = [];
        foreach ($draft->current->assets as $asset) {
            $this->assetChoices[$asset->id->value] = sprintf(
                '%s · %s · %s · SHA-256 %s',
                $asset->originalFilename,
                $asset->mimeType,
                $this->formatBytes($asset->byteSize),
                $asset->sha256,
            );
            $this->assetPreviews[] = [
                'id' => $asset->id->value,
                'filename' => $asset->originalFilename,
                'mime_type' => $asset->mimeType,
                'size' => $this->formatBytes($asset->byteSize),
                'url' => route('acquisition.source-assets.show', [
                    'source' => $draft->current->source->id->value,
                    'asset' => $asset->id->value,
                ]),
            ];
        }

        try {
            $structuredState = app(StructuredAcquisitionFormAdapter::class)->stateFromDraft($draft);
        } catch (InvalidArgumentException $exception) {
            abort(409, $exception->getMessage());
        }

        $this->sourceTypeContext = app(SourceTypePresentationCatalog::class)->display($draft->current->source->type);
        $this->form->fill([
            'name' => $draft->current->source->name,
            'source_type_key' => $draft->current->source->type->key,
            'source_type_schema_version' => $draft->current->source->type->schemaVersion,
            'template_id' => null,
            'metadata' => $metadataRows,
            'detach_asset_ids' => [],
            'uploads' => [],
        ]);
        $this->evidenceForm->fill([
            'add_supported_field' => null,
            ...$structuredState,
        ]);
    }

    private function applyRequestedTemplate(): void
    {
        $requestedTemplate = request()->query('template');
        if (is_string($requestedTemplate) && trim($requestedTemplate) !== '') {
            $this->templateChanged($requestedTemplate);
        }
    }

    /** @return array<string, string> */
    private function sourceTypeOptions(): array
    {
        return app(SourceTypePresentationCatalog::class)->options($this->currentFormSourceType());
    }

    /** @return array<string, string> */
    private function templateOptions(): array
    {
        $sourceType = $this->currentFormSourceType();
        if ($sourceType === null) {
            return [];
        }

        $options = [];
        foreach (app(ListSourceTypeTemplates::class)->handle(activeOnly: true, compatibleSourceType: $sourceType) as $template) {
            $options[$template->templateId->value] = sprintf(
                '%s · v%d%s',
                $template->definition->name,
                $template->version,
                $template->definition->description === null ? '' : ' · '.$template->definition->description,
            );
        }

        return $options;
    }

    private function findSelectableTemplate(string $templateId): ?SourceTypeTemplateVersion
    {
        $sourceType = $this->currentFormSourceType();
        if ($sourceType === null) {
            return null;
        }

        foreach (app(ListSourceTypeTemplates::class)->handle(activeOnly: true, compatibleSourceType: $sourceType) as $template) {
            if ($template->templateId->value === $templateId) {
                return $template;
            }
        }

        return null;
    }

    private function currentFormSourceType(): ?SourceType
    {
        $state = is_array($this->data) ? $this->data : [];
        $key = $state['source_type_key'] ?? null;
        $version = $state['source_type_schema_version'] ?? null;
        if (! is_string($key) || trim($key) === '' || (! is_int($version) && ! is_numeric($version))) {
            return null;
        }

        try {
            return new SourceType(trim($key), (int) $version);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    private function selectedTemplateId(): ?string
    {
        $state = is_array($this->data) ? $this->data : [];

        return $this->optionalString($state['template_id'] ?? null);
    }

    private function optionalString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function metadataType(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            is_bool($value) => 'boolean',
            is_int($value) => 'integer',
            is_float($value) => 'number',
            default => 'string',
        };
    }

    private function metadataDisplayValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }
        if ($bytes < 1024 * 1024) {
            return number_format($bytes / 1024, 1).' KiB';
        }

        return number_format($bytes / (1024 * 1024), 1).' MiB';
    }

    /** @return array<string, mixed>|null */
    private function serializeBaseState(?SourceDraftBaseState $baseState): ?array
    {
        if ($baseState === null) {
            return null;
        }

        return [
            'source_revision_id' => $baseState->sourceRevisionId->value,
            'mention_revision_ids' => array_map(
                static fn (MentionRevisionId $id): string => $id->value,
                $baseState->mentionRevisionIds,
            ),
            'claim_revision_ids' => array_map(
                static fn (ClaimRevisionId $id): string => $id->value,
                $baseState->claimRevisionIds,
            ),
            'evidence_state_id' => $baseState->evidenceStateId?->value,
        ];
    }

    private function restoreBaseState(): SourceDraftBaseState
    {
        if ($this->baseState === null) {
            throw SourceDraftConflict::stale();
        }

        $sourceRevisionId = $this->baseState['source_revision_id'] ?? null;
        $mentionRevisionIds = $this->baseState['mention_revision_ids'] ?? null;
        $claimRevisionIds = $this->baseState['claim_revision_ids'] ?? null;
        $evidenceStateId = $this->baseState['evidence_state_id'] ?? null;

        if (! is_string($sourceRevisionId) || ! is_array($mentionRevisionIds) || ! is_array($claimRevisionIds)) {
            throw SourceDraftConflict::stale();
        }

        return SourceDraftBaseState::capture(
            sourceRevisionId: new SourceRevisionId($sourceRevisionId),
            mentionRevisionIds: array_map(
                static fn (mixed $id): MentionRevisionId => new MentionRevisionId((string) $id),
                array_values($mentionRevisionIds),
            ),
            claimRevisionIds: array_map(
                static fn (mixed $id): ClaimRevisionId => new ClaimRevisionId((string) $id),
                array_values($claimRevisionIds),
            ),
            evidenceStateId: is_string($evidenceStateId) ? new EvidenceStateId($evidenceStateId) : null,
        );
    }

    private function reportConflict(SourceDraftConflict $exception): void
    {
        $this->addError('data', $exception->getMessage());
        Notification::make()
            ->title(__('ui.workspace.changed_elsewhere'))
            ->body(__('ui.workspace.changed_elsewhere_body'))
            ->danger()
            ->send();
    }

    private function reportDraftValidation(SourceDraftValidationResult $validation): void
    {
        foreach ($validation->issues as $issue) {
            $path = str_starts_with($issue->path, 'changes.detachAssetIds')
                || str_starts_with($issue->path, 'state.assets')
                || str_starts_with($issue->path, 'state.locators')
                ? 'data.detach_asset_ids'
                : 'data';
            $message = sprintf('[%s] %s', $issue->code, $issue->message);
            $this->addError($path, $message);
        }

        Notification::make()
            ->title(__('ui.workspace.save_failed'))
            ->body(__('ui.workspace.validation_failed'))
            ->danger()
            ->send();
    }
}
