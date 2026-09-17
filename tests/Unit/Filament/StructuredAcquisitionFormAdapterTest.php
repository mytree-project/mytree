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
