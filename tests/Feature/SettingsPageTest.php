<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Acquisition\CreateSourceTypeTemplate;
use App\Application\Acquisition\SourceTypeTemplateDefinition;
use App\Application\Acquisition\SupportedAcquisitionFieldCatalog;
use App\Domain\Acquisition\PredicateKey;
use App\Domain\Acquisition\SourceType;
use App\Infrastructure\Persistence\Eloquent\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SettingsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_open_settings_page(): void
    {
        $administrator = User::factory()->admin()->create();

        $this->actingAs($administrator)
            ->get('/admin/settings')
            ->assertOk()
            ->assertSee('Default locale')
            ->assertSee('Source type templates')
            ->assertSee('never limit');
    }

    public function test_existing_template_configuration_is_rendered_in_settings(): void
    {
        $administrator = User::factory()->admin()->create();
        app(CreateSourceTypeTemplate::class)->handle(
            new SourceTypeTemplateDefinition(
                name: 'Civil birth record',
                compatibleSourceTypes: [new SourceType('civil.birth')],
                defaultFieldKeys: [
                    PredicateKey::PersonGivenName->value,
                    SupportedAcquisitionFieldCatalog::EVENT_CONTEXT_KEY,
                ],
            ),
        );

        $this->actingAs($administrator)
            ->get('/admin/settings')
            ->assertOk()
            ->assertSee('Civil birth record')
            ->assertSee('civil.birth');
    }

    public function test_regular_user_cannot_open_settings_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/admin/settings')
            ->assertForbidden();
    }
}
