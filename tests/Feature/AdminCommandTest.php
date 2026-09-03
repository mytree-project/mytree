<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Infrastructure\Persistence\Eloquent\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\PendingCommand;
use Tests\TestCase;

final class AdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_be_created_interactively(): void
    {
        $password = 'a-secure-admin-password';
        $command = $this->artisan('mytree:admin create admin@example.test --name="MyTree Administrator"');

        self::assertInstanceOf(PendingCommand::class, $command);

        $command
            ->expectsQuestion('Password (minimum 12 characters)', $password)
            ->expectsQuestion('Confirm password', $password)
            ->assertSuccessful()
            ->execute();

        $administrator = User::query()->where('email', 'admin@example.test')->firstOrFail();

        self::assertTrue($administrator->is_admin);
        self::assertTrue(Hash::check($password, $administrator->password));
    }

    public function test_existing_administrator_password_can_be_reset(): void
    {
        $administrator = User::factory()->admin()->create([
            'email' => 'admin@example.test',
        ]);
        $password = 'a-different-secure-password';
        $command = $this->artisan('mytree:admin reset admin@example.test');

        self::assertInstanceOf(PendingCommand::class, $command);

        $command
            ->expectsQuestion('Password (minimum 12 characters)', $password)
            ->expectsQuestion('Confirm password', $password)
            ->assertSuccessful()
            ->execute();

        $administrator->refresh();

        self::assertTrue(Hash::check($password, $administrator->password));
    }
}
