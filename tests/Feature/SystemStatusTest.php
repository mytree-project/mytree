<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\SystemStatus;
use Tests\TestCase;

final class SystemStatusTest extends TestCase
{
    public function test_application_url_diagnostic_does_not_expose_credentials_or_query_data(): void
    {
        config([
            'app.url' => 'https://user:password@example.test:8443/admin?token=secret#fragment',
        ]);

        self::assertSame(
            'https://example.test:8443',
            app(SystemStatus::class)->applicationUrl(),
        );
    }
}
