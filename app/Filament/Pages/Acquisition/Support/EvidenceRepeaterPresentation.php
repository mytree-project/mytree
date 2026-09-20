<?php

declare(strict_types=1);

namespace App\Filament\Pages\Acquisition\Support;

use App\Application\Acquisition\SupportedAcquisitionFieldCatalog;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

/**
 * Presentation-only configuration for the Source Workspace evidence repeaters.
 *
 * Collapse state and item summaries remain Filament UI state and never become
 * part of the SourceDraft / Mention / Claim persistence contract.
 */
final readonly class EvidenceRepeaterPresentation
{
    private const SUMMARY_LIMIT = 80;

    public function __construct(private SupportedAcquisitionFieldCatalog $catalog) {}

    public function configure(Schema $schema): void
    {
        foreach ($schema->getComponents(withHidden: true) as $component) {
            if (! $component instanceof Repeater) {
                continue;
            }

            if ($component->getName() === 'mentions') {
                $this->configureMentions($component);
            }
        }
    }

    private function configureMentions(Repeater $repeater): void
    {
        $repeater
            ->extraAttributes(['data-evidence-repeater' => 'mentions'], merge: true)
            ->label(__('evidence.mentions'))
            ->helperText(__('evidence.mentions_help'))
            ->addActionLabel(__('evidence.add_mention'))
            ->collapsible()
            ->collapsed()
            ->itemLabel(fn (array $state, int $index): string => $this->mentionSummary(
                $state,
                $index + 1,
            ));

        $childSchema = $repeater->getChildSchema();
        if ($childSchema === null) {
            return;
        }

        foreach ($childSchema->getComponents(withHidden: true) as $component) {
            if ($component instanceof Repeater && $component->getName() === 'claims') {
                $this->configureClaims($component);
            }
        }
    }

    private function configureClaims(Repeater $repeater): void
    {
        $repeater
            ->label(__('evidence.claims'))
            ->helperText(__('evidence.claims_help'))
            ->addActionLabel(__('evidence.add_claim'))
            ->extraAttributes([
                'data-evidence-repeater' => 'claims',
            ], merge: true)
            ->collapsible()
            ->collapsed()
            ->itemLabel(fn (array $state, int $index): string => $this->claimSummary(
                $state,
                $index + 1,
            ));

        $this->makeSummaryFieldsLive($repeater, [
            'field_key',
            'subject_local_key',
            'object_local_key',
            'value_raw',
            'enum_key',
            'integer_value',
            'boolean_value',
        ]);
    }

    /**
     * @param  list<string>  $fieldNames
     */
    private function makeSummaryFieldsLive(Repeater $repeater, array $fieldNames): void
    {
        $childSchema = $repeater->getChildSchema();
        if ($childSchema === null) {
            return;
        }

        foreach ($childSchema->getComponents(withHidden: true) as $component) {
            if (! $component instanceof Field || ! in_array($component->getName(), $fieldNames, true)) {
                continue;
            }

            $component->live(onBlur: $component->getName() === 'value_raw');
        }
    }

    /** @param  array<string, mixed>  $state */
    private function mentionSummary(array $state, int $number): string
    {
        return $this->joinSummary([
            sprintf('%s %d', __('evidence.mention'), $number),
            $this->summaryString($state['kind'] ?? null),
            $this->summaryString($state['display_label'] ?? null),
            $this->summaryString($state['local_key'] ?? null),
        ]);
    }

    /** @param  array<string, mixed>  $state */
    private function claimSummary(array $state, int $number): string
    {
        return $this->joinSummary([
            sprintf('%s %d', __('evidence.claim'), $number),
            $this->fieldLabel($state['field_key'] ?? null),
            $this->claimValueSummary($state),
        ]);
    }

    private function fieldLabel(mixed $fieldKey): string
    {
        $fieldKey = $this->summaryString($fieldKey);
        if ($fieldKey === null) {
            return __('evidence.structured_field');
        }

        return $this->catalog->has($fieldKey)
            ? $this->catalog->get($fieldKey)->label
            : $fieldKey;
    }

    /** @param  array<string, mixed>  $state */
    private function claimValueSummary(array $state): ?string
    {
        foreach (['value_raw', 'object_local_key', 'enum_key', 'integer_value'] as $key) {
            $value = $this->summaryString($state[$key] ?? null);
            if ($value !== null) {
                return $value;
            }
        }

        $boolean = $state['boolean_value'] ?? null;
        if ($boolean === '1' || $boolean === 1 || $boolean === true) {
            return 'true';
        }
        if ($boolean === '0' || $boolean === 0 || $boolean === false) {
            return 'false';
        }

        return null;
    }

    /** @param  list<string|null>  $parts */
    private function joinSummary(array $parts): string
    {
        return implode(' · ', array_values(array_filter(
            $parts,
            static fn (?string $part): bool => $part !== null && $part !== '',
        )));
    }

    private function summaryString(mixed $value): ?string
    {
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : Str::limit($value, self::SUMMARY_LIMIT);
    }
}
