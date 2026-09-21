<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Acquisition\CreateSourceTypeTemplate;
use App\Application\Acquisition\LoadSourceDraft;
use App\Application\Acquisition\SourceTypeTemplateDefinition;
use App\Domain\Acquisition\PredicateKey;
use App\Domain\Acquisition\SexClaimValueKey;
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
                    'claims' => [[
                        'claim_id' => null,
                        'field_key' => PredicateKey::PersonAcademicDegree->value,
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
                ]],
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
                    'claims' => [[
                        'claim_id' => null,
                        'field_key' => PredicateKey::PersonOccupation->value,
                        'value_raw' => 'rolnik',
                        'transcription_certainty' => 'unspecified',
                        'interpretation_certainty' => 'unspecified',
                    ]],
                ]],
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

    public function test_basic_flow_saves_repeated_claims_and_event_mention_in_one_evidence_state(): void
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
                        'claims' => [],
                    ],
                    [
                        'id' => null,
                        'kind' => 'person',
                        'local_key' => 'father',
                        'role' => 'father',
                        'display_label' => 'Jan Gajda',
                        'raw_data_json' => null,
                        'claims' => [
                            [
                                'claim_id' => null,
                                'field_key' => PredicateKey::PersonOccupation->value,
                                'value_raw' => 'rolnik',
                                'transcription_certainty' => 'certain',
                                'interpretation_certainty' => 'certain',
                            ],
                            [
                                'claim_id' => null,
                                'field_key' => PredicateKey::PersonOccupation->value,
                                'value_raw' => 'właściciel gospodarstwa',
                                'transcription_certainty' => 'certain',
                                'interpretation_certainty' => 'certain',
                            ],
                        ],
                    ],
                    [
                        'id' => null,
                        'kind' => 'event',
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
                    ],
                ],
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
                'mentions' => [[
                    'id' => null,
                    'kind' => 'place',
                    'local_key' => 'place-1',
                    'role' => null,
                    'display_label' => 'Place 1',
                    'raw_data_json' => null,
                    'claims' => [[
                        'claim_id' => null,
                        'field_key' => PredicateKey::PersonOccupation->value,
                        'value_raw' => 'rolnik',
                        'transcription_certainty' => 'unspecified',
                        'interpretation_certainty' => 'unspecified',
                    ]],
                ]],
            ], 'evidenceForm')
            ->call('save')
            ->assertHasErrors(['evidenceData.mentions.0.claims.0'])
            ->assertHasNoErrors(['data']);

        $this->assertDatabaseCount('sources', 0);
        $this->assertDatabaseCount('mentions', 0);
        $this->assertDatabaseCount('claims', 0);
        $this->assertDatabaseCount('source_revisions', 0);
        $this->assertDatabaseCount('mention_revisions', 0);
        $this->assertDatabaseCount('claim_revisions', 0);
        $this->assertDatabaseCount('evidence_states', 0);
    }

    public function test_source_recorded_sex_and_religious_affiliation_persist_and_round_trip_through_workspace_history(): void
    {
        Livewire::test(SourceEditor::class)
            ->fillForm([
                'mentions' => [[
                    'id' => null,
                    'kind' => 'person',
                    'local_key' => 'child',
                    'role' => 'child',
                    'display_label' => 'Józef Gajda',
                    'raw_data_json' => null,
                    'claims' => [
                        [
                            'claim_id' => null,
                            'field_key' => PredicateKey::PersonSex->value,
                            'value_raw' => 'chłopca',
                            'enum_key' => SexClaimValueKey::Male->value,
                            'raw_text' => 'urodziła chłopca',
                            'transcription_certainty' => 'certain',
                            'interpretation_certainty' => 'certain',
                        ],
                        [
                            'claim_id' => null,
                            'field_key' => PredicateKey::PersonReligiousAffiliation->value,
                            'value_raw' => 'wyznania katolickiego',
                            'raw_text' => 'oboje wyznania katolickiego',
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
        self::assertCount(1, $draft->current->mentions);
        self::assertCount(2, $draft->current->claims);

        $claims = [];
        foreach ($draft->current->claims as $claim) {
            $claims[$claim->predicate->key->value] = $claim;
        }

        $sex = $claims[PredicateKey::PersonSex->value];
        $religion = $claims[PredicateKey::PersonReligiousAffiliation->value];

        self::assertSame('chłopca', $sex->value?->raw());
        self::assertSame(SexClaimValueKey::Male->value, $sex->value?->data()['key'] ?? null);
        self::assertSame('wyznania katolickiego', $religion->value?->raw());
        self::assertSame('urodziła chłopca', $sex->rawText);
        self::assertSame('oboje wyznania katolickiego', $religion->rawText);
        $this->assertDatabaseCount('claim_revisions', 2);
        $this->assertDatabaseCount('evidence_states', 1);

        $mention = $draft->current->mentions[0];

        Livewire::test(SourceEditor::class, ['source' => $sourceId])
            ->fillForm([
                'mentions' => [[
                    'id' => $mention->id->value,
                    'kind' => 'person',
                    'local_key' => 'child',
                    'role' => 'child',
                    'display_label' => 'Józef Gajda',
                    'raw_data_json' => null,
                    'claims' => [
                        [
                            'claim_id' => $sex->id->value,
                            'field_key' => PredicateKey::PersonSex->value,
                            'value_raw' => 'syn',
                            'enum_key' => SexClaimValueKey::Male->value,
                            'raw_text' => 'urodziła syna',
                            'transcription_certainty' => 'certain',
                            'interpretation_certainty' => 'certain',
                        ],
                        [
                            'claim_id' => $religion->id->value,
                            'field_key' => PredicateKey::PersonReligiousAffiliation->value,
                            'value_raw' => 'religii katolickiej',
                            'raw_text' => 'oboje religii katolickiej',
                            'transcription_certainty' => 'certain',
                            'interpretation_certainty' => 'certain',
                        ],
                    ],
                ]],
            ], 'evidenceForm')
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertRedirect();

        $afterEdit = app(LoadSourceDraft::class)->handle(new SourceId($sourceId));
        $afterEditClaims = [];
        foreach ($afterEdit->current->claims as $claim) {
            $afterEditClaims[$claim->predicate->key->value] = $claim;
        }

        self::assertSame($sex->id->value, $afterEditClaims[PredicateKey::PersonSex->value]->id->value);
        self::assertSame('syn', $afterEditClaims[PredicateKey::PersonSex->value]->value?->raw());
        self::assertSame(
            SexClaimValueKey::Male->value,
            $afterEditClaims[PredicateKey::PersonSex->value]->value?->data()['key'] ?? null,
        );
        self::assertSame($religion->id->value, $afterEditClaims[PredicateKey::PersonReligiousAffiliation->value]->id->value);
        self::assertSame(
            'religii katolickiej',
            $afterEditClaims[PredicateKey::PersonReligiousAffiliation->value]->value?->raw(),
        );
        $this->assertDatabaseCount('claim_revisions', 4);
        $this->assertDatabaseCount('evidence_states', 2);
    }
}
