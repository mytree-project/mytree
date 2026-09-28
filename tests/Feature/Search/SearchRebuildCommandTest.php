<?php

declare(strict_types=1);

namespace Tests\Feature\Search;

use App\Application\Acquisition\CaptureEvidenceStateForSource;
use App\Application\Acquisition\CreateSource;
use App\Application\Search\RebuildSourceSearchDocument;
use App\Application\Search\SearchDocumentRepository;
use App\Application\Search\SearchProjectionScheduler;
use App\Domain\Acquisition\Source;
use App\Domain\Acquisition\SourceId;
use App\Domain\Acquisition\SourceMetadata;
use App\Domain\Acquisition\SourceType;
use App\Infrastructure\Search\RebuildSourceSearchDocumentJob;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class SearchRebuildCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_single_source_rebuild_command_queues_existing_unique_rebuild_job(): void
    {
        $this->disableAutomaticSearchScheduling();
        $source = $this->createAcceptedSource('Single rebuild');
        Queue::fake();

        $this->artisan('search:rebuild '.$source->id->value)
            ->expectsOutput('Queued SearchDocument rebuild for Source '.$source->id->value.'.')
            ->assertSuccessful()
            ->execute();

        Queue::assertPushed(
            RebuildSourceSearchDocumentJob::class,
            static fn (RebuildSourceSearchDocumentJob $job): bool => $job->sourceId === $source->id->value,
        );
    }

    public function test_single_source_rebuild_command_rejects_source_without_accepted_evidence(): void
    {
        $source = app(CreateSource::class)->handle(
            SourceType::generic(),
            new SourceMetadata(['title' => 'No accepted evidence']),
        );
        Queue::fake();

        $this->artisan('search:rebuild '.$source->id->value)
            ->expectsOutput('Source '.$source->id->value.' has no accepted EvidenceState to index.')
            ->assertExitCode(Command::FAILURE)
            ->execute();

        Queue::assertNothingPushed();
    }

    public function test_stale_rebuild_command_queues_stale_missing_and_outdated_projections_only(): void
    {
        $this->disableAutomaticSearchScheduling();
        $missingProjection = $this->createAcceptedSource('Missing projection');
        $staleProjection = $this->createAcceptedSource('Stale projection');
        $outdatedProjection = $this->createAcceptedSource('Outdated projection');
        $freshProjection = $this->createAcceptedSource('Fresh projection');
        $noEvidence = app(CreateSource::class)->handle(
            SourceType::generic(),
            new SourceMetadata(['title' => 'No evidence']),
        );

        $rebuild = app(RebuildSourceSearchDocument::class);
        $rebuild->handle($staleProjection->id);
        $rebuild->handle($outdatedProjection->id);
        $rebuild->handle($freshProjection->id);
        app(SearchDocumentRepository::class)->markStale($staleProjection->id);
        DB::table('search_documents')
            ->where('source_id', $outdatedProjection->id->value)
            ->update(['index_signature' => str_repeat('0', 64)]);

        Queue::fake();

        $this->artisan('search:rebuild-stale')
            ->expectsOutput('Queued 3 SearchDocument rebuilds.')
            ->assertSuccessful()
            ->execute();

        Queue::assertPushed(RebuildSourceSearchDocumentJob::class, 3);
        Queue::assertPushed(
            RebuildSourceSearchDocumentJob::class,
            static fn (RebuildSourceSearchDocumentJob $job): bool => $job->sourceId === $missingProjection->id->value,
        );
        Queue::assertPushed(
            RebuildSourceSearchDocumentJob::class,
            static fn (RebuildSourceSearchDocumentJob $job): bool => $job->sourceId === $staleProjection->id->value,
        );
        Queue::assertPushed(
            RebuildSourceSearchDocumentJob::class,
            static fn (RebuildSourceSearchDocumentJob $job): bool => $job->sourceId === $outdatedProjection->id->value,
        );
        Queue::assertNotPushed(
            RebuildSourceSearchDocumentJob::class,
            static fn (RebuildSourceSearchDocumentJob $job): bool => $job->sourceId === $freshProjection->id->value,
        );
        Queue::assertNotPushed(
            RebuildSourceSearchDocumentJob::class,
            static fn (RebuildSourceSearchDocumentJob $job): bool => $job->sourceId === $noEvidence->id->value,
        );
    }

    private function disableAutomaticSearchScheduling(): void
    {
        $this->app->instance(SearchProjectionScheduler::class, new class implements SearchProjectionScheduler
        {
            public function sourceChanged(SourceId $sourceId): void
            {
                // Intentionally disabled in command tests.
            }
        });
    }

    private function createAcceptedSource(string $title): Source
    {
        $source = app(CreateSource::class)->handle(
            SourceType::generic(),
            new SourceMetadata(['title' => $title]),
        );
        app(CaptureEvidenceStateForSource::class)->capture($source->id);

        return $source;
    }
}
