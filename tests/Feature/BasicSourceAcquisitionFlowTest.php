<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Acquisition\CreateSourceTypeTemplate;
use App\Application\Acquisition\LoadSourceDraft;
use App\Application\Acquisition\SourceTypeTemplateDefinition;
use App\Domain\Acquisition\PredicateKey;
use App\Domain\Acquisition\SourceId;
use App\Domain\Acquisition\SourceType;
use App\Filament\Pages\Acquisition\SourceEditor;
use App\Infrastructure\Persistence\Eloquent\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

final class BasicSourceAcquisitionFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_template_defaults_are_presentation_only_until_populated(): void
    {
        $template = app(CreateSourceTypeTemplate::class)->handle(new SourceTypeTemplateDefinition(
            name: 'Generic civil record',
            compatibleSourceTypes: [SourceType::generic()],
            defaultFieldKeys: [
                PredicateKey::PersonGivenName->value,
                PredicateKey::PersonSurname->value,
                'event.context',
            ],
        ));

        Livewire::test(SourceEditor::class)
            ->call('templateChanged', $template->templateId->value)
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertRedirect();

        $this->assertDatabaseCount('sources', 1);
        $this->assertDatabaseCount('mentions', 0);
        $this->assertDatabaseCount('claims', 0);
        $this->assertDatabaseCount('mention_revisions', 0);
        $this->assertDatabaseCount('claim_revisions', 0);
        $this->assertDatabaseCount('source_revisions', 1);
        $this->assertDatabaseCount('evidence_states', 1);
    }

    public function test_blank_basic_flow_can_save_an_atypical_supported_field_atomically(): void
    {
        Livewire::test(SourceEditor::class)
            ->fillForm([
                'mentions' => [[
                    'id' => null,
                    'kind' => 'person',
                    'local_key' => 'person-1',
                    'role' => 'declarant',
                    'display_label' => 'Jan Kowalski',
                    'raw_data_json' => '{"descriptor":"włościanin"}',
                ]],
                'fields' => [[
                    'claim_id' => null,
                    'field_key' => PredicateKey::PersonAcademicDegree->value,
                    'subject_local_key' => 'person-1',
                    'object_local_key' => null,
                    'value_raw' => 'doktor nauk medycznych',
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
                    'raw_text' => 'doktor nauk medycznych',
                    'transcription_certainty' => 'certain',
                    'interpretation_certainty' => 'certain',
                ]],
                'event_contexts' => [],
            ], 'evidenceForm')
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertRedirect();

        $record = DB::table('sources')->first();
        self::assertNotNull($record);
        $draft = app(LoadSourceDraft::class)->handle(new SourceId((string) $record->id));

        self::assertCount(1, $draft->current->mentions);
        self::assertCount(1, $draft->current->claims);
        self::assertSame('włościanin', $draft->current->mentions[0]->rawData->toArray()['descriptor']);
        self::assertSame(PredicateKey::PersonAcademicDegree->value, $draft->current->claims[0]->predicate->key->value);
        self::assertSame('doktor nauk medycznych', $draft->current->claims[0]->value?->raw());
        $this->assertDatabaseCount('source_revisions', 1);
        $this->assertDatabaseCount('mention_revisions', 1);
        $this->assertDatabaseCount('claim_revisions', 1);
        $this->assertDatabaseCount('evidence_states', 1);
    }

    public function test_changing_template_presentation_preserves_existing_additional_fields_and_is_a_noop(): void
    {
        $firstTemplate = app(CreateSourceTypeTemplate::class)->handle(new SourceTypeTemplateDefinition(
            name: 'Names only',
            compatibleSourceTypes: [SourceType::generic()],
            defaultFieldKeys: [PredicateKey::PersonGivenName->value],
        ));
        $secondTemplate = app(CreateSourceTypeTemplate::class)->handle(new SourceTypeTemplateDefinition(
            name: 'Surname only',
            compatibleSourceTypes: [SourceType::generic()],
            defaultFieldKeys: [PredicateKey::PersonSurname->value],
        ));

        Livewire::test(SourceEditor::class)
            ->fillForm([
                'mentions' => [[
                    'id' => null,
                    'kind' => 'person',
                    'local_key' => 'person-1',
                    'role' => null,
                    'display_label' => 'Jan',
                    'raw_data_json' => null,
                ]],
                'fields' => [[
                    'claim_id' => null,
                    'field_key' => PredicateKey::PersonOccupation->value,
                    'subject_local_key' => 'person-1',
                    'value_raw' => 'rolnik',
                    'transcription_certainty' => 'unspecified',
                    'interpretation_certainty' => 'unspecified',
                ]],
                'event_contexts' => [],
            ], 'evidenceForm')
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertRedirect();

        $sourceId = (string) DB::table('sources')->value('id');
        $revisionCount = DB::table('source_revisions')->count();
        $mentionRevisionCount = DB::table('mention_revisions')->count();
        $claimRevisionCount = DB::table('claim_revisions')->count();
        $evidenceStateCount = DB::table('evidence_states')->count();

        Livewire::test(SourceEditor::class, ['source' => $sourceId])
            ->call('templateChanged', $firstTemplate->templateId->value)
            ->call('templateChanged', $secondTemplate->templateId->value)
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertRedirect();

        $draft = app(LoadSourceDraft::class)->handle(new SourceId($sourceId));
        self::assertCount(1, $draft->current->claims);
        self::assertSame(PredicateKey::PersonOccupation->value, $draft->current->claims[0]->predicate->key->value);
        self::assertSame('rolnik', $draft->current->claims[0]->value?->raw());
        self::assertSame($revisionCount, DB::table('source_revisions')->count());
        self::assertSame($mentionRevisionCount, DB::table('mention_revisions')->count());
        self::assertSame($claimRevisionCount, DB::table('claim_revisions')->count());
        self::assertSame($evidenceStateCount, DB::table('evidence_states')->count());
    }

    public function test_basic_flow_saves_repeated_fields_and_reified_event_context_in_one_evidence_state(): void
    {
        Livewire::test(SourceEditor::class)
            ->fillForm([
                'mentions' => [
                    [
                        'id' => null,
                        'kind' => 'person',
                        'local_key' => 'child',
                        'role' => 'child',
                        'display_label' => 'Józef Gajda',
                        'raw_data_json' => null,
                    ],
                    [
                        'id' => null,
                        'kind' => 'person',
                        'local_key' => 'father',
                        'role' => 'father',
                        'display_label' => 'Jan Gajda',
                        'raw_data_json' => null,
                    ],
                ],
                'fields' => [
                    [
                        'claim_id' => null,
                        'field_key' => PredicateKey::PersonOccupation->value,
                        'subject_local_key' => 'father',
                        'value_raw' => 'rolnik',
                        'transcription_certainty' => 'certain',
                        'interpretation_certainty' => 'certain',
                    ],
                    [
                        'claim_id' => null,
                        'field_key' => PredicateKey::PersonOccupation->value,
                        'subject_local_key' => 'father',
                        'value_raw' => 'właściciel gospodarstwa',
                        'transcription_certainty' => 'certain',
                        'interpretation_certainty' => 'certain',
                    ],
                ],
                'event_contexts' => [[
                    'id' => null,
                    'local_key' => 'birth-event',
                    'role' => 'birth',
                    'display_label' => 'Birth of Józef Gajda',
                    'raw_data_json' => null,
                    'claims' => [
                        [
                            'claim_id' => null,
                            'field_key' => PredicateKey::EventDate->value,
                            'value_raw' => '1853-04-12',
                            'expression_kind' => 'exact',
                            'value_from' => '1853-04-12',
                            'transcription_certainty' => 'certain',
                            'interpretation_certainty' => 'certain',
                        ],
                        [
                            'claim_id' => null,
                            'field_key' => PredicateKey::EventChild->value,
                            'object_local_key' => 'child',
                            'transcription_certainty' => 'certain',
                            'interpretation_certainty' => 'certain',
                        ],
                    ],
                ]],
            ], 'evidenceForm')
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertRedirect();

        $sourceId = (string) DB::table('sources')->value('id');
        $draft = app(LoadSourceDraft::class)->handle(new SourceId($sourceId));

        self::assertCount(3, $draft->current->mentions);
        self::assertCount(4, $draft->current->claims);
        self::assertSame(2, count(array_filter(
            $draft->current->claims,
            static fn ($claim): bool => $claim->predicate->key->value === PredicateKey::PersonOccupation->value,
        )));
        self::assertNotEmpty(array_filter(
            $draft->current->claims,
            static fn ($claim): bool => $claim->predicate->key->value === PredicateKey::EventDate->value,
        ));
        self::assertNotEmpty(array_filter(
            $draft->current->claims,
            static fn ($claim): bool => $claim->predicate->key->value === PredicateKey::EventChild->value,
        ));
        $this->assertDatabaseCount('evidence_states', 1);
        $this->assertDatabaseCount('source_revisions', 1);
        $this->assertDatabaseCount('mention_revisions', 3);
        $this->assertDatabaseCount('claim_revisions', 4);
    }

    public function test_structured_validation_failure_leaves_acquisition_state_unchanged(): void
    {
        Livewire::test(SourceEditor::class)
            ->fillForm([
                'fields' => [[
                    'claim_id' => null,
                    'field_key' => PredicateKey::PersonOccupation->value,
                    'subject_local_key' => 'missing-person',
                    'value_raw' => 'rolnik',
                    'transcription_certainty' => 'unspecified',
                    'interpretation_certainty' => 'unspecified',
                ]],
            ], 'evidenceForm')
            ->call('save')
            ->assertHasErrors(['data']);

        $this->assertDatabaseCount('sources', 0);
        $this->assertDatabaseCount('mentions', 0);
        $this->assertDatabaseCount('claims', 0);
        $this->assertDatabaseCount('source_revisions', 0);
        $this->assertDatabaseCount('mention_revisions', 0);
        $this->assertDatabaseCount('claim_revisions', 0);
        $this->assertDatabaseCount('evidence_states', 0);
    }
}
