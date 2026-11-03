<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Application\Search\SearchMatchType;
use App\Application\Search\SearchPage;
use App\Application\Search\SearchRequest;
use App\Application\Search\SearchSortMode;
use App\Application\Search\SearchSourceResult;
use App\Application\Search\SearchSources as SearchSourcesUseCase;
use App\Filament\Pages\Acquisition\SourceEditor;
use Filament\Pages\Page;
use InvalidArgumentException;
use Livewire\Attributes\Url;

final class SearchSources extends Page
{
    private const PER_PAGE = 20;

    protected static ?string $slug = 'search';

    protected string $view = 'filament.pages.search-sources';

    #[Url(as: 'q')]
    public string $query = '';

    #[Url(as: 'type')]
    public string $sourceTypeFilter = '';

    #[Url(as: 'place')]
    public string $placeFilter = '';

    #[Url(as: 'from')]
    public string $dateFrom = '';

    #[Url(as: 'to')]
    public string $dateTo = '';

    #[Url(as: 'sort')]
    public string $sortMode = 'relevance';

    public int $resultPage = 1;

    public static function getNavigationLabel(): string
    {
        return __('search.navigation_label');
    }

    public function getTitle(): string
    {
        return __('search.title');
    }

    public function updated(): void
    {
        $this->resultPage = 1;
    }

    public function hasInvalidDateFilter(): bool
    {
        try {
            new SearchRequest(
                query: '',
                dateFrom: $this->dateFrom,
                dateTo: $this->dateTo,
            );

            return false;
        } catch (InvalidArgumentException) {
            return true;
        }
    }

    public function searchPage(): ?SearchPage
    {
        if (
            trim($this->query) === ''
            && trim($this->sourceTypeFilter) === ''
            && trim($this->placeFilter) === ''
            && trim($this->dateFrom) === ''
            && trim($this->dateTo) === ''
            && $this->sortMode === SearchSortMode::Relevance->value
        ) {
            return null;
        }

        if ($this->hasInvalidDateFilter()) {
            return null;
        }

        return app(SearchSourcesUseCase::class)->handle(new SearchRequest(
            query: $this->query,
            page: $this->resultPage,
            perPage: self::PER_PAGE,
            sourceTypeKeys: trim($this->sourceTypeFilter) === '' ? [] : [trim($this->sourceTypeFilter)],
            place: $this->placeFilter,
            dateFrom: $this->dateFrom,
            dateTo: $this->dateTo,
            sort: SearchSortMode::tryFrom($this->sortMode) ?? SearchSortMode::Relevance,
        ));
    }

    public function previousResultPage(): void
    {
        $this->resultPage = max(1, $this->resultPage - 1);
    }

    public function nextResultPage(): void
    {
        $page = $this->searchPage();
        if ($page?->hasMore === true) {
            $this->resultPage++;
        }
    }

    public function sourceUrl(SearchSourceResult $result): string
    {
        return SourceEditor::getUrl(['source' => $result->sourceId->value]);
    }

    public function matchTypeLabel(SearchMatchType $type): string
    {
        return __('search.match_types.'.$type->value);
    }

    public function fieldLabel(string $field): string
    {
        $key = match ($field) {
            'source.name' => 'source_name',
            'source.metadata.title' => 'source_title',
            'person.given_name' => 'person_given_name',
            'person.surname' => 'person_surname',
            'place.name' => 'place_name',
            default => null,
        };

        return $key === null ? $field : __('search.fields.'.$key);
    }
}
