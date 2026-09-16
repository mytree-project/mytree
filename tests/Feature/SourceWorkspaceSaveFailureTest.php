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
            ->assertNoRedirect()
            ->assertSee(__('ui.workspace.save_failed'))
            ->assertSee('Błąd składni JSON w Mention nr 2 (person_valentin).')
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
}
