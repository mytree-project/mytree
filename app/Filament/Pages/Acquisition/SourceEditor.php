<?php

declare(strict_types=1);

namespace App\Filament\Pages\Acquisition;

use App\Filament\Support\BasicSourceEditorPage;

/**
 * Primary Source Acquisition page.
 *
 * BasicSourceEditorPage keeps Filament behind the application boundaries and
 * persists through SaveSourceDraft / StageSourceAsset rather than Eloquent.
 */
final class SourceEditor extends BasicSourceEditorPage
{
}