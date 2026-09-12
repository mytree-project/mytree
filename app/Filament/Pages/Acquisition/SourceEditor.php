<?php

declare(strict_types=1);

namespace App\Filament\Pages\Acquisition;

use App\Application\Acquisition\BrowseSources;
use App\Application\Acquisition\InvalidSourceDraft;
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
use App\Application\Acquisition\StageSourceAsset;
use App\Application\Acquisition\StoreSourceAssetInput;
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
use DateTimeImmutable;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

/**
 * @property-read Schema $form
 */
final class SourceEditor extends Page
{
    protected static ?string $slug = 'acquisition/source';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.acquisition.source-editor';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public ?string $sourceId = null;

    public ?int $revisionNumber = null;

    public string $sourceTypeContext = 'generic@1';

    /** @var array<string, string> */
    public array $assetChoices = [];

    /** @var array<string, mixed>|null */
    public ?array $baseState = null;

    public function mount(?string $source = null): void
    {
        $requestedSource = $source ?? request()->query('source');

        if ($requestedSource === null || trim((string) $requestedSource) === '') {
            $draft = app(LoadSourceDraft::class)->blank(SourceType::generic());
            $this->fillFromDraft($draft);

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
        $this->sourceTypeContext = sprintf('%s@%d', $summary->type->key, $summary->type->schemaVersion);
        $this->baseState = $this->serializeBaseState($draft->baseState);
        $this->fillFromDraft($draft);
    }

    public function getTitle(): string
    {
        return $this->sourceId === null ? 'Create Source' : 'Edit Source';
    }

    public function getSubheading(): string
    {
        if ($this->sourceId === null) {
            return sprintf('%s · blank workspace · unsaved', $this->sourceTypeContext);
        }

        return sprintf(
            'Source %s · %s · revision %d · persisted',
            $this->sourceId,
            $this->sourceTypeContext,
            $this->revisionNumber ?? 0,
        );
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('source_type_key')
                    ->label('Source type')
                    ->helperText('Use generic when no Source Type Template applies.')
                    ->required()
                    ->maxLength(120)
                    ->rules(['regex:/^[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)*$/']),
                TextInput::make('source_type_schema_version')
                    ->label('Source type schema version')
                    ->numeric()
                    ->integer()
                    ->minValue(1)
                    ->required(),
                Repeater::make('metadata')
                    ->label('Source metadata')
                    ->helperText('Scalar metadata is editable here. Structured array metadata is preserved unchanged.')
                    ->schema([
                        TextInput::make('key')
                            ->required()
                            ->maxLength(120),
                        Select::make('type')
                            ->required()
                            ->options([
                                'string' => 'Text',
                                'integer' => 'Integer',
                                'number' => 'Number',
                                'boolean' => 'Boolean',
                                'null' => 'Null',
                            ]),
                        TextInput::make('value')
                            ->helperText('For Boolean use true or false. Null ignores this value.'),
                    ])
                    ->columns(3)
                    ->defaultItems(0)
                    ->addActionLabel('Add metadata field'),
                Repeater::make('texts')
                    ->label('Source text')
                    ->helperText('Translation and summary are derived representations and remain distinct from transcription.')
                    ->schema([
                        Hidden::make('id'),
                        Select::make('kind')
                            ->required()
                            ->options([
                                SourceTextKind::Transcription->value => 'Transcription (source representation)',
                                SourceTextKind::Translation->value => 'Translation (derived)',
                                SourceTextKind::Summary->value => 'Summary (derived)',
                                SourceTextKind::ResearchNote->value => 'Research note',
                            ]),
                        TextInput::make('language')
                            ->maxLength(35)
                            ->rules(['nullable', 'regex:/^[A-Za-z]{2,8}(?:-[A-Za-z0-9]{1,8})*$/']),
                        Textarea::make('content')
                            ->required()
                            ->rows(8)
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->addActionLabel('Add source text'),
                CheckboxList::make('detach_asset_ids')
                    ->label('Attached assets')
                    ->helperText('Select an existing asset only when it should be detached from the current Source. Stored bytes are not purged.')
                    ->options(fn (): array => $this->assetChoices)
                    ->columns(1),
                FileUpload::make('uploads')
                    ->label('Attach new assets')
                    ->helperText('Uploads are staged through the SourceAsset storage boundary and attached by the atomic SourceDraft save.')
                    ->multiple()
                    ->storeFiles(false)
                    ->previewable(false),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        /** @var array<string, mixed> $data */
        $data = $this->form->getState();

        try {
            $draft = $this->draftForSave();
        } catch (SourceDraftConflict $exception) {
            $this->reportConflict($exception);

            return;
        }

        try {
            $sourceChanges = $this->sourceChanges($draft, $data);
            $detachAssetIds = $this->detachAssetIds($data);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw ValidationException::withMessages([
                'data' => $exception->getMessage(),
            ]);
        }

        $preflight = $draft->withChanges(new SourceDraftChanges(
            source: $sourceChanges,
            detachAssetIds: $detachAssetIds,
        ));
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
            changes: new SourceDraftChanges(
                source: $sourceChanges,
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
            ->title($result->changed ? 'Source saved' : 'No Source changes')
            ->body($result->changed ? 'The Source revision and EvidenceState were updated atomically.' : 'No semantic change was detected, so no new revision was created.');

        if ($result->changed) {
            $notification->success();
        } else {
            $notification->info();
        }

        $notification->send();

        $this->redirect(self::getUrl([
            'source' => $result->draft->current->source->id->value,
        ]));
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
        $typeKey = $data['source_type_key'] ?? null;
        $schemaVersion = $data['source_type_schema_version'] ?? null;

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
            throw ValidationException::withMessages(['data.texts' => 'Source text must be a list.']);
        }

        foreach (array_values($rows) as $index => $row) {
            if (! is_array($row)) {
                throw ValidationException::withMessages(["data.texts.$index" => 'Invalid Source text row.']);
            }

            $id = $row['id'] ?? null;
            $kind = $row['kind'] ?? null;
            $content = $row['content'] ?? null;
            $language = $row['language'] ?? null;

            if (! is_string($kind) || ! is_string($content)) {
                throw ValidationException::withMessages(["data.texts.$index" => 'Source text kind and content are required.']);
            }

            $language = is_string($language) && trim($language) !== '' ? trim($language) : null;
            $textId = null;

            if (is_string($id) && $id !== '') {
                if (! isset($current[$id]) || isset($seen[$id])) {
                    throw ValidationException::withMessages(["data.texts.$index.id" => 'Source text identity is invalid.']);
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
                throw ValidationException::withMessages(["data.texts.$index" => $exception->getMessage()]);
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
                throw ValidationException::withMessages(["data.metadata.$index.key" => 'Metadata keys must be unique and cannot replace preserved structured metadata.']);
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
            if (! ($upload instanceof TemporaryUploadedFile)) {
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

        $textRows = array_map(
            static fn (SourceText $text): array => [
                'id' => $text->id->value,
                'kind' => $text->kind->value,
                'language' => $text->language,
                'content' => $text->content,
            ],
            $draft->current->source->texts,
        );

        $this->assetChoices = [];
        foreach ($draft->current->assets as $asset) {
            $this->assetChoices[$asset->id->value] = sprintf(
                '%s · %s · %s · SHA-256 %s',
                $asset->originalFilename,
                $asset->mimeType,
                $this->formatBytes($asset->byteSize),
                $asset->sha256,
            );
        }

        $this->form->fill([
            'source_type_key' => $draft->current->source->type->key,
            'source_type_schema_version' => $draft->current->source->type->schemaVersion,
            'metadata' => $metadataRows,
            'texts' => $textRows,
            'detach_asset_ids' => [],
            'uploads' => [],
        ]);
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
            ->title('Source changed elsewhere')
            ->body('Reload the Source before saving again.')
            ->danger()
            ->send();
    }

    private function reportDraftValidation(SourceDraftValidationResult $validation): void
    {
        $firstMessage = 'SourceDraft validation failed.';

        foreach ($validation->issues as $issue) {
            $path = str_starts_with($issue->path, 'changes.detachAssetIds')
                || str_starts_with($issue->path, 'state.assets')
                || str_starts_with($issue->path, 'state.locators')
                ? 'data.detach_asset_ids'
                : 'data';
            $message = sprintf('[%s] %s', $issue->code, $issue->message);
            $this->addError($path, $message);
            $firstMessage = $message;
        }

        Notification::make()
            ->title('Source could not be saved')
            ->body($firstMessage)
            ->danger()
            ->send();
    }
}
