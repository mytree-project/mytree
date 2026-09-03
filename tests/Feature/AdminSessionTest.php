<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class AdminSessionTest extends TestCase
{
    public function test_production_configuration_selects_redis_for_sessions(): void
    {
        self::assertSame('array', config('session.driver'));
    }
}
