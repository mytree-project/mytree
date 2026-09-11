<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Acquisition\CaptureEvidenceStateForSource;
use App\Application\Acquisition\ClaimRevisionRepository;
use App\Application\Acquisition\CreateClaim;
use App\Application\Acquisition\CreateMention;
use App\Application\Acquisition\CreateSource;
use App\Application\Acquisition\CreateSourceLocator;
use App\Application\Acquisition\DetachSourceAsset;
use App\Application\Acquisition\GetEvidenceState;
use App\Application\Acquisition\ListClaimRevisions;
use App\Application\Acquisition\SourceAssetRepository;
use App\Application\Acquisition\SourceLocatorRepository;
use App\Application\Acquisition\SourceRepository;
use App\Application\Acquisition\SourceRevisionRepository;
use App\Application\Acquisition\StoreSourceAsset;
use App\Application\Acquisition\StoreSourceAssetInput;
use App\Application\Acquisition\UpdateSource;
use App\Application\Acquisition\UpdateSourceLocator;
use App\Domain\Acquisition\MentionKind;
use App\Domain\Acquisition\PdfPageLocatorValue;
use App\Domain\Acquisition\PredicateKey;
use App\Domain\Acquisition\PredicateVocabulary;
use App\Domain\Acquisition\SourceMetadata;
use App\Domain\Acquisition\SourceType;
use App\Domain\Acquisition\TextClaimValue;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

final class M3RevisionConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_locator_update_appends_one_revision_no_op_does_not_and_evidence_state_sees_update(): void
    {
        $source = app(CreateSource::class)->handle(SourceType::generic());
        $person = app(CreateMention::class)->handle($source->id, MentionKind::person(), 'person.subject');
        $claim = app(CreateClaim::class)->handle(
            sourceId: $source->id,
            subjectMentionId: $person->id,
            predicate: PredicateVocabulary::get(PredicateKey::PersonSurname),
            value: new TextClaimValue('Kowalski'),
        );
        $locator = app(CreateSourceLocator::class)->handle(
            sourceId: $source->id,
            claimId: $claim->id,
            value: new PdfPageLocatorValue(7),
        );

        app(UpdateSourceLocator::class)->handle(
            sourceId: $source->id,
            claimId: $claim->id,
            locatorId: $locator->id,
            value: new PdfPageLocatorValue(8),
            changeNote: 'Corrected page',
        );
        app(UpdateSourceLocator::class)->handle(
            sourceId: $source->id,
            claimId: $claim->id,
            locatorId: $locator->id,
            value: new PdfPageLocatorValue(8),
            changeNote: 'No-op must not create history',
        );

        $revisions = app(ListClaimRevisions::class)->handle($source->id, $claim->id);

        self::assertCount(3, $revisions);
        self::assertSame(7, $revisions[1]->reconstruct()->sourceLocators[0]->value->data()['page']);
        self::assertSame(8, $revisions[2]->reconstruct()->sourceLocators[0]->value->data()['page']);
        self::assertSame('Corrected page', $revisions[2]->changeNote);

        $evidenceState = app(CaptureEvidenceStateForSource::class)->capture($source->id);
        $resolved = app(GetEvidenceState::class)->get($evidenceState->id);

        self::assertCount(1, $resolved->claimRevisions);
        self::assertSame(8, $resolved->claimRevisions[0]->reconstruct()->sourceLocators[0]->value->data()['page']);
    }

    public function test_locator_update_rolls_back_when_claim_revision_append_fails(): void
    {
        $source = app(CreateSource::class)->handle(SourceType::generic());
        $person = app(CreateMention::class)->handle($source->id, MentionKind::person(), 'person.subject');
        $claim = app(CreateClaim::class)->handle(
            sourceId: $source->id,
            subjectMentionId: $person->id,
            predicate: PredicateVocabulary::get(PredicateKey::PersonSurname),
            value: new TextClaimValue('Kowalski'),
        );
        $locator = app(CreateSourceLocator::class)->handle(
            sourceId: $source->id,
            claimId: $claim->id,
            value: new PdfPageLocatorValue(7),
        );

        $this->mock(ClaimRevisionRepository::class, function (MockInterface $mock): void {
            $mock->shouldReceive('append')
                ->once()
                ->andThrow(new RuntimeException('Simulated ClaimRevision persistence failure.'));
        });

        try {
            app(UpdateSourceLocator::class)->handle(
                sourceId: $source->id,
                claimId: $claim->id,
                locatorId: $locator->id,
                value: new PdfPageLocatorValue(9),
            );
            self::fail('Expected ClaimRevision persistence failure.');
        } catch (RuntimeException $exception) {
            self::assertSame('Simulated ClaimRevision persistence failure.', $exception->getMessage());
        }

        $persisted = app(SourceLocatorRepository::class)->find($source->id, $claim->id, $locator->id);

        self::assertNotNull($persisted);
        self::assertSame(7, $persisted->value->data()['page']);
        $this->assertDatabaseCount('claim_revisions', 2);
    }

    public function test_source_no_op_does_not_append_revision_and_failed_revision_rolls_back_update(): void
    {
        $source = app(CreateSource::class)->handle(
            SourceType::generic(),
            new SourceMetadata(['stage' => 'original']),
        );

        app(UpdateSource::class)->handle(
            id: $source->id,
            type: SourceType::generic(),
            metadata: new SourceMetadata(['stage' => 'original']),
        );

        $this->assertDatabaseCount('source_revisions', 1);

        $this->mock(SourceRevisionRepository::class, function (MockInterface $mock): void {
            $mock->shouldReceive('append')
                ->once()
                ->andThrow(new RuntimeException('Simulated SourceRevision persistence failure.'));
        });

        try {
            app(UpdateSource::class)->handle(
                id: $source->id,
                type: SourceType::generic(),
                metadata: new SourceMetadata(['stage' => 'changed']),
            );
            self::fail('Expected SourceRevision persistence failure.');
        } catch (RuntimeException $exception) {
            self::assertSame('Simulated SourceRevision persistence failure.', $exception->getMessage());
        }

        $persisted = app(SourceRepository::class)->find($source->id);

        self::assertNotNull($persisted);
        self::assertSame('original', $persisted->metadata->toArray()['stage']);
        $this->assertDatabaseCount('source_revisions', 1);
    }

    public function test_asset_attachment_rolls_back_database_state_when_source_revision_append_fails(): void
    {
        config(['filesystems.default' => 'local']);
        Storage::fake('local');

        $source = app(CreateSource::class)->handle(SourceType::generic());

        $this->mock(SourceRevisionRepository::class, function (MockInterface $mock): void {
            $mock->shouldReceive('append')
                ->once()
                ->andThrow(new RuntimeException('Simulated SourceRevision persistence failure.'));
        });

        try {
            app(StoreSourceAsset::class)->handle(
                $source->id,
                new StoreSourceAssetInput(
                    contents: 'scan-bytes',
                    originalFilename: 'record.jpg',
                    mimeType: 'image/jpeg',
                    retrievedAt: new DateTimeImmutable('2026-09-11T08:00:00+00:00'),
                ),
            );
            self::fail('Expected SourceRevision persistence failure.');
        } catch (RuntimeException $exception) {
            self::assertSame('Simulated SourceRevision persistence failure.', $exception->getMessage());
        }

        self::assertCount(0, app(SourceAssetRepository::class)->forSource($source->id));
        $this->assertDatabaseCount('source_assets', 0);
        $this->assertDatabaseCount('source_revisions', 1);
    }

    public function test_asset_detach_rolls_back_when_source_revision_append_fails(): void
    {
        config(['filesystems.default' => 'local']);
        Storage::fake('local');

        $source = app(CreateSource::class)->handle(SourceType::generic());
        $asset = app(StoreSourceAsset::class)->handle(
            $source->id,
            new StoreSourceAssetInput(
                contents: 'scan-bytes',
                originalFilename: 'record.jpg',
                mimeType: 'image/jpeg',
                retrievedAt: new DateTimeImmutable('2026-09-11T08:00:00+00:00'),
            ),
        );

        $this->mock(SourceRevisionRepository::class, function (MockInterface $mock): void {
            $mock->shouldReceive('append')
                ->once()
                ->andThrow(new RuntimeException('Simulated SourceRevision persistence failure.'));
        });

        try {
            app(DetachSourceAsset::class)->handle($asset->id);
            self::fail('Expected SourceRevision persistence failure.');
        } catch (RuntimeException $exception) {
            self::assertSame('Simulated SourceRevision persistence failure.', $exception->getMessage());
        }

        $persisted = app(SourceAssetRepository::class)->find($asset->id);

        self::assertNotNull($persisted);
        self::assertSame($source->id->value, $persisted->sourceId?->value);
        $this->assertDatabaseCount('source_revisions', 2);
    }
}
