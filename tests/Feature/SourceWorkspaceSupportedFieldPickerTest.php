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

        $groups = app(SupportedFieldPickerPresentation::class)->groups();
        $catalog = app(SupportedAcquisitionFieldCatalog::class);

        self::assertSame(
            ['person_facts', 'event_contexts', 'event_facts', 'place_facts'],
            array_column($groups, 'key'),
        );
        self::assertSame(
            count($catalog->all()),
            array_sum(array_map(
                static fn (array $group): int => count($group['fields']),
                $groups,
            )),
        );

        $personGroup = $this->group($groups, 'person_facts');
        self::assertSame('Fakty o osobie', $personGroup['label']);

        $occupation = $this->field($personGroup['fields'], PredicateKey::PersonOccupation->value);
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

        app()->setLocale('en');
        $englishGroups = app(SupportedFieldPickerPresentation::class)->groups();
        self::assertSame('Person facts', $this->group($englishGroups, 'person_facts')['label']);
        self::assertSame(
            'Occupation',
            $this->field(
                $this->group($englishGroups, 'person_facts')['fields'],
                PredicateKey::PersonOccupation->value,
            )['label'],
        );
    }

    public function test_picker_selection_keeps_existing_canonical_add_supported_field_behavior(): void
    {
        $source = app(CreateSource::class)->handle(SourceType::generic());

        $component = Livewire::test(SourceEditor::class, ['source' => $source->id->value])
            ->call('supportedFieldSelected', PredicateKey::PersonOccupation->value)
            ->assertSet('evidenceData.fields.0.field_key', PredicateKey::PersonOccupation->value);

        $component
            ->call('supportedFieldSelected', PredicateKey::PersonOccupation->value)
            ->assertSet('evidenceData.fields.1.field_key', PredicateKey::PersonOccupation->value);

        $component
            ->call('supportedFieldSelected', SupportedAcquisitionFieldCatalog::EVENT_CONTEXT_KEY)
            ->assertSet('evidenceData.event_contexts.0.claims', []);

        $component
            ->call('supportedFieldSelected', PredicateKey::EventDate->value)
            ->assertSet('evidenceData.event_contexts.1.claims.0.field_key', PredicateKey::EventDate->value);
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
