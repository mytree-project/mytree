<?php

declare(strict_types=1);

namespace App\Filament\Pages\Acquisition;

use App\Application\Acquisition\BrowseSources;
use App\Application\Acquisition\SourceBrowseItem;
use Filament\Actions\Action;
use Filament\Pages\Page;
use JsonException;

final class Sources extends Page
{
    protected static ?string $slug = 'acquisition/sources';

    protected static ?string $navigationLabel = 'Source Acquisition';

    protected static ?string $title = 'Sources';

    protected string $view = 'filament.pages.acquisition.sources';

    public string $search = '';

    /** @return list<SourceBrowseItem> */
    public function sources(): array
    {
        return app(BrowseSources::class)->search($this->search);
    }

    public function metadataSummary(SourceBrowseItem $source): string
    {
        if ($source->metadata->toArray() === []) {
            return 'No metadata';
        }

        try {
            return json_encode(
                $source->metadata->toArray(),
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            );
        } catch (JsonException) {
            return 'Metadata unavailable';
        }
    }

    /** @return list<Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label('Create Source')
                ->url(SourceEditor::getUrl()),
        ];
    }
}
