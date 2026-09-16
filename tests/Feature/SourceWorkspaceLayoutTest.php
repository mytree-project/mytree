<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Pages\Acquisition\SourceEditor;
use Filament\Support\Enums\Width;
use Tests\TestCase;

final class SourceWorkspaceLayoutTest extends TestCase
{
    public function test_source_workspace_uses_full_available_content_width(): void
    {
        self::assertSame(Width::Full, (new SourceEditor)->getMaxWidth());
    }
}
