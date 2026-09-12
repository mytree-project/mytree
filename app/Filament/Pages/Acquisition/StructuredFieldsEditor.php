<?php

declare(strict_types=1);

namespace App\Filament\Pages\Acquisition;

use App\Application\Acquisition\InvalidSourceDraft;
use App\Application\Acquisition\LoadSourceDraft;
use App\Application\Acquisition\SaveSourceDraft;
use App\Application\Acquisition\SourceDraft;
use App\Application\Acquisition\SourceDraftBaseState;
use App\Application\Acquisition\SourceDraftChanges;
use App\Application\Acquisition\SourceDraftConflict;
use App\Application\Acquisition\SourceDraftValidationResult;
use App\Application\Acquisition\SourceNotFound;
use App\Application\Acquisition\SupportedAcquisitionClaimInput;
use App\Application\Acquisition\SupportedAcquisitionDraftEditor;
use App\Application\Acquisition\SupportedAcquisitionEditInput;
use App\Application\Acquisition\SupportedAcquisitionEventContextInput;
use App\Application\Acquisition\SupportedAcquisitionFieldCatalog;
use App\Application\Acquisition\SupportedAcquisitionFieldDescriptor;
use App\Application\Acquisition\SupportedAcquisitionFieldEditorKind;
use App\Application\Acquisition\SupportedAcquisitionFieldValueInput;
use App\Application\Acquisition\SupportedAcquisitionMentionInput;
use App\Application\Acquisition\ValidateSourceDraft;
use App\Domain\Acquisition\Claim;
use App\Domain\Acquisition\ClaimId;
use App\Domain\Acquisition\ClaimRevisionId;
use App\Domain\Acquisition\ClaimValue;
use App\Domain\Acquisition\ClaimValueType;
use App\Domain\Acquisition\EvidenceStateId;
use App\Domain\Acquisition\Mention;
use App\Domain\Acquisition\MentionId;
use App\Domain\Acquisition\MentionKind;
use App\Domain\Acquisition\MentionRawData;
use App\Domain\Acquisition\MentionRevisionId;
use App\Domain\Acquisition\SourceId;
use App\Domain\Acquisition\SourceRevisionId;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use JsonException;
use Throwable;

/**
 * Generic controlled structured-field adapter over SourceDraft Mention/Claim graph state.
 *
 * @property-read Schema $form
 */
final class StructuredFieldsEditor extends Page
{
    protected static ?string $slug = 'acquisition/structured-fields';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.acquisition.structured-fields-editor';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public string $sourceId = '';

    /** @var array<string, mixed>|null */
    public ?array $baseState = null;

    public function mount(?string $source = null): void
    {
        $requestedSource = $source ?? request()->query('source');
        if ($requestedSource === null || trim((string) $requestedSource) === '') {
            abort(404);
        }

        try {
            $sourceId = new SourceId((string) $requestedSource);
            $draft = app(LoadSourceDraft::class)->handle($sourceId);
        } catch (InvalidArgumentException|SourceNotFound) {
            abort(404);
        }

        $this->sourceId = $sourceId->value;
        $this->baseState = $this->serializeBaseState($draft->baseState);
        $this->fillFromDraft($draft);
    }

    public function getTitle(): string
    {
        return 'Structured Source Fields';
    }

    public function getSubheading(): string
    {
        return sprintf(
            'Source %s · Mention/Claim graph · controlled field catalog v%d',
            $this->sourceId,
            SupportedAcquisitionFieldCatalog::SCHEMA_VERSION,
        );
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Repeater::make('mentions')
                    ->label('Source-local Mentions')
                    ->helperText('Create or edit person/place/organization/other occurrences. Raw data remains available without forced semantic classification.')
                    ->schema([
                        Hidden::make('id'),
                        Select::make('kind')
                            ->required()
                            ->options([
                                MentionKind::PERSON => 'Person',
                                MentionKind::PLACE => 'Place',
                                MentionKind::ORGANIZATION => 'Organization',
                                MentionKind::OTHER => 'Other',
                            ]),
                        TextInput::make('local_key')
                            ->label('Local key')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('role')
                            ->maxLength(120),
                        TextInput::make('display_label')
                            ->label('Display label'),
                        Textarea::make('raw_data_json')
                            ->label('Raw source-local data (JSON object)')
                            ->rows(4)
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->addActionLabel('Add source-local Mention'),
                Repeater::make('fields')
                    ->label('Structured fields')
                    ->helperText('Add any registered direct field. Subject/object references use Mention local keys from this Source.')
                    ->schema($this->directFieldSchema(includeSubject: true))
                    ->columns(2)
                    ->defaultItems(0)
                    ->addActionLabel('Add supported field'),
                Repeater::make('event_contexts')
                    ->label('Reified event contexts')
                    ->helperText('Each group is one source-local event Mention with atomic event Claims.')
                    ->schema([
                        Hidden::make('id'),
                        TextInput::make('local_key')
                            ->label('Event local key')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('role')
                            ->label('Event role')
                            ->maxLength(120),
                        TextInput::make('display_label')
                            ->label('Event label'),
                        Textarea::make('raw_data_json')
                            ->label('Event raw data (JSON object)')
                            ->rows(3)
                            ->columnSpanFull(),
                        Repeater::make('claims')
                            ->label('Event facts / participant roles')
                            ->schema($this->directFieldSchema(includeSubject: false, eventOnly: true))
                            ->columns(2)
                            ->defaultItems(0)
                            ->addActionLabel('Add event field')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->addActionLabel('Add event context'),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        /** @var array<string, mixed> $data */
        $data = $this->form->getState();

        try {
            $draft = $this->draftForSave();
            $input = $this->editInput($data);
            $changes = app(SupportedAcquisitionDraftEditor::class)->changes($draft, $input);
        } catch (SourceDraftConflict $exception) {
            $this->reportConflict($exception);

            return;
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw ValidationException::withMessages([
                'data' => $exception->getMessage(),
            ]);
        }

        $actorId = auth()->id();
        $changedDraft = $draft->withChanges(
            changes: $changes,
            changedBy: $actorId === null ? null : (string) $actorId,
        );
        $validation = app(ValidateSourceDraft::class)->handle($changedDraft);

        if (! $validation->isValid()) {
            $this->reportDraftValidation($validation);

            return;
        }

        try {
            $result = app(SaveSourceDraft::class)->handle($changedDraft);
        } catch (InvalidSourceDraft $exception) {
            $this->reportDraftValidation($exception->validation);

            return;
        } catch (SourceDraftConflict $exception) {
            $this->reportConflict($exception);

            return;
        }

        $notification = Notification::make()
            ->title($result->changed ? 'Structured fields saved' : 'No structured changes')
            ->body($result->changed
                ? 'Mention/Claim revisions and the resulting EvidenceState were updated atomically.'
                : 'No semantic change was detected, so no new revisions were created.');

        if ($result->changed) {
            $notification->success();
        } else {
            $notification->info();
        }
        $notification->send();

        $this->redirect(self::getUrl(['source' => $this->sourceId]));
    }

    /** @return list<Component> */
    private function directFieldSchema(bool $includeSubject, bool $eventOnly = false): array
    {
        $schema = [
            Hidden::make('claim_id'),
            Select::make('field_key')
                ->label('Supported field')
                ->required()
                ->options($this->fieldOptions($eventOnly)),
        ];

        if ($includeSubject) {
            $schema[] = TextInput::make('subject_local_key')
                ->label('Subject Mention local key')
                ->required()
                ->maxLength(255);
        }

        $schema[] = TextInput::make('object_local_key')
            ->label('Object Mention local key')
            ->helperText('Used by relationship/place fields only.')
            ->maxLength(255);
        $schema[] = TextInput::make('value_raw')
            ->label('Raw/source value')
            ->helperText('Used by literal fields and preserved exactly as entered.');
        $schema[] = Select::make('expression_kind')
            ->label('Date / age expression')
            ->options([
                'exact' => 'Exact',
                'approximate' => 'Approximate',
                'range' => 'Range',
                'uncertain' => 'Uncertain',
            ]);
        $schema[] = TextInput::make('value_from')
            ->label('Date/age from')
            ->helperText('Date: YYYY, YYYY-MM or YYYY-MM-DD. Age: non-negative integer.');
        $schema[] = TextInput::make('value_to')
            ->label('Date/age to')
            ->helperText('Required only for a range.');
        $schema[] = Select::make('age_unit')
            ->label('Age unit')
            ->options([
                'years' => 'Years',
                'months' => 'Months',
                'days' => 'Days',
            ]);
        $schema[] = TextInput::make('integer_value')
            ->label('Parsed integer')
            ->numeric()
            ->integer();
        $schema[] = Select::make('boolean_value')
            ->label('Parsed boolean')
            ->options([
                '1' => 'True',
                '0' => 'False',
            ]);
        $schema[] = TextInput::make('enum_key')
            ->label('Controlled enum key');
        $schema[] = TextInput::make('effective_time_raw')
            ->label('Effective time raw/source value')
            ->helperText('Optional explicit Claim effective time/period; blank means no temporal qualifier.');
        $schema[] = Select::make('effective_time_kind')
            ->label('Effective time expression')
            ->options([
                'exact' => 'Exact',
                'approximate' => 'Approximate',
                'range' => 'Range',
                'uncertain' => 'Uncertain',
            ]);
        $schema[] = TextInput::make('effective_time_from')
            ->label('Effective time from')
            ->helperText('YYYY, YYYY-MM or YYYY-MM-DD.');
        $schema[] = TextInput::make('effective_time_to')
            ->label('Effective time to');
        $schema[] = Textarea::make('raw_text')
            ->label('Supporting raw text')
            ->rows(2)
            ->columnSpanFull();
        $schema[] = TextInput::make('transcription_certainty')
            ->label('Transcription certainty')
            ->helperText('Stable lowercase code, e.g. unspecified, certain, uncertain.')
            ->default('unspecified');
        $schema[] = TextInput::make('interpretation_certainty')
            ->label('Interpretation certainty')
            ->helperText('Stable lowercase code; independent from transcription certainty.')
            ->default('unspecified');

        return $schema;
    }

    /** @return array<string, string> */
    private function fieldOptions(bool $eventOnly): array
    {
        $options = [];
        foreach (app(SupportedAcquisitionFieldCatalog::class)->all() as $descriptor) {
            if (! $descriptor->isDirectClaim()) {
                continue;
            }

            if ($eventOnly !== ($descriptor->subjectMentionKind === MentionKind::EVENT)) {
                continue;
            }

            $options[$descriptor->key] = sprintf('%s · %s', $descriptor->group, $descriptor->label);
        }

        return $options;
    }

    private function draftForSave(): SourceDraft
    {
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
    private function editInput(array $data): SupportedAcquisitionEditInput
    {
        $mentionRows = $data['mentions'] ?? [];
        $fieldRows = $data['fields'] ?? [];
        $eventRows = $data['event_contexts'] ?? [];
        if (! is_array($mentionRows) || ! is_array($fieldRows) || ! is_array($eventRows)) {
            throw ValidationException::withMessages(['data' => 'Structured editor state must contain lists.']);
        }

        $mentions = [];
        foreach (array_values($mentionRows) as $index => $row) {
            if (! is_array($row)) {
                throw ValidationException::withMessages(["data.mentions.$index" => 'Invalid Mention row.']);
            }
            $mentions[] = $this->mentionInput($row, null, "data.mentions.$index");
        }

        $fields = [];
        foreach (array_values($fieldRows) as $index => $row) {
            if (! is_array($row)) {
                throw ValidationException::withMessages(["data.fields.$index" => 'Invalid structured field row.']);
            }
            $fields[] = $this->claimInput($row, null, "data.fields.$index");
        }

        $eventContexts = [];
        foreach (array_values($eventRows) as $eventIndex => $eventRow) {
            if (! is_array($eventRow)) {
                throw ValidationException::withMessages(["data.event_contexts.$eventIndex" => 'Invalid event context row.']);
            }

            $event = $this->mentionInput($eventRow, MentionKind::EVENT, "data.event_contexts.$eventIndex");
            $claimRows = $eventRow['claims'] ?? [];
            if (! is_array($claimRows)) {
                throw ValidationException::withMessages(["data.event_contexts.$eventIndex.claims" => 'Event Claims must be a list.']);
            }

            $claims = [];
            foreach (array_values($claimRows) as $claimIndex => $claimRow) {
                if (! is_array($claimRow)) {
                    throw ValidationException::withMessages(["data.event_contexts.$eventIndex.claims.$claimIndex" => 'Invalid event Claim row.']);
                }
                $claims[] = $this->claimInput(
                    $claimRow,
                    $event->localKey,
                    "data.event_contexts.$eventIndex.claims.$claimIndex",
                );
            }

            $eventContexts[] = new SupportedAcquisitionEventContextInput($event, $claims);
        }

        return new SupportedAcquisitionEditInput($mentions, $fields, $eventContexts);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function mentionInput(array $row, ?string $forcedKind, string $path): SupportedAcquisitionMentionInput
    {
        $id = $row['id'] ?? null;
        $kind = $forcedKind ?? ($row['kind'] ?? null);
        $localKey = $row['local_key'] ?? null;
        if (! is_string($kind) || ! is_string($localKey) || trim($localKey) === '') {
            throw ValidationException::withMessages([$path => 'Mention kind and local key are required.']);
        }

        try {
            return new SupportedAcquisitionMentionInput(
                id: is_string($id) && $id !== '' ? new MentionId($id) : null,
                kind: new MentionKind($kind),
                localKey: trim($localKey),
                role: $this->optionalString($row['role'] ?? null),
                displayLabel: $this->optionalString($row['display_label'] ?? null),
                rawData: new MentionRawData($this->rawData($row['raw_data_json'] ?? null, "$path.raw_data_json")),
            );
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([$path => $exception->getMessage()]);
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function claimInput(array $row, ?string $forcedSubjectLocalKey, string $path): SupportedAcquisitionClaimInput
    {
        $fieldKey = $row['field_key'] ?? null;
        $subjectLocalKey = $forcedSubjectLocalKey ?? ($row['subject_local_key'] ?? null);
        if (! is_string($fieldKey) || ! is_string($subjectLocalKey) || trim($subjectLocalKey) === '') {
            throw ValidationException::withMessages([$path => 'Supported field and subject Mention are required.']);
        }

        try {
            $descriptor = app(SupportedAcquisitionFieldCatalog::class)->get($fieldKey);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(["$path.field_key" => $exception->getMessage()]);
        }

        $claimId = $row['claim_id'] ?? null;
        $value = $descriptor->editorKind === SupportedAcquisitionFieldEditorKind::MentionReference
            ? null
            : $this->literalInput($descriptor, $row, $path);

        return new SupportedAcquisitionClaimInput(
            id: is_string($claimId) && $claimId !== '' ? new ClaimId($claimId) : null,
            fieldKey: $fieldKey,
            subjectLocalKey: trim($subjectLocalKey),
            objectLocalKey: $this->optionalString($row['object_local_key'] ?? null),
            value: $value,
            effectiveTime: $this->effectiveTimeInput($row, $path),
            rawText: $this->optionalString($row['raw_text'] ?? null),
            transcriptionCertainty: $this->optionalString($row['transcription_certainty'] ?? null) ?? 'unspecified',
            interpretationCertainty: $this->optionalString($row['interpretation_certainty'] ?? null) ?? 'unspecified',
        );
    }

    /** @param  array<string, mixed>  $row */
    private function literalInput(
        SupportedAcquisitionFieldDescriptor $descriptor,
        array $row,
        string $path,
    ): SupportedAcquisitionFieldValueInput {
        $raw = $row['value_raw'] ?? null;
        if (! is_string($raw) || trim($raw) === '') {
            throw ValidationException::withMessages(["$path.value_raw" => 'Literal fields require the raw/source value.']);
        }

        return new SupportedAcquisitionFieldValueInput(
            rawValue: $raw,
            expressionKind: $this->optionalString($row['expression_kind'] ?? null),
            from: $this->stringOrInt($row['value_from'] ?? null),
            to: $this->stringOrInt($row['value_to'] ?? null),
            ageUnit: $this->optionalString($row['age_unit'] ?? null),
            integerValue: $this->integerOrNull($row['integer_value'] ?? null),
            booleanValue: $this->booleanOrNull($row['boolean_value'] ?? null),
            enumKey: $this->optionalString($row['enum_key'] ?? null),
        );
    }

    /** @param  array<string, mixed>  $row */
    private function effectiveTimeInput(array $row, string $path): ?SupportedAcquisitionFieldValueInput
    {
        $raw = $this->optionalString($row['effective_time_raw'] ?? null);
        $kind = $this->optionalString($row['effective_time_kind'] ?? null);
        $from = $this->optionalString($row['effective_time_from'] ?? null);
        $to = $this->optionalString($row['effective_time_to'] ?? null);

        if ($raw === null && $kind === null && $from === null && $to === null) {
            return null;
        }
        if ($raw === null || $kind === null || $from === null) {
            throw ValidationException::withMessages(["$path.effective_time_raw" => 'Effective time requires raw value, expression kind and start date.']);
        }

        return new SupportedAcquisitionFieldValueInput(
            rawValue: $raw,
            expressionKind: $kind,
            from: $from,
            to: $to,
        );
    }

    /** @return array<string, mixed> */
    private function rawData(mixed $value, string $path): array
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return [];
        }
        if (! is_string($value)) {
            throw ValidationException::withMessages([$path => 'Mention raw data must be a JSON object.']);
        }

        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw ValidationException::withMessages([$path => $exception->getMessage()]);
        }

        if (! is_array($decoded) || ($decoded !== [] && array_is_list($decoded))) {
            throw ValidationException::withMessages([$path => 'Mention raw data must be a JSON object.']);
        }

        return $decoded;
    }

    private function stringOrInt(mixed $value): string|int|null
    {
        return is_string($value) || is_int($value) ? $value : null;
    }

    private function integerOrNull(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^-?\d+$/D', trim($value)) === 1) {
            return (int) trim($value);
        }

        return null;
    }

    private function booleanOrNull(mixed $value): ?bool
    {
        return match ($value) {
            true, 1, '1' => true,
            false, 0, '0' => false,
            default => null,
        };
    }

    private function optionalString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function fillFromDraft(SourceDraft $draft): void
    {
        $mentionsById = [];
        foreach ($draft->current->mentions as $mention) {
            $mentionsById[$mention->id->value] = $mention;
        }

        $mentionRows = [];
        $eventRows = [];
        foreach ($draft->current->mentions as $mention) {
            $row = $this->mentionRow($mention);
            if ($mention->kind->key === MentionKind::EVENT) {
                $row['claims'] = [];
                $eventRows[$mention->id->value] = $row;
            } else {
                $mentionRows[] = $row;
            }
        }

        $fieldRows = [];
        $catalog = app(SupportedAcquisitionFieldCatalog::class);
        foreach ($draft->current->claims as $claim) {
            if (! $catalog->has($claim->predicate->key->value)) {
                continue;
            }

            $subject = $mentionsById[$claim->subjectMentionId->value] ?? null;
            if ($subject === null) {
                continue;
            }

            $row = $this->claimRow($claim, $mentionsById);
            if ($subject->kind->key === MentionKind::EVENT && isset($eventRows[$subject->id->value])) {
                unset($row['subject_local_key']);
                $eventRows[$subject->id->value]['claims'][] = $row;
            } else {
                $fieldRows[] = $row;
            }
        }

        $this->form->fill([
            'mentions' => $mentionRows,
            'fields' => $fieldRows,
            'event_contexts' => array_values($eventRows),
        ]);
    }

    /** @return array<string, mixed> */
    private function mentionRow(Mention $mention): array
    {
        try {
            $rawData = json_encode(
                $mention->rawData->toArray(),
                JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            );
        } catch (JsonException) {
            $rawData = '{}';
        }

        return [
            'id' => $mention->id->value,
            'kind' => $mention->kind->key,
            'local_key' => $mention->localKey,
            'role' => $mention->role,
            'display_label' => $mention->displayLabel,
            'raw_data_json' => $rawData,
        ];
    }

    /**
     * @param  array<string, Mention>  $mentionsById
     * @return array<string, mixed>
     */
    private function claimRow(Claim $claim, array $mentionsById): array
    {
        $subject = $mentionsById[$claim->subjectMentionId->value] ?? null;
        $object = $claim->objectMentionId === null ? null : ($mentionsById[$claim->objectMentionId->value] ?? null);

        $row = [
            'claim_id' => $claim->id->value,
            'field_key' => $claim->predicate->key->value,
            'subject_local_key' => $subject?->localKey,
            'object_local_key' => $object?->localKey,
            'value_raw' => $claim->value?->raw(),
            'expression_kind' => null,
            'value_from' => null,
            'value_to' => null,
            'age_unit' => null,
            'integer_value' => null,
            'boolean_value' => null,
            'enum_key' => null,
            'effective_time_raw' => null,
            'effective_time_kind' => null,
            'effective_time_from' => null,
            'effective_time_to' => null,
            'raw_text' => $claim->rawText,
            'transcription_certainty' => $claim->transcriptionCertainty->code,
            'interpretation_certainty' => $claim->interpretationCertainty->code,
        ];

        $this->fillValueRow($row, $claim->value);
        $effectiveTime = $claim->qualifiers->effectiveTime;
        if ($effectiveTime !== null) {
            $data = $effectiveTime->data();
            $row['effective_time_raw'] = $effectiveTime->raw();
            $row['effective_time_kind'] = $data['kind'] ?? null;
            $row['effective_time_from'] = $data['from'] ?? null;
            $row['effective_time_to'] = $data['to'] ?? null;
        }

        return $row;
    }

    /** @param  array<string, mixed>  $row */
    private function fillValueRow(array &$row, ?ClaimValue $value): void
    {
        if ($value === null) {
            return;
        }

        $data = $value->data();
        match ($value->type()) {
            ClaimValueType::Text => null,
            ClaimValueType::Integer => $row['integer_value'] = $data['value'] ?? null,
            ClaimValueType::Date => $this->fillDateRow($row, $data),
            ClaimValueType::Age => $this->fillAgeRow($row, $data),
            ClaimValueType::Boolean => $row['boolean_value'] = isset($data['value']) ? ((bool) $data['value'] ? '1' : '0') : null,
            ClaimValueType::Enum => $row['enum_key'] = $data['key'] ?? null,
        };
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $data
     */
    private function fillDateRow(array &$row, array $data): void
    {
        $row['expression_kind'] = $data['kind'] ?? null;
        $row['value_from'] = $data['from'] ?? null;
        $row['value_to'] = $data['to'] ?? null;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $data
     */
    private function fillAgeRow(array &$row, array $data): void
    {
        $row['expression_kind'] = $data['kind'] ?? null;
        $row['value_from'] = $data['from'] ?? null;
        $row['value_to'] = $data['to'] ?? null;
        $row['age_unit'] = $data['unit'] ?? null;
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
            ->body('Reload the Source before saving structured fields again.')
            ->danger()
            ->send();
    }

    private function reportDraftValidation(SourceDraftValidationResult $validation): void
    {
        $firstMessage = 'SourceDraft validation failed.';
        foreach ($validation->issues as $issue) {
            $message = sprintf('[%s] %s', $issue->code, $issue->message);
            $this->addError('data', $message);
            $firstMessage = $message;
        }

        Notification::make()
            ->title('Structured fields could not be saved')
            ->body($firstMessage)
            ->danger()
            ->send();
    }
}
