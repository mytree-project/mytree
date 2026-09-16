<?php

declare(strict_types=1);

namespace App\Filament\Pages\Acquisition;

use App\Filament\Support\SourceWorkspacePage;

/**
 * Primary Source Acquisition page.
 *
 * SourceWorkspacePage keeps Filament behind the application boundaries and
 * persists through SaveSourceDraft / StageSourceAsset rather than Eloquent.
 */
final class SourceEditor extends SourceWorkspacePage {}
