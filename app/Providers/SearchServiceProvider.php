<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Search\CurrentEvidenceStateLookup;
use App\Application\Search\SearchDocumentRepository;
use App\Application\Search\SearchIndexProfile;
use App\Application\Search\SearchProjectionScheduler;
use App\Infrastructure\Persistence\Eloquent\Search\EloquentCurrentEvidenceStateLookup;
use App\Infrastructure\Persistence\Eloquent\Search\EloquentSearchDocumentRepository;
use App\Infrastructure\Search\LaravelSearchProjectionScheduler;
use Illuminate\Support\ServiceProvider;

final class SearchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            SearchIndexProfile::class,
            static fn (): SearchIndexProfile => SearchIndexProfile::m5Foundation(),
        );
        $this->app->bind(SearchDocumentRepository::class, EloquentSearchDocumentRepository::class);
        $this->app->bind(CurrentEvidenceStateLookup::class, EloquentCurrentEvidenceStateLookup::class);
        $this->app->bind(SearchProjectionScheduler::class, LaravelSearchProjectionScheduler::class);
    }
}
