<?php

declare(strict_types=1);

namespace App\Filament\Pages\Acquisition\Support;

use App\Application\Acquisition\SupportedAcquisitionFieldCatalog;
use App\Application\Acquisition\SupportedAcquisitionFieldDescriptor;
use App\Application\Acquisition\SupportedAcquisitionFieldEditorKind;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

/**
 * Localized presentation data for the supported-field picker.
 *
 * Field membership, grouping, repeatability and editor semantics all come from
 * SupportedAcquisitionFieldCatalog. This class only chooses localized copy and
 * a stable UI order for those catalog-provided groups.
 */
final readonly class SupportedFieldPickerPresentation
{
    /** @var list<string> */
    private const GROUP_ORDER = [
        'person_facts',
        'event_contexts',
        'event_facts',
        'place_facts',
        'source_facts',
    ];

    public function __construct(private SupportedAcquisitionFieldCatalog $catalog) {}

    /**
     * @return list<array{
     *     key: string,
     *     label: string,
     *     fields: list<array{
     *         key: string,
     *         label: string,
     *         help: ?string,
     *         repeatable: bool,
     *         event_context: bool
     *     }>
     * }>
     */
    public function groups(): array
    {
        /**
         * @var array<string, array{
         *     key: string,
         *     label: string,
         *     fields: list<array{
         *         key: string,
         *         label: string,
         *         help: ?string,
         *         repeatable: bool,
         *         event_context: bool
         *     }>
         * }> $groups
         */
        $groups = [];

        foreach ($this->catalog->all() as $descriptor) {
            $groupKey = Str::snake($descriptor->group);
            $groups[$groupKey] ??= [
                'key' => $groupKey,
                'label' => $this->translation(
                    "supported_fields.groups.$groupKey",
                    $descriptor->group,
                ),
                'fields' => [],
            ];

            $groups[$groupKey]['fields'][] = $this->field($descriptor);
        }

        uksort($groups, static function (string $left, string $right): int {
            $leftOrder = array_search($left, self::GROUP_ORDER, true);
            $rightOrder = array_search($right, self::GROUP_ORDER, true);

            $comparison = ($leftOrder === false ? PHP_INT_MAX : $leftOrder)
                <=> ($rightOrder === false ? PHP_INT_MAX : $rightOrder);

            return $comparison !== 0 ? $comparison : strcmp($left, $right);
        });

        return array_values($groups);
    }

    /**
     * @return array{
     *     key: string,
     *     label: string,
     *     help: ?string,
     *     repeatable: bool,
     *     event_context: bool
     * }
     */
    private function field(SupportedAcquisitionFieldDescriptor $descriptor): array
    {
        $labelKey = "supported_fields.fields.{$descriptor->key}.label";
        $helpKey = "supported_fields.fields.{$descriptor->key}.help";

        return [
            'key' => $descriptor->key,
            'label' => $this->translation($labelKey, $descriptor->label),
            'help' => $descriptor->helpText === null
                ? null
                : $this->translation($helpKey, $descriptor->helpText),
            'repeatable' => $descriptor->repeatable,
            'event_context' => $descriptor->editorKind === SupportedAcquisitionFieldEditorKind::EventContext,
        ];
    }

    private function translation(string $key, string $fallback): string
    {
        if (! Lang::has($key)) {
            return $fallback;
        }

        $translated = __($key);

        return is_string($translated) ? $translated : $fallback;
    }
}
