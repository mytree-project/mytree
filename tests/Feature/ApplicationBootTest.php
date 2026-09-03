<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class ApplicationBootTest extends TestCase
{
    public function test_root_redirects_to_admin_panel(): void
    {
        $this->get('/')->assertRedirect('/admin');
    }

    public function test_guest_is_redirected_to_filament_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_filament_login_page_renders(): void
    {
        $this->get('/admin/login')->assertOk();
    }
}
