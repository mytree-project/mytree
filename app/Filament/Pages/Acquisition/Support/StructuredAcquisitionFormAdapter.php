<?php

declare(strict_types=1);

namespace App\Filament\Pages\Acquisition\Support;

use App\Application\Acquisition\SourceDraft;
use App\Application\Acquisition\SupportedAcquisitionClaimInput;
use App\Application\Acquisition\SupportedAcquisitionEditInput;
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
use Filament\Forms\Components\Placeholder;
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
                ->helperText('Create or edit source-local Mentions. Each Mention owns the Claims whose subject it is, including event Mentions.')
                ->schema([
                    Hidden::make('id'),
                    Hidden::make('presentation_origin'),
                    Select::make('kind')
                        ->required()
                        ->options([
                            MentionKind::PERSON => 'Person',
                            MentionKind::EVENT => 'Event',
                            MentionKind::PLACE => 'Place',
                            MentionKind::ORGANIZATION => 'Organization',
                            MentionKind::OTHER => 'Other',
                        ])
                        ->live(),
                    TextInput::make('local_key')
                        ->label('Local key')
                        ->helperText('Claims in this Mention card use this source-local identity implicitly as their subject. Empty template presets are ignored until populated.')
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
                    Repeater::make('claims')
                        ->label('Claims')
                        ->helperText('Only predicates compatible with this Mention kind are available. The subject is the containing Mention.')
                        ->schema($this->directFieldSchema())
                        ->columns(2)
                        ->defaultItems(0)
                        ->addActionLabel('Add claim')
                        ->columnSpanFull(),
                    Placeholder::make('incoming_relationships')
                        ->label('Incoming relationship references')
                        ->content(fn (Get $get, LivewireComponent $livewire): string => $this->incomingRelationshipSummary(
                            state: $livewire instanceof SourceWorkspacePage && is_array($livewire->evidenceData)
                                ? $livewire->evidenceData
                                : [],
                            targetLocalKey: $this->optionalString($get('local_key')),
                        ) ?? '')
                        ->visible(fn (Get $get, LivewireComponent $livewire): bool => $this->incomingRelationshipSummary(
                            state: $livewire instanceof SourceWorkspacePage && is_array($livewire->evidenceData)
                                ? $livewire->evidenceData
                                : [],
                            targetLocalKey: $this->optionalString($get('local_key')),
                        ) !== null)
                        ->columnSpanFull(),
                ])
                ->columns(2)
                ->defaultItems(0)
                ->addActionLabel('Add source-local Mention'),
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

        return $options;
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
        $mentions = [];
        foreach ($this->rows($state['mentions'] ?? []) as $row) {
            $claims = [];
            foreach ($this->rows($row['claims'] ?? []) as $claimRow) {
                if ($this->isRemovableTemplateClaimRow($claimRow)) {
                    continue;
                }
                if (($claimRow['presentation_origin'] ?? null) === self::PRESENTATION_TEMPLATE) {
                    $claimRow['presentation_origin'] = null;
                }
                $claims[] = $claimRow;
            }
            $row['claims'] = $claims;

            if ($this->isRemovableTemplateMentionRow($row)) {
                continue;
            }
            if (($row['presentation_origin'] ?? null) === self::PRESENTATION_TEMPLATE) {
                $row['presentation_origin'] = null;
            }
            $mentions[] = $row;
        }

        /** @var array<string, array<string, mixed>> $templateMentions */
        $templateMentions = [];

        foreach ($defaultFieldKeys as $fieldKey) {
            $descriptor = $this->descriptor($fieldKey);
            $kind = $descriptor->subjectMentionKind;

            if ($descriptor->editorKind === SupportedAcquisitionFieldEditorKind::MentionPreset) {
                if (! $this->hasMentionKind($mentions, MentionKind::EVENT)) {
                    $templateMentions[MentionKind::EVENT] ??= $this->blankMentionRow(
                        MentionKind::EVENT,
                        self::PRESENTATION_TEMPLATE,
                    );
                }

                continue;
            }

            if ($this->hasClaimField($mentions, $fieldKey)) {
                continue;
            }

            $templateMentions[$kind] ??= $this->blankMentionRow(
                $kind,
                self::PRESENTATION_TEMPLATE,
            );
            $templateMentions[$kind]['claims'][] = $this->blankClaimRow(
                $fieldKey,
                self::PRESENTATION_TEMPLATE,
            );
        }

        foreach ($templateMentions as $templateMention) {
            $mentions[] = $templateMention;
        }

        $state['mentions'] = $mentions;
        unset($state['fields'], $state['event_contexts'], $state['add_supported_field']);

        return $state;
    }

    /** @return array<string, mixed> */
    public function stateFromDraft(SourceDraft $draft): array
    {
        $mentionsById = [];
        $mentionRows = [];

        foreach ($draft->current->mentions as $mention) {
            if (! $mention->kind->isCanonical()) {
                throw new InvalidArgumentException(sprintf(
                    'Source contains Mention kind "%s" that the Basic editor cannot represent safely.',
                    $mention->kind->key,
                ));
            }

            $mentionsById[$mention->id->value] = $mention;
            $row = $this->mentionRow($mention);
            $mentionRows[$mention->id->value] = $row;
        }

        foreach ($draft->current->claims as $claim) {
            $fieldKey = $claim->predicate->key->value;
            if (! $this->catalog->has($fieldKey)) {
                throw new InvalidArgumentException(sprintf(
                    'Source contains Claim predicate "%s" that the Basic editor cannot represent safely.',
                    $fieldKey,
                ));
            }

            $subject = $mentionsById[$claim->subjectMentionId->value] ?? null;
            if ($subject === null || ! isset($mentionRows[$claim->subjectMentionId->value])) {
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
            unset($row['subject_local_key']);
            $mentionRows[$claim->subjectMentionId->value]['claims'][] = $row;
        }

        return [
            'mentions' => array_values($mentionRows),
        ];
    }

    /** @param  array<string, mixed>  $data */
    public function editInput(array $data): SupportedAcquisitionEditInput
    {
        $mentionRows = $data['mentions'] ?? [];
        if (! is_array($mentionRows)) {
            throw ValidationException::withMessages(['data' => 'Structured editor state must contain a Mention list.']);
        }

        $mentions = [];
        $fields = [];

        foreach (array_values($mentionRows) as $mentionIndex => $row) {
            if (! is_array($row)) {
                throw ValidationException::withMessages(["data.mentions.$mentionIndex" => 'Invalid Mention row.']);
            }
            if ($this->isEmptyMentionRow($row)) {
                continue;
            }

            $mention = $this->mentionInput($row, null, "data.mentions.$mentionIndex");
            $mentions[] = $mention;

            $claimRows = $row['claims'] ?? [];
            if (! is_array($claimRows)) {
                throw ValidationException::withMessages([
                    "data.mentions.$mentionIndex.claims" => 'Mention Claims must be a list.',
                ]);
            }

            foreach (array_values($claimRows) as $claimIndex => $claimRow) {
                if (! is_array($claimRow)) {
                    throw ValidationException::withMessages([
                        "data.mentions.$mentionIndex.claims.$claimIndex" => 'Invalid Claim row.',
                    ]);
                }
                if ($this->isEmptyClaimRow($claimRow)) {
                    continue;
                }

                $fields[] = $this->claimInput(
                    $claimRow,
                    $mention->localKey,
                    "data.mentions.$mentionIndex.claims.$claimIndex",
                );
            }
        }

        return new SupportedAcquisitionEditInput($mentions, $fields);
    }

    /** @return list<Component> */
    private function directFieldSchema(): array
    {
        $schema = [
            Hidden::make('claim_id'),
            Hidden::make('presentation_origin'),
            SupportedFieldPicker::make('field_key')
                ->label(__('supported_fields.picker.field_label'))
                ->groups(fn (Get $get): array => app(SupportedFieldPickerPresentation::class)->claimGroupsForMentionKind(
                    $get('../../kind'),
                ))
                ->live(),
            MentionReferenceSelect::make('object_local_key')
                ->label('Object Mention')
                ->helperText('Select the source-local Mention referenced by this Claim.')
                ->options(fn (Get $get, LivewireComponent $livewire): array => $this->mentionPickerOptionsFromLivewire(
                    fieldKey: $get('field_key'),
                    livewire: $livewire,
                    subject: false,
                ))
                ->searchable()
                ->preload()
                ->live()
                ->visible(fn (Get $get): bool => $this->editorKind($get('field_key')) === SupportedAcquisitionFieldEditorKind::MentionReference),
            TextInput::make('value_raw')
                ->label('Raw/source value')
                ->helperText('Preserved exactly as entered.')
                ->visible(fn (Get $get): bool => $this->isLiteralEditor($get('field_key'))),
            Select::make('expression_kind')
                ->label('Expression')
                ->options([
                    'exact' => 'Exact',
                    'approximate' => 'Approximate',
                    'range' => 'Range',
                    'uncertain' => 'Uncertain',
                ])
                ->live()
                ->visible(fn (Get $get): bool => $this->isTemporalValueEditor($get('field_key'))),
            TextInput::make('value_from')
                ->label(fn (Get $get): string => $this->temporalValueLabel($get('field_key'), $get('expression_kind')))
                ->helperText(fn (Get $get): string => $this->editorKind($get('field_key')) === SupportedAcquisitionFieldEditorKind::Age
                    ? 'Non-negative integer.'
                    : 'YYYY, YYYY-MM or YYYY-MM-DD.')
                ->visible(fn (Get $get): bool => $this->isTemporalValueEditor($get('field_key'))),
            TextInput::make('value_to')
                ->label('To')
                ->helperText('Required only for a range.')
                ->visible(fn (Get $get): bool => $this->isTemporalValueEditor($get('field_key'))
                    && $get('expression_kind') === 'range'),
            Select::make('age_unit')
                ->label('Age unit')
                ->options([
                    'years' => 'Years',
                    'months' => 'Months',
                    'days' => 'Days',
                ])
                ->visible(fn (Get $get): bool => $this->editorKind($get('field_key')) === SupportedAcquisitionFieldEditorKind::Age),
            TextInput::make('integer_value')
                ->label('Parsed integer')
                ->numeric()
                ->integer()
                ->visible(fn (Get $get): bool => $this->editorKind($get('field_key')) === SupportedAcquisitionFieldEditorKind::Integer),
            Select::make('boolean_value')
                ->label('Parsed boolean')
                ->options([
                    '1' => 'True',
                    '0' => 'False',
                ])
                ->visible(fn (Get $get): bool => $this->editorKind($get('field_key')) === SupportedAcquisitionFieldEditorKind::Boolean),
            Select::make('enum_key')
                ->label('Controlled value')
                ->options(fn (Get $get): array => $this->enumOptions($get('field_key')))
                ->visible(fn (Get $get): bool => $this->editorKind($get('field_key')) === SupportedAcquisitionFieldEditorKind::Enum),
            Section::make('Context & provenance')
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
                ->columnSpanFull(),
        ];

        return $schema;
    }

    private function editorKind(mixed $fieldKey): ?SupportedAcquisitionFieldEditorKind
    {
        if (! is_string($fieldKey) || ! $this->catalog->has($fieldKey)) {
            return null;
        }

        return $this->catalog->get($fieldKey)->editorKind;
    }

    /** @return array<string, string> */
    private function enumOptions(mixed $fieldKey): array
    {
        if (! is_string($fieldKey) || ! $this->catalog->has($fieldKey)) {
            return [];
        }

        $options = [];
        foreach ($this->catalog->get($fieldKey)->allowedEnumKeys as $enumKey) {
            $translationKey = 'supported_fields.enum_values.'.$enumKey;
            $translated = __($translationKey);

            $options[$enumKey] = is_string($translated) && $translated !== $translationKey
                ? $translated
                : $enumKey;
        }

        return $options;
    }

    private function isLiteralEditor(mixed $fieldKey): bool
    {
        $kind = $this->editorKind($fieldKey);

        return $kind !== null
            && $kind !== SupportedAcquisitionFieldEditorKind::MentionReference
            && $kind !== SupportedAcquisitionFieldEditorKind::MentionPreset;
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
            'claims' => [],
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
    private function blankMentionRow(string $kind, ?string $origin = null): array
    {
        return [
            'id' => null,
            'presentation_origin' => $origin,
            'kind' => $kind,
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
    private function isEmptyMentionRow(array $row): bool
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
    private function isRemovableTemplateMentionRow(array $row): bool
    {
        return ($row['presentation_origin'] ?? null) === self::PRESENTATION_TEMPLATE
            && $this->isEmptyMentionRow($row);
    }

    /** @param  list<array<string, mixed>>  $mentions */
    private function hasMentionKind(array $mentions, string $kind): bool
    {
        foreach ($mentions as $mention) {
            if (($mention['kind'] ?? null) === $kind && ! $this->isEmptyMentionRow($mention)) {
                return true;
            }
        }

        return false;
    }

    /** @param  list<array<string, mixed>>  $mentions */
    private function hasClaimField(array $mentions, string $fieldKey): bool
    {
        foreach ($mentions as $mention) {
            foreach ($this->rows($mention['claims'] ?? []) as $claim) {
                if (($claim['field_key'] ?? null) === $fieldKey && ! $this->isEmptyClaimRow($claim)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @param  array<string, mixed>  $state */
    private function incomingRelationshipSummary(array $state, ?string $targetLocalKey): ?string
    {
        if ($targetLocalKey === null) {
            return null;
        }

        $relationships = [];
        foreach ($this->rows($state['mentions'] ?? []) as $mention) {
            $subjectLocalKey = $this->optionalString($mention['local_key'] ?? null);
            if ($subjectLocalKey === null) {
                continue;
            }

            $subjectLabel = $this->optionalString($mention['display_label'] ?? null) ?? $subjectLocalKey;

            foreach ($this->rows($mention['claims'] ?? []) as $claim) {
                if (($claim['object_local_key'] ?? null) !== $targetLocalKey) {
                    continue;
                }

                $fieldKey = $this->optionalString($claim['field_key'] ?? null);
                if ($fieldKey === null || ! $this->catalog->has($fieldKey)) {
                    continue;
                }

                $descriptor = $this->catalog->get($fieldKey);
                if ($descriptor->editorKind !== SupportedAcquisitionFieldEditorKind::MentionReference) {
                    continue;
                }

                $relationships[] = sprintf('%s ← %s', $descriptor->label, $subjectLabel);
            }
        }

        return $relationships === [] ? null : implode("\n", $relationships);
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
