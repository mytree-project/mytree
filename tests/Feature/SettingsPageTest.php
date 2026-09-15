<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Acquisition\CreateSourceTypeTemplate;
use App\Application\Acquisition\ListSourceTypeTemplates;
use App\Application\Acquisition\SourceTypeTemplateDefinition;
use App\Application\Acquisition\SupportedAcquisitionFieldCatalog;
use App\Domain\Acquisition\PredicateKey;
use App\Domain\Acquisition\SourceType;
use App\Filament\Pages\Settings;
use App\Infrastructure\Persistence\Eloquent\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

final class SettingsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('en');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_administrator_sees_categorized_settings_without_page_level_save(): void
    {
        $administrator = User::factory()->admin()->create();

        $this->actingAs($administrator)
            ->get('/admin/settings')
            ->assertOk()
            ->assertSee('General')
            ->assertSee('Acquisition')
            ->assertSee('Language')
            ->assertSee('Source type templates')
            ->assertSee('never limit')
            ->assertDontSee('wire:submit="save"', false);
    }

    public function test_language_cancel_is_local_and_does_not_persist(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(Settings::class)
            ->call('startEditingLanguage')
            ->assertSet('editingLanguage', true)
            ->set('language', 'pl')
            ->call('cancelEditingLanguage')
            ->assertSet('editingLanguage', false)
            ->assertSet('language', 'en');

        self::assertSame(0, DB::table('application_settings')->count());
    }

    public function test_language_save_persists_and_polish_ui_is_applied_on_next_request(): void
    {
        $administrator = User::factory()->admin()->create();
        $this->actingAs($administrator);

        Livewire::test(Settings::class)
            ->call('startEditingLanguage')
            ->set('language', 'pl')
            ->call('saveLanguage')
            ->assertHasNoErrors()
            ->assertSet('editingLanguage', false);

        $this->assertDatabaseHas('application_settings', [
            'section' => 'application',
            'key' => 'default_locale',
            'value' => 'pl',
        ]);

        $this->get('/admin/settings')
            ->assertOk()
            ->assertSee('Ogólne')
            ->assertSee('Akwizycja')
            ->assertSee('Język')
            ->assertSee('Szablony typów źródeł');
    }

    public function test_existing_template_is_listed_and_edited_in_local_drawer_without_losing_history(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $created = app(CreateSourceTypeTemplate::class)->handle(
            new SourceTypeTemplateDefinition(
                name: 'Civil birth record',
                compatibleSourceTypes: [new SourceType('civil.birth')],
                defaultFieldKeys: [
                    PredicateKey::PersonGivenName->value,
                    SupportedAcquisitionFieldCatalog::EVENT_CONTEXT_KEY,
                ],
            ),
        );

        Livewire::test(Settings::class)
            ->assertSee('Civil birth record')
            ->assertSee('Birth record · v1')
            ->call('editTemplate', $created->templateId->value)
            ->assertSet('templateEditorOpen', true)
            ->assertSet('templateEditor.name', 'Civil birth record')
            ->set('templateEditor.name', 'Civil birth record 1850+')
            ->call('saveTemplate')
            ->assertHasNoErrors()
            ->assertSet('templateEditorOpen', false)
            ->assertSee('Civil birth record 1850+');

        $templates = app(ListSourceTypeTemplates::class)->handle();
        self::assertCount(1, $templates);
        self::assertSame(2, $templates[0]->version);
        self::assertSame('Civil birth record 1850+', $templates[0]->definition->name);
    }

    public function test_create_template_uses_the_same_drawer_editor(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(Settings::class)
            ->call('createTemplate')
            ->assertSet('templateEditorOpen', true)
            ->assertSet('templateEditor.template_id', null)
            ->set('templateEditor.name', 'Blank research template')
            ->call('saveTemplate')
            ->assertHasNoErrors()
            ->assertSet('templateEditorOpen', false)
            ->assertSee('Blank research template');

        $templates = app(ListSourceTypeTemplates::class)->handle();
        self::assertCount(1, $templates);
        self::assertSame(1, $templates[0]->version);
    }

    public function test_regular_user_cannot_open_settings_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/admin/settings')
            ->assertForbidden();
    }
}
