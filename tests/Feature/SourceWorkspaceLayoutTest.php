<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Pages\Acquisition\SourceEditor;
use App\Infrastructure\Persistence\Eloquent\Models\User;
use Filament\Facades\Filament;
use Filament\Support\Enums\Width;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SourceWorkspaceLayoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_source_workspace_uses_full_available_content_width(): void
    {
        self::assertSame(Width::Full, (new SourceEditor)->getMaxContentWidth());

        $this->actingAs(User::factory()->admin()->create())
            ->get(SourceEditor::getUrl())
            ->assertOk()
            ->assertSee('fi-width-full', false);
    }
}
