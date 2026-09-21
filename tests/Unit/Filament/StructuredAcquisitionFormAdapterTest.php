<?php

declare(strict_types=1);

namespace Tests\Unit\Filament;

use App\Application\Acquisition\SupportedAcquisitionFieldCatalog;
use App\Domain\Acquisition\MentionKind;
use App\Domain\Acquisition\PredicateKey;
use App\Domain\Acquisition\SexClaimValueKey;
use App\Filament\Pages\Acquisition\Support\StructuredAcquisitionFormAdapter;
use PHPUnit\Framework\TestCase;

final class StructuredAcquisitionFormAdapterTest extends TestCase
{
    public function test_mention_picker_options_follow_subject_and_object_predicate_contracts(): void
    {
        $adapter = new StructuredAcquisitionFormAdapter(new SupportedAcquisitionFieldCatalog);
        $state = $this->state();

        self::assertSame(
            [
                'person.child' => 'Jan Kowalski · person.child',
                'person.witness' => 'person.witness',
            ],
            $adapter->mentionPickerOptions($state, PredicateKey::PersonOccupation->value, subject: true),
        );
        self::assertSame(
            [
                'place.birth' => 'Wieś X · place.birth',
            ],
            $adapter->mentionPickerOptions($state, PredicateKey::PersonResidence->value, subject: false),
        );
        self::assertSame(
            [
                'event.birth.1' => 'Birth record event · event.birth.1',
            ],
            $adapter->mentionPickerOptions($state, PredicateKey::EventDate->value, subject: true),
        );
    }

    public function test_event_mention_and_its_claims_use_the_same_canonical_input_path_as_other_mentions(): void
    {
        $adapter = new StructuredAcquisitionFormAdapter(new SupportedAcquisitionFieldCatalog);
        $state = $this->state();
        $state['mentions'][4]['claims'] = [[
            'field_key' => PredicateKey::EventWitness->value,
            'object_local_key' => 'person.witness',
        ]];

        $input = $adapter->editInput($state);

        self::assertCount(5, $input->mentions);
        self::assertSame(MentionKind::EVENT, $input->mentions[4]->kind->key);
        self::assertSame('event.birth.1', $input->mentions[4]->localKey);
        self::assertCount(1, $input->fields);
        self::assertSame(PredicateKey::EventWitness->value, $input->fields[0]->fieldKey);
        self::assertSame('event.birth.1', $input->fields[0]->subjectLocalKey);
        self::assertSame('person.witness', $input->fields[0]->objectLocalKey);
    }

    public function test_template_presentation_groups_defaults_under_subject_kind_mentions_without_persisting_empty_rows(): void
    {
        $adapter = new StructuredAcquisitionFormAdapter(new SupportedAcquisitionFieldCatalog);

        $state = $adapter->applyTemplatePresentation(
            ['mentions' => []],
            [
                PredicateKey::PersonGivenName->value,
                PredicateKey::PersonSurname->value,
                SupportedAcquisitionFieldCatalog::EVENT_CONTEXT_KEY,
                PredicateKey::EventDate->value,
            ],
        );

        self::assertCount(2, $state['mentions']);
        self::assertSame(MentionKind::PERSON, $state['mentions'][0]['kind']);
        self::assertSame(
            [PredicateKey::PersonGivenName->value, PredicateKey::PersonSurname->value],
            array_column($state['mentions'][0]['claims'], 'field_key'),
        );
        self::assertSame(MentionKind::EVENT, $state['mentions'][1]['kind']);
        self::assertSame(
            [PredicateKey::EventDate->value],
            array_column($state['mentions'][1]['claims'], 'field_key'),
        );

        $input = $adapter->editInput($state);
        self::assertSame([], $input->mentions);
        self::assertSame([], $input->fields);
    }

    public function test_text_field_ignores_stale_controls_from_other_value_types(): void
    {
        $adapter = new StructuredAcquisitionFormAdapter(new SupportedAcquisitionFieldCatalog);
        $state = $this->state();
        $state['mentions'][0]['claims'] = [[
            'field_key' => PredicateKey::PersonGivenName->value,
            'object_local_key' => 'place.birth',
            'value_raw' => 'Jan',
            'expression_kind' => 'range',
            'value_from' => '1890',
            'value_to' => '1891',
            'age_unit' => 'years',
            'integer_value' => 42,
            'boolean_value' => '1',
            'enum_key' => 'stale',
        ]];

        $claim = $adapter->editInput($state)->fields[0];

        self::assertSame('person.child', $claim->subjectLocalKey);
        self::assertNull($claim->objectLocalKey);
        self::assertNotNull($claim->value);
        self::assertSame('Jan', $claim->value->rawValue);
        self::assertNull($claim->value->expressionKind);
        self::assertNull($claim->value->from);
        self::assertNull($claim->value->to);
        self::assertNull($claim->value->ageUnit);
        self::assertNull($claim->value->integerValue);
        self::assertNull($claim->value->booleanValue);
        self::assertNull($claim->value->enumKey);
    }

    public function test_exact_date_does_not_submit_stale_range_end(): void
    {
        $adapter = new StructuredAcquisitionFormAdapter(new SupportedAcquisitionFieldCatalog);
        $state = $this->state();
        $state['mentions'][0]['claims'] = [[
            'field_key' => PredicateKey::PersonBirthDate->value,
            'value_raw' => '3 II 1891',
            'expression_kind' => 'exact',
            'value_from' => '1891-02-03',
            'value_to' => '1891-02-04',
        ]];

        $value = $adapter->editInput($state)->fields[0]->value;

        self::assertNotNull($value);
        self::assertSame('exact', $value->expressionKind);
        self::assertSame('1891-02-03', $value->from);
        self::assertNull($value->to);
    }

    public function test_mention_reference_does_not_submit_stale_literal_value(): void
    {
        $adapter = new StructuredAcquisitionFormAdapter(new SupportedAcquisitionFieldCatalog);
        $state = $this->state();
        $state['mentions'][0]['claims'] = [[
            'field_key' => PredicateKey::PersonResidence->value,
            'object_local_key' => 'place.birth',
            'value_raw' => 'stale literal',
            'integer_value' => 42,
        ]];

        $claim = $adapter->editInput($state)->fields[0];

        self::assertSame('person.child', $claim->subjectLocalKey);
        self::assertSame('place.birth', $claim->objectLocalKey);
        self::assertNull($claim->value);
    }

    /** @return array<string, mixed> */
    private function state(): array
    {
        return [
            'mentions' => [
                [
                    'id' => null,
                    'kind' => MentionKind::PERSON,
                    'local_key' => 'person.child',
                    'display_label' => 'Jan Kowalski',
                    'claims' => [],
                ],
                [
                    'id' => null,
                    'kind' => MentionKind::PERSON,
                    'local_key' => 'person.witness',
                    'display_label' => null,
                    'claims' => [],
                ],
                [
                    'id' => null,
                    'kind' => MentionKind::PLACE,
                    'local_key' => 'place.birth',
                    'display_label' => 'Wieś X',
                    'claims' => [],
                ],
                [
                    'id' => null,
                    'kind' => MentionKind::ORGANIZATION,
                    'local_key' => 'organization.parish',
                    'display_label' => 'Parish X',
                    'claims' => [],
                ],
                [
                    'id' => null,
                    'kind' => MentionKind::EVENT,
                    'local_key' => 'event.birth.1',
                    'display_label' => 'Birth record event',
                    'claims' => [],
                ],
            ],
        ];
    }

    public function test_source_recorded_sex_keeps_raw_wording_separate_from_the_controlled_key(): void
    {
        $adapter = new StructuredAcquisitionFormAdapter(new SupportedAcquisitionFieldCatalog);
        $state = $this->state();
        $state['mentions'][0]['claims'] = [[
            'field_key' => PredicateKey::PersonSex->value,
            'value_raw' => 'chłopca',
            'enum_key' => SexClaimValueKey::Male->value,
        ]];

        $value = $adapter->editInput($state)->fields[0]->value;

        self::assertNotNull($value);
        self::assertSame('chłopca', $value->rawValue);
        self::assertSame(SexClaimValueKey::Male->value, $value->enumKey);
    }
}
