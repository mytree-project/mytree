<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Acquisition\CreateMention;
use App\Application\Acquisition\CreateSource;
use App\Domain\Acquisition\MentionKind;
use App\Domain\Acquisition\SourceType;
use App\Filament\Pages\Acquisition\SourceEditor;
use App\Infrastructure\Persistence\Eloquent\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class SourceWorkspaceMentionLocalKeyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_add_mention_action_assigns_m1_in_a_blank_draft(): void
    {
        $undoRepeaterFake = Repeater::fake();

        try {
            Livewire::test(SourceEditor::class)
                ->callFormComponentAction('mentions', 'add', [], [], 'evidenceForm')
                ->assertSet('evidenceData.mentions.0.local_key', 'M1');
        } finally {
            $undoRepeaterFake();
        }
    }

    public function test_add_mention_action_uses_the_next_unused_key_across_persisted_and_unsaved_mentions(): void
    {
        $source = app(CreateSource::class)->handle(SourceType::generic());

        app(CreateMention::class)->handle(
            sourceId: $source->id,
            kind: MentionKind::person(),
            localKey: 'M1',
        );
        app(CreateMention::class)->handle(
            sourceId: $source->id,
            kind: MentionKind::person(),
            localKey: 'M3',
        );

        $undoRepeaterFake = Repeater::fake();

        try {
            Livewire::test(SourceEditor::class, ['source' => $source->id->value])
                ->callFormComponentAction('mentions', 'add', [], [], 'evidenceForm')
                ->assertSet('evidenceData.mentions.2.local_key', 'M2')
                ->callFormComponentAction('mentions', 'add', [], [], 'evidenceForm')
                ->assertSet('evidenceData.mentions.3.local_key', 'M4');
        } finally {
            $undoRepeaterFake();
        }
    }
}
