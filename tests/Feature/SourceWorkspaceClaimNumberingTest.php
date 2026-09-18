<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Acquisition\CreateSource;
use App\Domain\Acquisition\MentionKind;
use App\Domain\Acquisition\PredicateKey;
use App\Domain\Acquisition\SourceType;
use App\Filament\Pages\Acquisition\SourceEditor;
use App\Infrastructure\Persistence\Eloquent\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class SourceWorkspaceClaimNumberingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $panel = Filament::getPanel('admin');
        Filament::setCurrentPanel($panel);

        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_claim_summaries_follow_current_repeater_order_after_remove_and_add_rerenders(): void
    {
        app()->setLocale('en');

        $source = app(CreateSource::class)->handle(SourceType::generic());
        $claim = $this->claimRow();

        $component = Livewire::test(SourceEditor::class, ['source' => $source->id->value])
            ->set('evidenceData.mentions', [$this->mentionRow()])
            ->set('evidenceData.fields', [$claim, $claim, $claim])
            ->assertSee('Claim 1 · Given name · person_jan · Jan')
            ->assertSee('Claim 2 · Given name · person_jan · Jan')
            ->assertSee('Claim 3 · Given name · person_jan · Jan');

        $component
            ->set('evidenceData.fields', [$claim, $claim])
            ->assertSee('Claim 1 · Given name · person_jan · Jan')
            ->assertSee('Claim 2 · Given name · person_jan · Jan')
            ->assertDontSee('Claim 3 · Given name · person_jan · Jan');

        $component
            ->set('evidenceData.fields', [$claim, $claim, $claim])
            ->assertSee('Claim 1 · Given name · person_jan · Jan')
            ->assertSee('Claim 2 · Given name · person_jan · Jan')
            ->assertSee('Claim 3 · Given name · person_jan · Jan');
    }

    public function test_claim_summaries_use_the_same_sequential_indexes_in_polish(): void
    {
        app()->setLocale('pl');

        $source = app(CreateSource::class)->handle(SourceType::generic());
        $claim = $this->claimRow();

        Livewire::test(SourceEditor::class, ['source' => $source->id->value])
            ->set('evidenceData.mentions', [$this->mentionRow()])
            ->set('evidenceData.fields', [$claim, $claim, $claim])
            ->assertSee('Twierdzenie 1 · Given name · person_jan · Jan')
            ->assertSee('Twierdzenie 2 · Given name · person_jan · Jan')
            ->assertSee('Twierdzenie 3 · Given name · person_jan · Jan');
    }

    /** @return array<string, mixed> */
    private function mentionRow(): array
    {
        return [
            'id' => null,
            'kind' => MentionKind::PERSON,
            'local_key' => 'person_jan',
            'role' => null,
            'display_label' => 'Jan Kowalski',
            'raw_data_json' => '{}',
        ];
    }

    /** @return array<string, mixed> */
    private function claimRow(): array
    {
        return [
            'claim_id' => null,
            'presentation_origin' => null,
            'field_key' => PredicateKey::PersonGivenName->value,
            'subject_local_key' => 'person_jan',
            'object_local_key' => null,
            'value_raw' => 'Jan',
            'transcription_certainty' => 'unspecified',
            'interpretation_certainty' => 'unspecified',
        ];
    }
}
