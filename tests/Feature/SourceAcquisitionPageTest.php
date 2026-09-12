<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Acquisition\BrowseSources;
use App\Application\Acquisition\CreateSource;
use App\Application\Acquisition\LoadSourceDraft;
use App\Application\Acquisition\SourceAssetRepository;
use App\Application\Acquisition\StoreSourceAsset;
use App\Application\Acquisition\StoreSourceAssetInput;
use App\Domain\Acquisition\SourceId;
use App\Domain\Acquisition\SourceMetadata;
use App\Domain\Acquisition\SourceTextKind;
use App\Domain\Acquisition\SourceType;
use App\Filament\Pages\Acquisition\SourceEditor;
use App\Filament\Pages\Acquisition\Sources;
use App\Infrastructure\Persistence\Eloquent\Models\User;
use DateTimeImmutable;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

final class SourceAcquisitionPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $panel = Filament::getPanel('admin');
        Filament::setCurrentPanel($panel);
    }

    public function test_only_administrator_can_open_source_acquisition_workspace(): void
    {
        $administrator = User::factory()->admin()->create();
        $regularUser = User::factory()->create();

        $this->actingAs($administrator)
            ->get('/admin/acquisition/sources')
            ->assertOk()
            ->assertSee('Sources')
            ->assertSee('Create Source');

        $this->actingAs($administrator)
            ->get('/admin/acquisition/source')
            ->assertOk()
            ->assertSee('Create Source')
            ->assertSee('Source metadata')
            ->assertSee('Source text')
            ->assertSee('Attach new assets');

        $this->actingAs($regularUser)
            ->get('/admin/acquisition/sources')
            ->assertForbidden();
    }

    public function test_blank_workspace_creates_source_with_scalar_metadata_and_repeated_source_text(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(SourceEditor::class)
            ->fillForm([
                'source_type_key' => 'generic',
                'source_type_schema_version' => 1,
                'metadata' => [
                    ['key' => 'archive_reference', 'type' => 'string', 'value' => 'Fond 12 / Act 7'],
                    ['key' => 'page', 'type' => 'integer', 'value' => '4'],
                ],
                'texts' => [
                    [
                        'id' => null,
                        'kind' => SourceTextKind::Transcription->value,
                        'language' => 'ru',
                        'content' => 'Original transcription',
                    ],
                    [
                        'id' => null,
                        'kind' => SourceTextKind::Translation->value,
                        'language' => 'pl',
                        'content' => 'Polish translation',
                    ],
                ],
                'detach_asset_ids' => [],
                'uploads' => [],
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertRedirect();

        $record = DB::table('sources')->first();
        self::assertNotNull($record);
        $draft = app(LoadSourceDraft::class)->handle(new SourceId((string) $record->id));

        self::assertSame('Fond 12 / Act 7', $draft->current->source->metadata->toArray()['archive_reference']);
        self::assertSame(4, $draft->current->source->metadata->toArray()['page']);
        self::assertCount(2, $draft->current->source->texts);
        self::assertSame(SourceTextKind::Transcription, $draft->current->source->texts[0]->kind);
        self::assertSame(SourceTextKind::Translation, $draft->current->source->texts[1]->kind);
        $this->assertDatabaseCount('source_revisions', 1);
        $this->assertDatabaseCount('evidence_states', 1);
    }

    public function test_editor_changes_existing_source_and_semantic_noop_does_not_add_history(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $source = app(CreateSource::class)->handle(
            type: SourceType::generic(),
            metadata: new SourceMetadata(['archive' => 'A']),
        );

        Livewire::test(SourceEditor::class, ['source' => $source->id->value])
            ->fillForm([
                'source_type_key' => 'generic',
                'source_type_schema_version' => 1,
                'metadata' => [
                    ['key' => 'archive', 'type' => 'string', 'value' => 'B'],
                ],
                'texts' => [
                    [
                        'id' => null,
                        'kind' => SourceTextKind::ResearchNote->value,
                        'language' => 'pl',
                        'content' => 'Checked against second scan.',
                    ],
                ],
                'detach_asset_ids' => [],
                'uploads' => [],
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertRedirect();

        $afterEdit = app(LoadSourceDraft::class)->handle($source->id);
        self::assertSame('B', $afterEdit->current->source->metadata->toArray()['archive']);
        self::assertCount(1, $afterEdit->current->source->texts);

        $revisionsBeforeNoop = DB::table('source_revisions')->count();
        $evidenceBeforeNoop = DB::table('evidence_states')->count();

        Livewire::test(SourceEditor::class, ['source' => $source->id->value])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertRedirect();

        self::assertSame($revisionsBeforeNoop, DB::table('source_revisions')->count());
        self::assertSame($evidenceBeforeNoop, DB::table('evidence_states')->count());
    }

    public function test_asset_upload_is_attached_by_source_draft_and_detach_keeps_stored_bytes(): void
    {
        config(['filesystems.default' => 'local']);
        Storage::fake('local');
        $this->actingAs(User::factory()->admin()->create());
        $source = app(CreateSource::class)->handle(SourceType::generic());

        Livewire::test(SourceEditor::class, ['source' => $source->id->value])
            ->fillForm([
                'uploads' => [UploadedFile::fake()->createWithContent('scan.txt', 'source-scan-bytes')],
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertRedirect();

        $assets = app(SourceAssetRepository::class)->forSource($source->id);
        self::assertCount(1, $assets);
        $asset = $assets[0];
        self::assertSame(hash('sha256', 'source-scan-bytes'), $asset->sha256);
        Storage::disk('local')->assertExists($asset->storage->path);

        Livewire::test(SourceEditor::class, ['source' => $source->id->value])
            ->fillForm([
                'detach_asset_ids' => [$asset->id->value],
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertRedirect();

        self::assertCount(0, app(SourceAssetRepository::class)->forSource($source->id));
        $detached = app(SourceAssetRepository::class)->find($asset->id);
        self::assertNotNull($detached);
        self::assertNull($detached->sourceId);
        Storage::disk('local')->assertExists($asset->storage->path);
    }

    public function test_source_list_searches_by_basic_metadata_through_application_read_model(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $wanted = app(CreateSource::class)->handle(
            type: new SourceType('civil.birth'),
            metadata: new SourceMetadata(['archive_reference' => 'Unique Fond 42']),
        );
        app(CreateSource::class)->handle(
            type: new SourceType('civil.marriage'),
            metadata: new SourceMetadata(['archive_reference' => 'Other fond']),
        );

        $results = app(BrowseSources::class)->search('Unique Fond');

        self::assertCount(1, $results);
        self::assertSame($wanted->id->value, $results[0]->id->value);

        Livewire::test(Sources::class)
            ->set('search', 'Unique Fond')
            ->assertSee($wanted->id->value)
            ->assertDontSee('Other fond');
    }

    public function test_filament_acquisition_adapter_does_not_import_eloquent_acquisition_models(): void
    {
        $source = file_get_contents(app_path('Filament/Pages/Acquisition/SourceEditor.php'));
        self::assertIsString($source);
        self::assertStringNotContainsString('Infrastructure\\Persistence\\Eloquent\\Acquisition', $source);
        self::assertStringContainsString('SaveSourceDraft', $source);
        self::assertStringContainsString('StageSourceAsset', $source);
    }

    public function test_existing_store_source_asset_behavior_remains_available_for_non_draft_callers(): void
    {
        config(['filesystems.default' => 'local']);
        Storage::fake('local');
        $source = app(CreateSource::class)->handle(SourceType::generic());

        $asset = app(StoreSourceAsset::class)->handle(
            $source->id,
            new StoreSourceAssetInput(
                contents: 'legacy-boundary-bytes',
                originalFilename: 'existing-boundary.txt',
                mimeType: 'text/plain',
                retrievedAt: new DateTimeImmutable('2026-09-12T12:00:00+00:00'),
            ),
        );

        self::assertSame($source->id->value, $asset->sourceId?->value);
        Storage::disk('local')->assertExists($asset->storage->path);
    }
}
