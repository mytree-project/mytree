<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Acquisition\BrowseSources;
use App\Application\Acquisition\CreateSource;
use App\Domain\Acquisition\SourceId;
use App\Domain\Acquisition\SourceType;
use App\Filament\Pages\Acquisition\SourceEditor;
use App\Filament\Pages\Acquisition\Sources;
use App\Filament\Support\SourceTypePresentationCatalog;
use App\Infrastructure\Persistence\Eloquent\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

final class SourceTypePresentationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_catalog_localizes_all_supported_source_types_and_has_deterministic_fallback(): void
    {
        $catalog = app(SourceTypePresentationCatalog::class);

        app()->setLocale('en');
        self::assertSame([
            'generic' => 'Generic source · v1',
            'civil.birth' => 'Birth record · v1',
            'civil.marriage' => 'Marriage record · v1',
            'civil.death' => 'Death record · v1',
            'oral_testimony' => 'Oral testimony · v1',
            'family_tradition' => 'Family tradition · v1',
        ], $catalog->options());
        self::assertSame('Custom source type (archive.custom)', $catalog->label('archive.custom'));

        app()->setLocale('pl');
        self::assertSame('Akt urodzenia', $catalog->label('civil.birth'));
        self::assertSame('Akt zgonu', $catalog->label('civil.death'));
        self::assertSame('Niestandardowy typ źródła (archive.custom)', $catalog->label('archive.custom'));
    }

    public function test_unknown_existing_source_type_remains_selectable_without_losing_identity(): void
    {
        app()->setLocale('en');
        $catalog = app(SourceTypePresentationCatalog::class);
        $current = new SourceType('archive.custom', 3);

        $options = $catalog->options($current);

        self::assertSame('Custom source type (archive.custom) · v3', $options['archive.custom']);
        self::assertSame('archive.custom@3', $catalog->diagnostic($current));
        self::assertNull($catalog->schemaVersion('archive.custom'));
    }

    public function test_source_list_uses_localized_label_and_keeps_key_as_secondary_diagnostic(): void
    {
        app()->setLocale('en');
        $this->actingAs(User::factory()->admin()->create());
        app(CreateSource::class)->handle(new SourceType('civil.birth'));

        Livewire::test(Sources::class)
            ->assertSee('Birth record · v1')
            ->assertSee('civil.birth@1');
    }

    public function test_source_editor_derives_schema_version_and_persists_canonical_key(): void
    {
        app()->setLocale('en');
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(SourceEditor::class)
            ->set('data.source_type_schema_version', 99)
            ->set('data.source_type_key', 'civil.death')
            ->call('sourceTypeChanged')
            ->assertSet('data.source_type_schema_version', 1)
            ->assertSet('sourceTypeContext', 'Death record · v1')
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertRedirect();

        $record = DB::table('sources')->first();
        self::assertNotNull($record);
        $source = app(BrowseSources::class)->find(new SourceId((string) $record->id));
        self::assertNotNull($source);
        self::assertSame('civil.death', $source->type->key);
        self::assertSame(1, $source->type->schemaVersion);
    }
}
