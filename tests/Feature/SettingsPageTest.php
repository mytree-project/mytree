<?php

declare(strict_types=1);

namespace Tests\Feature;

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
            ->assertSee('Default locale');
    }

    public function test_regular_user_cannot_open_settings_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/admin/settings')
            ->assertForbidden();
    }
}
