<?php

declare(strict_types=1);

namespace App\Filament\Pages\Acquisition;

use App\Application\Acquisition\BrowseSources;
use App\Application\Acquisition\SourceBrowseItem;
use Filament\Actions\Action;
use Filament\Pages\Page;

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
        $metadata = $source->metadata->toArray();
        if ($metadata === []) {
            return 'No metadata';
        }

        $parts = [];
        foreach ($metadata as $key => $value) {
            $parts[] = sprintf(
                '%s: %s',
                str_replace('_', ' ', $key),
                $this->metadataValueSummary($value),
            );
        }

        return implode(' · ', $parts);
    }

    private function metadataValueSummary(mixed $value): string
    {
        return match (true) {
            is_string($value) => $value,
            is_int($value), is_float($value) => (string) $value,
            is_bool($value) => $value ? 'true' : 'false',
            $value === null => 'null',
            is_array($value) => sprintf('[%d items]', count($value)),
            default => 'unsupported value',
        };
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
