<?php

declare(strict_types=1);

namespace App\Filament\Pages\Acquisition;

use App\Filament\Support\SourceWorkspacePage;
use Filament\Support\Enums\Width;

/**
 * Primary Source Acquisition page.
 *
 * SourceWorkspacePage keeps Filament behind the application boundaries and
 * persists through SaveSourceDraft / StageSourceAsset rather than Eloquent.
 */
final class SourceEditor extends SourceWorkspacePage
{
    public function getMaxWidth(): Width|string|null
    {
        return Width::Full;
    }
}
