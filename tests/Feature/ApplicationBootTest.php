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

    public function test_filament_admin_panel_is_registered(): void
    {
        $this->get('/admin')->assertOk();
    }
}
