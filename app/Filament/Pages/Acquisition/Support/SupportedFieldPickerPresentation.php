<?php

declare(strict_types=1);

namespace App\Filament\Pages\Acquisition\Support;

use App\Application\Acquisition\SupportedAcquisitionFieldCatalog;
use App\Application\Acquisition\SupportedAcquisitionFieldDescriptor;
use App\Application\Acquisition\SupportedAcquisitionFieldEditorKind;
use App\Domain\Acquisition\MentionKind;
use App\Domain\Acquisition\PredicateKey;
use Illuminate\Support\Facades\Lang;

final readonly class SupportedFieldPickerPresentation
{
    /** @var list<string> */
    private const PERSON_GENERAL_ORDER = [
        'person.given_name',
        'person.surname',
        'person.age',
        'person.birth_date',
        'person.death_date',
    ];

    /** @var list<string> */
    private const GROUP_ORDER = [
        'person_general',
        'person_relationships',
        'person_places',
        'person_status',
        'event_contexts',
        'event_general',
        'event_roles',
        'place_general',
        'source_general',
    ];

    public function __construct(private SupportedAcquisitionFieldCatalog $catalog) {}

    /**
     * Picker used for adding a new supported occurrence to the workspace.
     *
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
    public function addGroups(): array
    {
        return $this->groups($this->catalog->all());
    }

    /**
     * Picker used for choosing the Predicate of an existing Claim row.
     *
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
    public function claimGroups(bool $eventOnly): array
    {
        $descriptors = array_values(array_filter(
            $this->catalog->all(),
            static fn (SupportedAcquisitionFieldDescriptor $descriptor): bool => $descriptor->isDirectClaim()
                && ($descriptor->subjectMentionKind === MentionKind::EVENT) === $eventOnly,
        ));

        return $this->groups($descriptors);
    }

    /**
     * @param  list<SupportedAcquisitionFieldDescriptor>  $descriptors
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
    private function groups(array $descriptors): array
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

        foreach ($descriptors as $descriptor) {
            $groupKey = $this->groupKey($descriptor);
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

        foreach ($groups as $groupKey => &$group) {
            if ($groupKey !== 'person_general') {
                continue;
            }

            usort($group['fields'], static function (array $left, array $right): int {
                $leftOrder = array_search($left['key'], self::PERSON_GENERAL_ORDER, true);
                $rightOrder = array_search($right['key'], self::PERSON_GENERAL_ORDER, true);

                return ($leftOrder === false ? PHP_INT_MAX : $leftOrder)
                    <=> ($rightOrder === false ? PHP_INT_MAX : $rightOrder);
            });
        }
        unset($group);

        uksort($groups, static function (string $left, string $right): int {
            $leftOrder = array_search($left, self::GROUP_ORDER, true);
            $rightOrder = array_search($right, self::GROUP_ORDER, true);

            $comparison = ($leftOrder === false ? PHP_INT_MAX : $leftOrder)
                <=> ($rightOrder === false ? PHP_INT_MAX : $rightOrder);

            return $comparison !== 0 ? $comparison : strcmp($left, $right);
        });

        return array_values($groups);
    }

    private function groupKey(SupportedAcquisitionFieldDescriptor $descriptor): string
    {
        if ($descriptor->editorKind === SupportedAcquisitionFieldEditorKind::EventContext) {
            return 'event_contexts';
        }

        return match ($descriptor->predicateKey) {
            PredicateKey::PersonGivenName,
            PredicateKey::PersonSurname,
            PredicateKey::PersonAge,
            PredicateKey::PersonBirthDate,
            PredicateKey::PersonDeathDate => 'person_general',

            PredicateKey::PersonParent,
            PredicateKey::PersonSpouse => 'person_relationships',

            PredicateKey::PersonBirthPlace,
            PredicateKey::PersonResidence,
            PredicateKey::PersonPermanentResidence,
            PredicateKey::PersonTemporaryStay,
            PredicateKey::PersonPresence,
            PredicateKey::PersonAddress,
            PredicateKey::PersonOrigin,
            PredicateKey::PersonWorkPlace,
            PredicateKey::PersonStudyPlace,
            PredicateKey::PersonDetentionPlace,
            PredicateKey::PersonExilePlace,
            PredicateKey::PersonDeportationDestination => 'person_places',

            PredicateKey::PersonOccupation,
            PredicateKey::PersonSocialStatus,
            PredicateKey::PersonSocialEstate,
            PredicateKey::PersonOffice,
            PredicateKey::PersonRank,
            PredicateKey::PersonTitle,
            PredicateKey::PersonAcademicDegree => 'person_status',

            PredicateKey::EventDate,
            PredicateKey::EventPlace,
            PredicateKey::EventOriginPlace,
            PredicateKey::EventDestinationPlace,
            PredicateKey::EventReason => 'event_general',

            PredicateKey::EventParticipant,
            PredicateKey::EventChild,
            PredicateKey::EventParent,
            PredicateKey::EventSpouse,
            PredicateKey::EventWitness,
            PredicateKey::EventDeclarant,
            PredicateKey::EventOfficiant => 'event_roles',

            PredicateKey::PlaceName => 'place_general',
            default => 'source_general',
        };
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
