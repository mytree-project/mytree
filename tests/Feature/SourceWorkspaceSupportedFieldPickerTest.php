<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Acquisition\SupportedAcquisitionFieldCatalog;
use App\Domain\Acquisition\MentionKind;
use App\Domain\Acquisition\PredicateKey;
use App\Filament\Pages\Acquisition\Support\SupportedFieldPickerPresentation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SourceWorkspaceSupportedFieldPickerTest extends TestCase
{
    use RefreshDatabase;

    public function test_claim_picker_is_filtered_by_the_containing_mention_kind(): void
    {
        app()->setLocale('pl');

        $presentation = app(SupportedFieldPickerPresentation::class);

        $personGroups = $presentation->claimGroupsForMentionKind(MentionKind::PERSON);
        self::assertSame(
            ['person_general', 'person_relationships', 'person_places', 'person_status'],
            array_column($personGroups, 'key'),
        );
        self::assertSame(
            'Osoba · Relacje',
            $this->group($personGroups, 'person_relationships')['label'],
        );
        self::assertSame(
            [PredicateKey::PersonParent->value, PredicateKey::PersonSpouse->value],
            array_column($this->group($personGroups, 'person_relationships')['fields'], 'key'),
        );

        $personGeneralFields = $this->group($personGroups, 'person_general')['fields'];
        self::assertContains(PredicateKey::PersonSex->value, array_column($personGeneralFields, 'key'));
        self::assertContains(PredicateKey::PersonReligiousAffiliation->value, array_column($personGeneralFields, 'key'));
        self::assertSame(
            'Płeć zapisana w źródle',
            array_column($personGeneralFields, 'label', 'key')[PredicateKey::PersonSex->value],
        );
        self::assertSame(
            'Wyznanie / przynależność religijna',
            array_column($personGeneralFields, 'label', 'key')[PredicateKey::PersonReligiousAffiliation->value],
        );
        self::assertStringContainsString(
            'nie wnioskuj',
            (string) array_column($personGeneralFields, 'help', 'key')[PredicateKey::PersonSex->value],
        );

        app()->setLocale('en');
        $englishPersonFields = $this->group(
            $presentation->claimGroupsForMentionKind(MentionKind::PERSON),
            'person_general',
        )['fields'];
        self::assertSame(
            'Source-recorded sex',
            array_column($englishPersonFields, 'label', 'key')[PredicateKey::PersonSex->value],
        );
        self::assertSame(
            'Religious affiliation',
            array_column($englishPersonFields, 'label', 'key')[PredicateKey::PersonReligiousAffiliation->value],
        );

        app()->setLocale('pl');
        $eventGroups = $presentation->claimGroupsForMentionKind(MentionKind::EVENT);
        self::assertSame(
            ['event_general', 'event_roles'],
            array_column($eventGroups, 'key'),
        );
        self::assertContains(
            PredicateKey::EventDate->value,
            array_column($this->group($eventGroups, 'event_general')['fields'], 'key'),
        );
        self::assertContains(
            PredicateKey::EventSpouse->value,
            array_column($this->group($eventGroups, 'event_roles')['fields'], 'key'),
        );

        $placeGroups = $presentation->claimGroupsForMentionKind(MentionKind::PLACE);
        self::assertSame(['place_general'], array_column($placeGroups, 'key'));
        self::assertSame(
            [PredicateKey::PlaceName->value],
            array_column($this->group($placeGroups, 'place_general')['fields'], 'key'),
        );

        self::assertSame([], $presentation->claimGroupsForMentionKind(MentionKind::ORGANIZATION));
        self::assertSame([], $presentation->claimGroupsForMentionKind(null));
    }

    public function test_synthetic_event_context_descriptor_remains_a_template_preset_not_a_claim_option(): void
    {
        $catalog = app(SupportedAcquisitionFieldCatalog::class);
        $presentation = app(SupportedFieldPickerPresentation::class);

        self::assertTrue($catalog->has(SupportedAcquisitionFieldCatalog::EVENT_CONTEXT_KEY));

        foreach ([
            MentionKind::PERSON,
            MentionKind::EVENT,
            MentionKind::PLACE,
            MentionKind::ORGANIZATION,
            MentionKind::OTHER,
        ] as $kind) {
            $fieldKeys = [];
            foreach ($presentation->claimGroupsForMentionKind($kind) as $group) {
                array_push($fieldKeys, ...array_column($group['fields'], 'key'));
            }

            self::assertNotContains(SupportedAcquisitionFieldCatalog::EVENT_CONTEXT_KEY, $fieldKeys);
        }
    }

    /**
     * @param  list<array{key: string, label: string, fields: list<array<string, mixed>>}>  $groups
     * @return array{key: string, label: string, fields: list<array<string, mixed>>}
     */
    private function group(array $groups, string $key): array
    {
        foreach ($groups as $group) {
            if ($group['key'] === $key) {
                return $group;
            }
        }

        self::fail(sprintf('Supported-field group "%s" was not found.', $key));
    }
}
