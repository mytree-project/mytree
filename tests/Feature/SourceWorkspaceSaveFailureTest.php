<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Acquisition\CreateSource;
use App\Domain\Acquisition\MentionKind;
use App\Domain\Acquisition\SourceType;
use App\Filament\Pages\Acquisition\SourceEditor;
use App\Infrastructure\Persistence\Eloquent\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

final class SourceWorkspaceSaveFailureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $panel = Filament::getPanel('admin');
        Filament::setCurrentPanel($panel);
    }

    public function test_structured_validation_failure_is_visible_preserves_state_and_does_not_persist(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        app()->setLocale('pl');
        $source = app(CreateSource::class)->handle(SourceType::generic());

        $sourceRevisionCount = DB::table('source_revisions')->count();
        $mentionRevisionCount = DB::table('mention_revisions')->count();
        $claimRevisionCount = DB::table('claim_revisions')->count();
        $evidenceStateCount = DB::table('evidence_states')->count();

        Livewire::test(SourceEditor::class, ['source' => $source->id->value])
            ->set('evidenceData.mentions', [
                [
                    'id' => null,
                    'kind' => MentionKind::PERSON,
                    'local_key' => 'person_hedvig',
                    'role' => 'mother',
                    'display_label' => 'Hedvig Wiśniewska',
                    'raw_data_json' => '{}',
                ],
                [
                    'id' => null,
                    'kind' => MentionKind::PERSON,
                    'local_key' => 'person_valentin',
                    'role' => 'declarant',
                    'display_label' => 'Valentin Wiśniewski',
                    'raw_data_json' => '{"broken":',
                ],
            ])
            ->call('save')
            ->assertHasErrors(['evidenceData.mentions.1.raw_data_json'])
            ->assertSet('evidenceData.mentions.0.local_key', 'person_hedvig')
            ->assertSet('evidenceData.mentions.1.local_key', 'person_valentin')
            ->assertSet('evidenceData.mentions.1.display_label', 'Valentin Wiśniewski')
            ->assertSet('workspaceValidationTargets', [[
                'type' => 'mention',
                'index' => 1,
            ]])
            ->assertSet('workspaceHasUntargetedEvidenceValidationError', false)
            ->assertNoRedirect()
            ->assertSee(__('ui.workspace.save_failed'))
            ->assertSee('Wzmianka nr 2 (person_valentin) zawiera błąd składni JSON.')
            ->assertSee(__('workspace_validation.technical_details'))
            ->assertSee('Syntax error')
            ->assertSeeHtml('data-source-workspace-save-errors');

        self::assertSame($sourceRevisionCount, DB::table('source_revisions')->count());
        self::assertSame($mentionRevisionCount, DB::table('mention_revisions')->count());
        self::assertSame($claimRevisionCount, DB::table('claim_revisions')->count());
        self::assertSame($evidenceStateCount, DB::table('evidence_states')->count());
    }

    public function test_application_validation_uses_localized_summary_and_keeps_technical_detail(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        app()->setLocale('pl');
        $source = app(CreateSource::class)->handle(SourceType::generic());
        $sourceRevisionCount = DB::table('source_revisions')->count();

        Livewire::test(SourceEditor::class, ['source' => $source->id->value])
            ->set('data.metadata', [[
                'key' => 'record_number',
                'type' => 'integer',
                'value' => 'abc',
            ]])
            ->call('save')
            ->assertHasErrors(['data.metadata.0.value'])
            ->assertNoRedirect()
            ->assertSee('Pole metadanych nr 1 wymaga prawidłowej liczby całkowitej.')
            ->assertSee(__('workspace_validation.technical_details'))
            ->assertSee('Enter a valid integer.');

        self::assertSame($sourceRevisionCount, DB::table('source_revisions')->count());
    }

    public function test_claim_domain_failure_targets_claim_instead_of_source_details(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        app()->setLocale('pl');
        $source = app(CreateSource::class)->handle(SourceType::generic());

        Livewire::test(SourceEditor::class, ['source' => $source->id->value])
            ->set('evidenceData.mentions', [[
                'id' => null,
                'kind' => MentionKind::PLACE,
                'local_key' => 'place_sobotka',
                'role' => null,
                'display_label' => 'Sobótka',
                'raw_data_json' => '{}',
            ]])
            ->set('evidenceData.fields', [[
                'claim_id' => null,
                'presentation_origin' => null,
                'field_key' => 'person.given_name',
                'subject_local_key' => 'place_sobotka',
                'object_local_key' => null,
                'value_raw' => 'Sobótka',
                'transcription_certainty' => 'unspecified',
                'interpretation_certainty' => 'unspecified',
            ]])
            ->call('save')
            ->assertHasErrors(['evidenceData.fields.0.subject_local_key'])
            ->assertHasNoErrors(['data'])
            ->assertSet('workspaceValidationTargets', [[
                'type' => 'claim',
                'index' => 0,
            ]])
            ->assertSet('workspaceHasUntargetedEvidenceValidationError', false)
            ->assertNoRedirect()
            ->assertSee('Twierdzenie nr 1 zawiera nieprawidłowe dane.')
            ->assertSee(__('workspace_validation.technical_details'))
            ->assertSee('Predicate &quot;person.given_name&quot; requires a &quot;person&quot; subject Mention.', escape: false);
    }

    public function test_event_claim_failure_targets_nested_claim_and_enclosing_event_path(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        app()->setLocale('pl');
        $source = app(CreateSource::class)->handle(SourceType::generic());

        Livewire::test(SourceEditor::class, ['source' => $source->id->value])
            ->set('evidenceData.event_contexts', [[
                'id' => null,
                'local_key' => 'event.birth.1',
                'role' => 'birth',
                'display_label' => 'Birth event',
                'raw_data_json' => '{}',
                'claims' => [[
                    'claim_id' => null,
                    'presentation_origin' => null,
                    'field_key' => 'event.place',
                    'subject_local_key' => null,
                    'object_local_key' => 'missing-place',
                    'value_raw' => null,
                    'transcription_certainty' => 'unspecified',
                    'interpretation_certainty' => 'unspecified',
                ]],
            ]])
            ->call('save')
            ->assertHasErrors(['evidenceData.event_contexts.0.claims.0.object_local_key'])
            ->assertSet('workspaceValidationTargets', [[
                'type' => 'event_claim',
                'event_index' => 0,
                'claim_index' => 0,
            ]])
            ->assertSet('workspaceHasUntargetedEvidenceValidationError', false)
            ->assertNoRedirect()
            ->assertSee('Twierdzenie nr 1 w Zdarzeniu nr 1 zawiera nieprawidłowe dane.')
            ->assertSee(__('workspace_validation.technical_details'))
            ->assertSee('Object Mention local key &quot;missing-place&quot; does not exist in this Source.', escape: false);
    }

    public function test_event_validation_uses_polish_event_name(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        app()->setLocale('pl');
        $source = app(CreateSource::class)->handle(SourceType::generic());

        Livewire::test(SourceEditor::class, ['source' => $source->id->value])
            ->set('evidenceData.event_contexts', [[
                'id' => null,
                'local_key' => 'event.birth.1',
                'role' => 'birth',
                'display_label' => 'Birth event',
                'raw_data_json' => '{"broken":',
                'claims' => [],
            ]])
            ->call('save')
            ->assertHasErrors(['evidenceData.event_contexts.0.raw_data_json'])
            ->assertNoRedirect()
            ->assertSee('Zdarzenie nr 1 (event.birth.1) zawiera błąd składni JSON.')
            ->assertSee(__('workspace_validation.technical_details'))
            ->assertSee('Syntax error');
    }

    public function test_english_validation_object_names_remain_unchanged(): void
    {
        app()->setLocale('en');

        self::assertSame(
            'Mention no. 1 contains invalid data.',
            __('workspace_validation.mention_invalid', ['number' => 1, 'key_suffix' => '']),
        );
        self::assertSame(
            'Claim no. 1 contains invalid data.',
            __('workspace_validation.claim_invalid', ['number' => 1]),
        );
        self::assertSame(
            'Event no. 1 contains invalid data.',
            __('workspace_validation.event_invalid', ['number' => 1, 'key_suffix' => '']),
        );
    }

    public function test_missing_claim_subject_uses_specific_message_and_targeted_path(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        app()->setLocale('pl');
        $source = app(CreateSource::class)->handle(SourceType::generic());

        Livewire::test(SourceEditor::class, ['source' => $source->id->value])
            ->set('evidenceData.fields', [[
                'claim_id' => null,
                'presentation_origin' => null,
                'field_key' => 'person.occupation',
                'subject_local_key' => 'missing-person',
                'object_local_key' => null,
                'value_raw' => 'rolnik',
                'transcription_certainty' => 'unspecified',
                'interpretation_certainty' => 'unspecified',
            ]])
            ->call('save')
            ->assertHasErrors(['evidenceData.fields.0.subject_local_key'])
            ->assertHasNoErrors(['data'])
            ->assertNoRedirect()
            ->assertSee('Twierdzenie nr 1 odwołuje się do nieistniejącej Wzmianki jako podmiotu.')
            ->assertSee(__('workspace_validation.technical_details'))
            ->assertSee('Subject Mention local key &quot;missing-person&quot; does not exist in this Source.', escape: false);
    }
}
