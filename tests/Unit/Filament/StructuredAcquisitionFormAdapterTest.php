<?php

declare(strict_types=1);

namespace Tests\Unit\Filament;

use App\Application\Acquisition\SupportedAcquisitionFieldCatalog;
use App\Domain\Acquisition\MentionKind;
use App\Domain\Acquisition\PredicateKey;
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
                'person.child' => 'Jan Kowalski · person.child',
                'person.witness' => 'person.witness',
            ],
            $adapter->mentionPickerOptions($state, PredicateKey::PersonSpouse->value, subject: false),
        );
    }

    public function test_event_claim_object_options_are_filtered_while_event_subject_is_available_from_context_state(): void
    {
        $adapter = new StructuredAcquisitionFormAdapter(new SupportedAcquisitionFieldCatalog);
        $state = $this->state();

        self::assertSame(
            [
                'place.birth' => 'Wieś X · place.birth',
            ],
            $adapter->mentionPickerOptions($state, PredicateKey::EventPlace->value, subject: false),
        );
        self::assertSame(
            [
                'person.child' => 'Jan Kowalski · person.child',
                'person.witness' => 'person.witness',
            ],
            $adapter->mentionPickerOptions($state, PredicateKey::EventWitness->value, subject: false),
        );
        self::assertSame(
            [
                'event.birth.1' => 'Birth record event · event.birth.1',
            ],
            $adapter->mentionPickerOptions($state, PredicateKey::EventDate->value, subject: true),
        );
    }

    public function test_literal_fields_do_not_offer_object_mentions(): void
    {
        $adapter = new StructuredAcquisitionFormAdapter(new SupportedAcquisitionFieldCatalog);

        self::assertSame(
            [],
            $adapter->mentionPickerOptions($this->state(), PredicateKey::PersonOccupation->value, subject: false),
        );
    }

    public function test_text_field_ignores_stale_controls_from_other_value_types(): void
    {
        $adapter = new StructuredAcquisitionFormAdapter(new SupportedAcquisitionFieldCatalog);
        $state = $this->state();
        $state['fields'] = [[
            'field_key' => PredicateKey::PersonGivenName->value,
            'subject_local_key' => 'person.child',
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

        $claim = $adapter->editInput($state)->claims[0];

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
        $state['fields'] = [[
            'field_key' => PredicateKey::PersonBirthDate->value,
            'subject_local_key' => 'person.child',
            'value_raw' => '3 II 1891',
            'expression_kind' => 'exact',
            'value_from' => '1891-02-03',
            'value_to' => '1891-02-04',
        ]];

        $value = $adapter->editInput($state)->claims[0]->value;

        self::assertNotNull($value);
        self::assertSame('exact', $value->expressionKind);
        self::assertSame('1891-02-03', $value->from);
        self::assertNull($value->to);
    }

    public function test_mention_reference_does_not_submit_stale_literal_value(): void
    {
        $adapter = new StructuredAcquisitionFormAdapter(new SupportedAcquisitionFieldCatalog);
        $state = $this->state();
        $state['fields'] = [[
            'field_key' => PredicateKey::PersonResidence->value,
            'subject_local_key' => 'person.child',
            'object_local_key' => 'place.birth',
            'value_raw' => 'stale literal',
            'integer_value' => 42,
        ]];

        $claim = $adapter->editInput($state)->claims[0];

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
                ],
                [
                    'id' => null,
                    'kind' => MentionKind::PERSON,
                    'local_key' => 'person.witness',
                    'display_label' => null,
                ],
                [
                    'id' => null,
                    'kind' => MentionKind::PLACE,
                    'local_key' => 'place.birth',
                    'display_label' => 'Wieś X',
                ],
                [
                    'id' => null,
                    'kind' => MentionKind::ORGANIZATION,
                    'local_key' => 'organization.parish',
                    'display_label' => 'Parish X',
                ],
            ],
            'fields' => [],
            'event_contexts' => [
                [
                    'id' => null,
                    'local_key' => 'event.birth.1',
                    'display_label' => 'Birth record event',
                    'claims' => [],
                ],
            ],
        ];
    }
}
