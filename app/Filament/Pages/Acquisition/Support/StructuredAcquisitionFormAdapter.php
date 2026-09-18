<?php

declare(strict_types=1);

namespace App\Filament\Pages\Acquisition\Support;

use App\Application\Acquisition\SourceDraft;
use App\Application\Acquisition\SupportedAcquisitionClaimInput;
use App\Application\Acquisition\SupportedAcquisitionEditInput;
use App\Application\Acquisition\SupportedAcquisitionEventContextInput;
use App\Application\Acquisition\SupportedAcquisitionFieldCatalog;
use App\Application\Acquisition\SupportedAcquisitionFieldDescriptor;
use App\Application\Acquisition\SupportedAcquisitionFieldEditorKind;
use App\Application\Acquisition\SupportedAcquisitionFieldValueInput;
use App\Application\Acquisition\SupportedAcquisitionMentionInput;
use App\Domain\Acquisition\Claim;
use App\Domain\Acquisition\ClaimId;
use App\Domain\Acquisition\ClaimValue;
use App\Domain\Acquisition\ClaimValueType;
use App\Domain\Acquisition\Mention;
use App\Domain\Acquisition\MentionId;
use App\Domain\Acquisition\MentionKind;
use App\Domain\Acquisition\MentionRawData;
use App\Filament\Support\SourceWorkspacePage;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use JsonException;
use Livewire\Component as LivewireComponent;

/**
 * Filament adapter for the controlled SourceDraft Mention/Claim graph editor.
 *
 * Presentation-only template rows are deliberately allowed to remain empty. They
 * are filtered before SupportedAcquisitionEditInput is created, so rendering a
 * field never creates a Mention or Claim by itself.
 */
final readonly class StructuredAcquisitionFormAdapter
{
    private const PRESENTATION_TEMPLATE = 'template';

    public function __construct(private SupportedAcquisitionFieldCatalog $catalog) {}

    /** @return list<Component> */
    public function components(): array
    {
        return [
            Repeater::make('mentions')
                ->label('Source-local Mentions')
                ->helperText('Create or edit person/place/organization/other occurrences. Raw wording can be preserved without forced semantic classification.')
                ->schema([
                    Hidden::make('id'),
                    Select::make('kind')
                        ->required()
                        ->options([
                            MentionKind::PERSON => 'Person',
                            MentionKind::PLACE => 'Place',
                            MentionKind::ORGANIZATION => 'Organization',
                            MentionKind::OTHER => 'Other',
                        ])
                        ->live(),
                    TextInput::make('local_key')
                        ->label('Local key')
                        ->helperText('Claim selectors store this source-local identity. If it is renamed, stale Claim references must be reselected before save.')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true),
                    TextInput::make('role')
                        ->maxLength(120),
                    TextInput::make('display_label')
                        ->label('Display label')
                        ->live(onBlur: true),
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
                ->helperText('Template defaults, existing additional data, and manually added fields share the same controlled field catalog. Empty presentation rows are not saved as Claims.')
                ->schema($this->directFieldSchema(includeSubject: true))
                ->columns(2)
                ->defaultItems(0)
                ->addActionLabel('Add another field occurrence'),
            Repeater::make('event_contexts')
                ->label('Reified event contexts')
                ->helperText('Each populated group is one source-local event Mention with atomic event Claims. An empty template-provided context is only presentation state.')
                ->schema([
                    Hidden::make('id'),
                    Hidden::make('presentation_origin'),
                    TextInput::make('local_key')
                        ->label('Event local key')
                        ->helperText('Event Claims use this source-local identity implicitly as their subject.')
                        ->maxLength(255)
                        ->live(onBlur: true),
                    TextInput::make('role')
                        ->label('Event role')
                        ->maxLength(120),
                    TextInput::make('display_label')
                        ->label('Event label')
                        ->live(onBlur: true),
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
        ];
    }

    /** @return array<string, string> */
    public function pickerOptions(): array
    {
        $options = [];
        foreach ($this->catalog->all() as $descriptor) {
            $options[$descriptor->key] = sprintf('%s · %s', $descriptor->group, $descriptor->label);
        }

        return $options;
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, string>
     */
    public function mentionPickerOptions(array $state, string $fieldKey, bool $subject): array
    {
        $descriptor = $this->catalog->get($fieldKey);
        if (! $descriptor->isDirectClaim()) {
            return [];
        }

        $requiredKind = $subject ? $descriptor->subjectMentionKind : $descriptor->objectMentionKind;
        if ($requiredKind === null) {
            return [];
        }

        $options = [];
        foreach ($this->rows($state['mentions'] ?? []) as $row) {
            if (($row['kind'] ?? null) !== $requiredKind) {
                continue;
            }

            $this->appendMentionPickerOption($options, $row);
        }

        if ($requiredKind === MentionKind::EVENT) {
            foreach ($this->rows($state['event_contexts'] ?? []) as $row) {
                $this->appendMentionPickerOption($options, $row);
            }
        }

        return $options;
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public function addSupportedField(array $state, string $fieldKey): array
    {
        $descriptor = $this->descriptor($fieldKey);

        if ($descriptor->editorKind === SupportedAcquisitionFieldEditorKind::EventContext) {
            $state['event_contexts'] = $this->rows($state['event_contexts'] ?? []);
            $state['event_contexts'][] = $this->blankEventRow();

            return $state;
        }

        if ($descriptor->subjectMentionKind === MentionKind::EVENT) {
            $state['event_contexts'] = $this->rows($state['event_contexts'] ?? []);
            $event = $this->blankEventRow();
            $event['claims'] = [$this->blankClaimRow($fieldKey)];
            $state['event_contexts'][] = $event;

            return $state;
        }

        $state['fields'] = $this->rows($state['fields'] ?? []);
        $state['fields'][] = $this->blankClaimRow($fieldKey);

        return $state;
    }

    /**
     * Apply a template as presentation state only.
     *
     * Existing persisted rows and user-populated former template rows are kept.
     * Only still-empty rows originating from the previous template are removed.
     *
     * @param  array<string, mixed>  $state
     * @param  list<string>  $defaultFieldKeys
     * @return array<string, mixed>
     */
    public function applyTemplatePresentation(array $state, array $defaultFieldKeys): array
    {
        $fields = [];
        foreach ($this->rows($state['fields'] ?? []) as $row) {
            if ($this->isRemovableTemplateClaimRow($row)) {
                continue;
            }
            if (($row['presentation_origin'] ?? null) === self::PRESENTATION_TEMPLATE) {
                $row['presentation_origin'] = null;
            }
            $fields[] = $row;
        }

        $eventContexts = [];
        foreach ($this->rows($state['event_contexts'] ?? []) as $row) {
            if ($this->isRemovableTemplateEventRow($row)) {
                continue;
            }
            if (($row['presentation_origin'] ?? null) === self::PRESENTATION_TEMPLATE) {
                $row['presentation_origin'] = null;
            }
            $eventContexts[] = $row;
        }

        foreach ($defaultFieldKeys as $fieldKey) {
            $descriptor = $this->descriptor($fieldKey);

            if ($descriptor->editorKind === SupportedAcquisitionFieldEditorKind::EventContext) {
                if (! $this->hasEventContext($eventContexts)) {
                    $eventContexts[] = $this->blankEventRow(self::PRESENTATION_TEMPLATE);
                }

                continue;
            }

            if ($descriptor->subjectMentionKind === MentionKind::EVENT) {
                if (! $this->hasEventClaimField($eventContexts, $fieldKey)) {
                    $event = $this->blankEventRow(self::PRESENTATION_TEMPLATE);
                    $event['claims'] = [$this->blankClaimRow($fieldKey, self::PRESENTATION_TEMPLATE)];
                    $eventContexts[] = $event;
                }

                continue;
            }

            if (! $this->hasDirectField($fields, $fieldKey)) {
                $fields[] = $this->blankClaimRow($fieldKey, self::PRESENTATION_TEMPLATE);
            }
        }

        $state['fields'] = $this->orderTemplateFields($fields, $defaultFieldKeys);
        $state['event_contexts'] = $eventContexts;

        return $state;
    }

    /** @return array<string, mixed> */
    public function stateFromDraft(SourceDraft $draft): array
    {
        $mentionsById = [];
        foreach ($draft->current->mentions as $mention) {
            if (! $mention->kind->isCanonical()) {
                throw new InvalidArgumentException(sprintf(
                    'Source contains Mention kind "%s" that the Basic editor cannot represent safely.',
                    $mention->kind->key,
                ));
            }
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
        foreach ($draft->current->claims as $claim) {
            $fieldKey = $claim->predicate->key->value;
            if (! $this->catalog->has($fieldKey)) {
                throw new InvalidArgumentException(sprintf(
                    'Source contains Claim predicate "%s" that the Basic editor cannot represent safely.',
                    $fieldKey,
                ));
            }

            $subject = $mentionsById[$claim->subjectMentionId->value] ?? null;
            if ($subject === null) {
                throw new InvalidArgumentException(sprintf(
                    'Claim %s refers to a subject Mention that is not present in the SourceDraft.',
                    $claim->id->value,
                ));
            }
            if ($claim->objectMentionId !== null && ! isset($mentionsById[$claim->objectMentionId->value])) {
                throw new InvalidArgumentException(sprintf(
                    'Claim %s refers to an object Mention that is not present in the SourceDraft.',
                    $claim->id->value,
                ));
            }

            $row = $this->claimRow($claim, $mentionsById);
            if ($subject->kind->key === MentionKind::EVENT) {
                if (! isset($eventRows[$subject->id->value])) {
                    throw new InvalidArgumentException(sprintf(
                        'Event Claim %s cannot be attached to a representable event context.',
                        $claim->id->value,
                    ));
                }
                unset($row['subject_local_key']);
                $eventRows[$subject->id->value]['claims'][] = $row;
            } else {
                $fieldRows[] = $row;
            }
        }

        return [
            'mentions' => $mentionRows,
            'fields' => $fieldRows,
            'event_contexts' => array_values($eventRows),
        ];
    }

    /** @param  array<string, mixed>  $data */
    public function editInput(array $data): SupportedAcquisitionEditInput
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
            if ($this->isEmptyClaimRow($row)) {
                continue;
            }
            $fields[] = $this->claimInput($row, null, "data.fields.$index");
        }

        $eventContexts = [];
        foreach (array_values($eventRows) as $eventIndex => $eventRow) {
            if (! is_array($eventRow)) {
                throw ValidationException::withMessages(["data.event_contexts.$eventIndex" => 'Invalid event context row.']);
            }
            if ($this->isEmptyEventRow($eventRow)) {
                continue;
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
                if ($this->isEmptyClaimRow($claimRow)) {
                    continue;
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

    /** @return list<Component> */
    private function directFieldSchema(bool $includeSubject, bool $eventOnly = false): array
    {
        $schema = [
            Hidden::make('claim_id'),
            Hidden::make('presentation_origin'),
            SupportedFieldPicker::make('field_key')
                ->label(__('supported_fields.picker.field_label'))
                ->groups(fn (): array => app(SupportedFieldPickerPresentation::class)->claimGroups($eventOnly))
                ->live(),
        ];

        if ($includeSubject) {
            $schema[] = MentionReferenceSelect::make('subject_local_key')
                ->label('Subject Mention')
                ->helperText('Select a Mention from this SourceDraft. Display labels are descriptive; the source-local key remains the reference identity.')
                ->options(fn (Get $get, LivewireComponent $livewire): array => $this->mentionPickerOptionsFromLivewire(
                    fieldKey: $get('field_key'),
                    livewire: $livewire,
                    subject: true,
                ))
                ->searchable()
                ->preload();
        }

        $schema[] = MentionReferenceSelect::make('object_local_key')
            ->label('Object Mention')
            ->helperText('Select the Mention referenced by this field.')
            ->options(fn (Get $get, LivewireComponent $livewire): array => $this->mentionPickerOptionsFromLivewire(
                fieldKey: $get('field_key'),
                livewire: $livewire,
                subject: false,
            ))
            ->searchable()
            ->preload()
            ->visible(fn (Get $get): bool => $this->editorKind($get('field_key')) === SupportedAcquisitionFieldEditorKind::MentionReference);

        $schema[] = TextInput::make('value_raw')
            ->label('Raw/source value')
            ->helperText('Preserved exactly as entered.')
            ->visible(fn (Get $get): bool => $this->isLiteralEditor($get('field_key')));

        $schema[] = Select::make('expression_kind')
            ->label('Expression')
            ->options([
                'exact' => 'Exact',
                'approximate' => 'Approximate',
                'range' => 'Range',
                'uncertain' => 'Uncertain',
            ])
            ->live()
            ->visible(fn (Get $get): bool => $this->isTemporalValueEditor($get('field_key')));

        $schema[] = TextInput::make('value_from')
            ->label(fn (Get $get): string => $this->temporalValueLabel($get('field_key'), $get('expression_kind')))
            ->helperText(fn (Get $get): string => $this->editorKind($get('field_key')) === SupportedAcquisitionFieldEditorKind::Age
                ? 'Non-negative integer.'
                : 'YYYY, YYYY-MM or YYYY-MM-DD.')
            ->visible(fn (Get $get): bool => $this->isTemporalValueEditor($get('field_key')));

        $schema[] = TextInput::make('value_to')
            ->label('To')
            ->helperText('Required only for a range.')
            ->visible(fn (Get $get): bool => $this->isTemporalValueEditor($get('field_key'))
                && $get('expression_kind') === 'range');

        $schema[] = Select::make('age_unit')
            ->label('Age unit')
            ->options([
                'years' => 'Years',
                'months' => 'Months',
                'days' => 'Days',
            ])
            ->visible(fn (Get $get): bool => $this->editorKind($get('field_key')) === SupportedAcquisitionFieldEditorKind::Age);

        $schema[] = TextInput::make('integer_value')
            ->label('Parsed integer')
            ->numeric()
            ->integer()
            ->visible(fn (Get $get): bool => $this->editorKind($get('field_key')) === SupportedAcquisitionFieldEditorKind::Integer);

        $schema[] = Select::make('boolean_value')
            ->label('Parsed boolean')
            ->options([
                '1' => 'True',
                '0' => 'False',
            ])
            ->visible(fn (Get $get): bool => $this->editorKind($get('field_key')) === SupportedAcquisitionFieldEditorKind::Boolean);

        $schema[] = TextInput::make('enum_key')
            ->label('Controlled enum key')
            ->visible(fn (Get $get): bool => $this->editorKind($get('field_key')) === SupportedAcquisitionFieldEditorKind::Enum);

        $schema[] = Section::make('Context & provenance')
            ->description('Optional source context and certainty metadata.')
            ->schema([
                TextInput::make('effective_time_raw')
                    ->label('Effective time raw/source value')
                    ->helperText('Optional explicit Claim effective time/period; blank means no temporal qualifier.'),
                Select::make('effective_time_kind')
                    ->label('Effective time expression')
                    ->options([
                        'exact' => 'Exact',
                        'approximate' => 'Approximate',
                        'range' => 'Range',
                        'uncertain' => 'Uncertain',
                    ])
                    ->live(),
                TextInput::make('effective_time_from')
                    ->label('Effective time value')
                    ->helperText('YYYY, YYYY-MM or YYYY-MM-DD.'),
                TextInput::make('effective_time_to')
                    ->label('Effective time to')
                    ->visible(fn (Get $get): bool => $get('effective_time_kind') === 'range'),
                Textarea::make('raw_text')
                    ->label('Supporting raw text')
                    ->rows(2)
                    ->columnSpanFull(),
                TextInput::make('transcription_certainty')
                    ->label('Transcription certainty')
                    ->helperText('Stable lowercase code, e.g. unspecified, certain, uncertain.')
                    ->default('unspecified'),
                TextInput::make('interpretation_certainty')
                    ->label('Interpretation certainty')
                    ->helperText('Stable lowercase code; independent from transcription certainty.')
                    ->default('unspecified'),
            ])
            ->columns(2)
            ->collapsed()
            ->collapsible()
            ->columnSpanFull();

        return $schema;
    }

    private function editorKind(mixed $fieldKey): ?SupportedAcquisitionFieldEditorKind
    {
        if (! is_string($fieldKey) || ! $this->catalog->has($fieldKey)) {
            return null;
        }

        return $this->catalog->get($fieldKey)->editorKind;
    }

    private function isLiteralEditor(mixed $fieldKey): bool
    {
        $kind = $this->editorKind($fieldKey);

        return $kind !== null
            && $kind !== SupportedAcquisitionFieldEditorKind::MentionReference
            && $kind !== SupportedAcquisitionFieldEditorKind::EventContext;
    }

    private function isTemporalValueEditor(mixed $fieldKey): bool
    {
        return $this->isTemporalEditorKind($this->editorKind($fieldKey));
    }

    private function isTemporalEditorKind(?SupportedAcquisitionFieldEditorKind $kind): bool
    {
        return in_array($kind, [
            SupportedAcquisitionFieldEditorKind::Date,
            SupportedAcquisitionFieldEditorKind::Age,
        ], true);
    }

    private function temporalValueLabel(mixed $fieldKey, mixed $expressionKind): string
    {
        $value = $this->editorKind($fieldKey) === SupportedAcquisitionFieldEditorKind::Age ? 'Age' : 'Date';

        return match ($expressionKind) {
            'approximate' => "Approximate {$value}",
            'uncertain' => "Uncertain {$value}",
            'range' => 'From',
            default => $value,
        };
    }

    /** @return array<string, string> */
    private function fieldOptions(bool $eventOnly): array
    {
        $options = [];
        foreach ($this->catalog->all() as $descriptor) {
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

    /** @return array<string, string> */
    private function mentionPickerOptionsFromLivewire(mixed $fieldKey, LivewireComponent $livewire, bool $subject): array
    {
        if (! $livewire instanceof SourceWorkspacePage
            || ! is_string($fieldKey)
            || ! $this->catalog->has($fieldKey)) {
            return [];
        }

        return $this->mentionPickerOptions(
            state: is_array($livewire->evidenceData) ? $livewire->evidenceData : [],
            fieldKey: $fieldKey,
            subject: $subject,
        );
    }

    /**
     * @param  array<string, string>  $options
     * @param  array<string, mixed>  $row
     */
    private function appendMentionPickerOption(array &$options, array $row): void
    {
        $localKey = $this->optionalString($row['local_key'] ?? null);
        if ($localKey === null) {
            return;
        }

        $displayLabel = $this->optionalString($row['display_label'] ?? null);
        $options[$localKey] = $displayLabel === null || $displayLabel === $localKey
            ? $localKey
            : sprintf('%s · %s', $displayLabel, $localKey);
    }

    private function descriptor(string $fieldKey): SupportedAcquisitionFieldDescriptor
    {
        try {
            return $this->catalog->get($fieldKey);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'data.template_id' => $exception->getMessage(),
            ]);
        }
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
            $descriptor = $this->catalog->get($fieldKey);
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
            objectLocalKey: $descriptor->editorKind === SupportedAcquisitionFieldEditorKind::MentionReference
                ? $this->optionalString($row['object_local_key'] ?? null)
                : null,
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

        $kind = $descriptor->editorKind;
        $expressionKind = $this->isTemporalEditorKind($kind)
            ? $this->optionalString($row['expression_kind'] ?? null)
            : null;

        return new SupportedAcquisitionFieldValueInput(
            rawValue: $raw,
            expressionKind: $expressionKind,
            from: $this->isTemporalEditorKind($kind) ? $this->stringOrInt($row['value_from'] ?? null) : null,
            to: $this->isTemporalEditorKind($kind) && $expressionKind === 'range'
                ? $this->stringOrInt($row['value_to'] ?? null)
                : null,
            ageUnit: $kind === SupportedAcquisitionFieldEditorKind::Age
                ? $this->optionalString($row['age_unit'] ?? null)
                : null,
            integerValue: $kind === SupportedAcquisitionFieldEditorKind::Integer
                ? $this->integerOrNull($row['integer_value'] ?? null)
                : null,
            booleanValue: $kind === SupportedAcquisitionFieldEditorKind::Boolean
                ? $this->booleanOrNull($row['boolean_value'] ?? null)
                : null,
            enumKey: $kind === SupportedAcquisitionFieldEditorKind::Enum
                ? $this->optionalString($row['enum_key'] ?? null)
                : null,
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
            'presentation_origin' => null,
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

        $row = $this->blankClaimRow($claim->predicate->key->value);
        $row['claim_id'] = $claim->id->value;
        $row['subject_local_key'] = $subject?->localKey;
        $row['object_local_key'] = $object?->localKey;
        $row['value_raw'] = $claim->value?->raw();
        $row['raw_text'] = $claim->rawText;
        $row['transcription_certainty'] = $claim->transcriptionCertainty->code;
        $row['interpretation_certainty'] = $claim->interpretationCertainty->code;

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

    /** @return array<string, mixed> */
    private function blankClaimRow(string $fieldKey, ?string $origin = null): array
    {
        return [
            'claim_id' => null,
            'presentation_origin' => $origin,
            'field_key' => $fieldKey,
            'subject_local_key' => null,
            'object_local_key' => null,
            'value_raw' => null,
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
            'raw_text' => null,
            'transcription_certainty' => 'unspecified',
            'interpretation_certainty' => 'unspecified',
        ];
    }

    /** @return array<string, mixed> */
    private function blankEventRow(?string $origin = null): array
    {
        return [
            'id' => null,
            'presentation_origin' => $origin,
            'local_key' => null,
            'role' => null,
            'display_label' => null,
            'raw_data_json' => null,
            'claims' => [],
        ];
    }

    /** @param  array<string, mixed>  $row */
    private function isEmptyClaimRow(array $row): bool
    {
        if ($this->optionalString($row['claim_id'] ?? null) !== null) {
            return false;
        }

        foreach ([
            'subject_local_key',
            'object_local_key',
            'value_raw',
            'expression_kind',
            'value_from',
            'value_to',
            'age_unit',
            'integer_value',
            'boolean_value',
            'enum_key',
            'effective_time_raw',
            'effective_time_kind',
            'effective_time_from',
            'effective_time_to',
            'raw_text',
        ] as $key) {
            if ($this->hasValue($row[$key] ?? null)) {
                return false;
            }
        }

        foreach (['transcription_certainty', 'interpretation_certainty'] as $key) {
            $certainty = $this->optionalString($row[$key] ?? null);
            if ($certainty !== null && $certainty !== 'unspecified') {
                return false;
            }
        }

        return true;
    }

    /** @param  array<string, mixed>  $row */
    private function isEmptyEventRow(array $row): bool
    {
        if ($this->optionalString($row['id'] ?? null) !== null) {
            return false;
        }
        foreach (['local_key', 'role', 'display_label', 'raw_data_json'] as $key) {
            if ($this->hasValue($row[$key] ?? null)) {
                return false;
            }
        }

        foreach ($this->rows($row['claims'] ?? []) as $claimRow) {
            if (! $this->isEmptyClaimRow($claimRow)) {
                return false;
            }
        }

        return true;
    }

    /** @param  array<string, mixed>  $row */
    private function isRemovableTemplateClaimRow(array $row): bool
    {
        return ($row['presentation_origin'] ?? null) === self::PRESENTATION_TEMPLATE
            && $this->isEmptyClaimRow($row);
    }

    /** @param  array<string, mixed>  $row */
    private function isRemovableTemplateEventRow(array $row): bool
    {
        return ($row['presentation_origin'] ?? null) === self::PRESENTATION_TEMPLATE
            && $this->isEmptyEventRow($row);
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function hasDirectField(array $rows, string $fieldKey): bool
    {
        foreach ($rows as $row) {
            if (($row['field_key'] ?? null) === $fieldKey) {
                return true;
            }
        }

        return false;
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function hasEventContext(array $rows): bool
    {
        foreach ($rows as $row) {
            if (! $this->isEmptyEventRow($row) || ($row['presentation_origin'] ?? null) === self::PRESENTATION_TEMPLATE) {
                return true;
            }
        }

        return false;
    }

    /** @param  list<array<string, mixed>>  $eventRows */
    private function hasEventClaimField(array $eventRows, string $fieldKey): bool
    {
        foreach ($eventRows as $eventRow) {
            foreach ($this->rows($eventRow['claims'] ?? []) as $claimRow) {
                if (($claimRow['field_key'] ?? null) === $fieldKey) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $defaultFieldKeys
     * @return list<array<string, mixed>>
     */
    private function orderTemplateFields(array $rows, array $defaultFieldKeys): array
    {
        $order = array_flip(array_values(array_filter(
            $defaultFieldKeys,
            fn (string $fieldKey): bool => $this->catalog->has($fieldKey)
                && $this->catalog->get($fieldKey)->subjectMentionKind !== MentionKind::EVENT
                && $this->catalog->get($fieldKey)->isDirectClaim(),
        )));

        $indexed = [];
        foreach ($rows as $index => $row) {
            $fieldKey = $row['field_key'] ?? null;
            $indexed[] = [
                'row' => $row,
                'template_order' => is_string($fieldKey) && isset($order[$fieldKey]) ? $order[$fieldKey] : PHP_INT_MAX,
                'original_order' => $index,
            ];
        }

        usort(
            $indexed,
            static fn (array $left, array $right): int => [$left['template_order'], $left['original_order']] <=> [$right['template_order'], $right['original_order']],
        );

        return array_map(static fn (array $item): array => $item['row'], $indexed);
    }

    /** @return list<array<string, mixed>> */
    private function rows(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, static fn (mixed $row): bool => is_array($row)));
    }

    private function hasValue(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }
        if (is_string($value)) {
            return trim($value) !== '';
        }

        return true;
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
}
