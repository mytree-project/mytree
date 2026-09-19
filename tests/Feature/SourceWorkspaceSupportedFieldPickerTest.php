<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Acquisition\CreateSource;
use App\Application\Acquisition\SupportedAcquisitionFieldCatalog;
use App\Domain\Acquisition\PredicateKey;
use App\Domain\Acquisition\SourceType;
use App\Filament\Pages\Acquisition\SourceEditor;
use App\Filament\Pages\Acquisition\Support\SupportedFieldPickerPresentation;
use App\Infrastructure\Persistence\Eloquent\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class SourceWorkspaceSupportedFieldPickerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_picker_groups_every_catalog_field_and_localizes_picker_copy(): void
    {
        app()->setLocale('pl');

        $presentation = app(SupportedFieldPickerPresentation::class);
        $groups = $presentation->addGroups();
        $catalog = app(SupportedAcquisitionFieldCatalog::class);

        self::assertSame(
            [
                'person_general',
                'person_relationships',
                'person_places',
                'person_status',
                'event_contexts',
                'event_general',
                'event_roles',
                'place_general',
            ],
            array_column($groups, 'key'),
        );
        self::assertSame(
            count($catalog->all()),
            array_sum(array_map(
                static fn (array $group): int => count($group['fields']),
                $groups,
            )),
        );

        self::assertSame(
            'Osoba · Informacje podstawowe',
            $this->group($groups, 'person_general')['label'],
        );
        self::assertSame(
            'Osoba · Relacje',
            $this->group($groups, 'person_relationships')['label'],
        );
        self::assertSame(
            'Osoba · Miejsca',
            $this->group($groups, 'person_places')['label'],
        );

        $personStatusGroup = $this->group($groups, 'person_status');
        self::assertSame('Osoba · Zawód, status i tytuły', $personStatusGroup['label']);

        $occupation = $this->field($personStatusGroup['fields'], PredicateKey::PersonOccupation->value);
        self::assertSame('Zawód', $occupation['label']);
        self::assertStringContainsString('Praca lub zawód faktycznie wykonywany', (string) $occupation['help']);
        self::assertTrue($occupation['repeatable']);
        self::assertFalse($occupation['event_context']);

        $eventContextGroup = $this->group($groups, 'event_contexts');
        $eventContext = $this->field(
            $eventContextGroup['fields'],
            SupportedAcquisitionFieldCatalog::EVENT_CONTEXT_KEY,
        );
        self::assertSame('Kontekst zdarzenia', $eventContext['label']);
        self::assertTrue($eventContext['repeatable']);
        self::assertTrue($eventContext['event_context']);

        self::assertSame(
            ['person_general', 'person_relationships', 'person_places', 'person_status', 'place_general'],
            array_column($presentation->claimGroups(eventOnly: false), 'key'),
        );
        self::assertSame(
            ['event_general', 'event_roles'],
            array_column($presentation->claimGroups(eventOnly: true), 'key'),
        );

        app()->setLocale('en');
        $englishGroups = $presentation->addGroups();
        self::assertSame(
            'Person · Basic information',
            $this->group($englishGroups, 'person_general')['label'],
        );
        self::assertSame(
            'Occupation',
            $this->field(
                $this->group($englishGroups, 'person_status')['fields'],
                PredicateKey::PersonOccupation->value,
            )['label'],
        );
    }

    public function test_picker_selection_keeps_existing_canonical_add_supported_field_behavior(): void
    {
        $source = app(CreateSource::class)->handle(SourceType::generic());

        $component = Livewire::test(SourceEditor::class, ['source' => $source->id->value])
            ->call('supportedFieldSelected', PredicateKey::PersonOccupation->value)
            ->assertSet('evidenceData.add_supported_field', null)
            ->assertSet('evidenceData.fields', fn (mixed $fields): bool => $this->fieldKeyCount(
                $fields,
                PredicateKey::PersonOccupation->value,
            ) === 1);

        $component
            ->call('supportedFieldSelected', PredicateKey::PersonOccupation->value)
            ->assertSet('evidenceData.fields', fn (mixed $fields): bool => $this->fieldKeyCount(
                $fields,
                PredicateKey::PersonOccupation->value,
            ) === 2);

        $component
            ->call('supportedFieldSelected', SupportedAcquisitionFieldCatalog::EVENT_CONTEXT_KEY)
            ->assertSet('evidenceData.event_contexts', fn (mixed $events): bool => $this->eventContextCount(
                $events,
            ) === 1);

        $component
            ->call('supportedFieldSelected', PredicateKey::EventDate->value)
            ->assertSet('evidenceData.event_contexts', fn (mixed $events): bool => $this->eventClaimKeyCount(
                $events,
                PredicateKey::EventDate->value,
            ) === 1);
    }

    private function fieldKeyCount(mixed $fields, string $fieldKey): int
    {
        if (! is_array($fields)) {
            return 0;
        }

        return count(array_filter(
            $fields,
            static fn (mixed $field): bool => is_array($field)
                && ($field['field_key'] ?? null) === $fieldKey,
        ));
    }

    private function eventContextCount(mixed $events): int
    {
        if (! is_array($events)) {
            return 0;
        }

        return count(array_filter($events, 'is_array'));
    }

    private function eventClaimKeyCount(mixed $events, string $fieldKey): int
    {
        if (! is_array($events)) {
            return 0;
        }

        $count = 0;

        foreach ($events as $event) {
            if (! is_array($event) || ! is_array($event['claims'] ?? null)) {
                continue;
            }

            $count += $this->fieldKeyCount($event['claims'], $fieldKey);
        }

        return $count;
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

    /**
     * @param  list<array<string, mixed>>  $fields
     * @return array<string, mixed>
     */
    private function field(array $fields, string $key): array
    {
        foreach ($fields as $field) {
            if (($field['key'] ?? null) === $key) {
                return $field;
            }
        }

        self::fail(sprintf('Supported field "%s" was not found.', $key));
    }
}
