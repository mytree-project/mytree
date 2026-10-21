<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Search\CurrentEvidenceStateLookup;
use App\Application\Search\DirectSearchCandidateRepository;
use App\Application\Search\SearchDocumentRepository;
use App\Application\Search\SearchIndexNameProcessingProfile;
use App\Application\Search\SearchIndexProfile;
use App\Application\Search\SearchNameProcessor;
use App\Application\Search\SearchProjectionRebuildTargetRepository;
use App\Application\Search\SearchProjectionScheduler;
use App\Application\Search\SearchRuntimeConfiguration;
use App\Infrastructure\Persistence\Eloquent\Search\EloquentCurrentEvidenceStateLookup;
use App\Infrastructure\Persistence\Eloquent\Search\EloquentDirectSearchCandidateRepository;
use App\Infrastructure\Persistence\Eloquent\Search\EloquentSearchDocumentRepository;
use App\Infrastructure\Persistence\Eloquent\Search\EloquentSearchProjectionRebuildTargetRepository;
use App\Infrastructure\Search\LaravelSearchProjectionScheduler;
use App\Infrastructure\Search\NameProcessingSearchNameProcessor;
use Composer\InstalledVersions;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use LogicException;
use MyTree\NameProcessing\Folding\IcuNameFolder;
use MyTree\NameProcessing\Normalization\IcuNameNormalizer;
use MyTree\NameProcessing\Profile\JsonProfileRepository;
use MyTree\NameProcessing\Transliteration\IcuTransliterator;

final class SearchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            SearchNameProcessor::class,
            static function (): SearchNameProcessor {
                $packageRoot = InstalledVersions::getInstallPath('mytree/name-processing');
                $packageReference = InstalledVersions::getReference('mytree/name-processing');

                if ($packageRoot === null || $packageReference === null || $packageReference === '') {
                    throw new LogicException('Installed mytree/name-processing package metadata is incomplete.');
                }

                $profiles = new JsonProfileRepository($packageRoot.'/resources/profiles');

                return new NameProcessingSearchNameProcessor(
                    normalizer: new IcuNameNormalizer($profiles),
                    transliterator: new IcuTransliterator($profiles),
                    folder: new IcuNameFolder($profiles),
                    profiles: $profiles,
                    packageReference: $packageReference,
                );
            },
        );
        $this->app->singleton(
            SearchIndexProfile::class,
            static function (Application $app): SearchIndexProfile {
                $nameProcessing = new SearchIndexNameProcessingProfile(
                    normalizeProfile: (string) config('search.name_processing.normalize_profile', 'default'),
                    transliterateProfile: (string) config('search.name_processing.transliterate_profile', 'cyrillic-latin'),
                    foldProfile: (string) config('search.name_processing.fold_profile', 'latin-search'),
                );
                $processor = $app->make(SearchNameProcessor::class);

                return SearchIndexProfile::m5Foundation(
                    nameProcessing: $nameProcessing,
                    processingSignature: $processor->signature($nameProcessing),
                );
            },
        );
        $this->app->singleton(
            SearchRuntimeConfiguration::class,
            static fn (): SearchRuntimeConfiguration => new SearchRuntimeConfiguration(
                maxPageSize: (int) config('search.max_page_size', 100),
                candidateLimit: (int) config('search.candidate_limit', 1000),
                queryExpansionLimit: (int) config('search.query_expansion_limit', 32),
                fuzzyEnabled: (bool) config('search.fuzzy.enabled', false),
                fuzzyThreshold: (float) config('search.fuzzy.threshold', 0.78),
                fuzzyCandidateLimit: (int) config('search.fuzzy.candidate_limit', 200),
                directSourceMetadataWeight: (int) config('search.ranking.direct_source_metadata', 100),
                directClaimValueWeight: (int) config('search.ranking.direct_claim_value', 100),
                sourceRepresentationWeight: (int) config('search.ranking.source_representation', 90),
                sameMentionBonus: (int) config('search.ranking.same_mention_bonus', 5),
            ),
        );
        $this->app->bind(SearchDocumentRepository::class, EloquentSearchDocumentRepository::class);
        $this->app->bind(CurrentEvidenceStateLookup::class, EloquentCurrentEvidenceStateLookup::class);
        $this->app->bind(DirectSearchCandidateRepository::class, EloquentDirectSearchCandidateRepository::class);
        $this->app->bind(SearchProjectionRebuildTargetRepository::class, EloquentSearchProjectionRebuildTargetRepository::class);
        $this->app->bind(SearchProjectionScheduler::class, LaravelSearchProjectionScheduler::class);
    }
}
