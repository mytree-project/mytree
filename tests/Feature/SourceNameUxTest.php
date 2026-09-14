<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Acquisition\BrowseSources;
use App\Application\Acquisition\CreateSource;
use App\Application\Acquisition\LoadSourceDraft;
use App\Domain\Acquisition\SourceId;
use App\Domain\Acquisition\SourceMetadata;
use App\Domain\Acquisition\SourceType;
use App\Filament\Pages\Acquisition\SourceEditor;
use App\Filament\Pages\Acquisition\Sources;
use App\Infrastructure\Persistence\Eloquent\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

final class SourceNameUxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_source_name_can_be_created_and_edited_without_changing_source_identity(): void
    {
        Livewire::test(SourceEditor::class)
            ->fillForm([
                'name' => 'Birth certificate Jan Kowalski 1880',
                'source_type_key' => 'generic',
                'source_type_schema_version' => 1,
                'metadata' => [],
                'texts' => [],
                'detach_asset_ids' => [],
                'uploads' => [],
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertRedirect();

        $record = DB::table('sources')->first();
        self::assertNotNull($record);
        self::assertSame('Birth certificate Jan Kowalski 1880', $record->name);

        $sourceId = new SourceId((string) $record->id);
        $created = app(LoadSourceDraft::class)->handle($sourceId);
        self::assertSame('Birth certificate Jan Kowalski 1880', $created->current->source->name);
        $this->assertDatabaseCount('source_revisions', 1);

        Livewire::test(SourceEditor::class, ['source' => $sourceId->value])
            ->fillForm(['name' => 'Birth certificate Jan Kowalski 1880 — copy A'])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertRedirect();

        $edited = app(LoadSourceDraft::class)->handle($sourceId);
        self::assertSame($sourceId->value, $edited->current->source->id->value);
        self::assertSame('Birth certificate Jan Kowalski 1880 — copy A', $edited->current->source->name);
        $this->assertDatabaseCount('source_revisions', 2);
    }

    public function test_source_list_searches_by_name_and_renders_name_with_full_copyable_identifier(): void
    {
        $wanted = app(CreateSource::class)->handle(
            type: new SourceType('civil.birth'),
            metadata: new SourceMetadata(['archive_reference' => 'Fond 12']),
            name: 'Birth certificate Jan Kowalski 1880',
        );
        app(CreateSource::class)->handle(
            type: new SourceType('civil.marriage'),
            metadata: new SourceMetadata(['archive_reference' => 'Fond 99']),
            name: 'Marriage certificate Anna Nowak 1901',
        );

        $results = app(BrowseSources::class)->search('Jan Kowalski');

        self::assertCount(1, $results);
        self::assertSame($wanted->id->value, $results[0]->id->value);
        self::assertSame('Birth certificate Jan Kowalski 1880', $results[0]->name);

        Livewire::test(Sources::class)
            ->set('search', 'Jan Kowalski')
            ->assertSee('Birth certificate Jan Kowalski 1880')
            ->assertSee($wanted->id->value)
            ->assertDontSee('ID '.substr($wanted->id->value, 0, 8).'…')
            ->assertSee('Copy UUID')
            ->assertSee('Copied')
            ->assertSee('navigator.clipboard.writeText', false)
            ->assertSee('civil.birth@1')
            ->assertDontSee('Marriage certificate Anna Nowak 1901');
    }
}
