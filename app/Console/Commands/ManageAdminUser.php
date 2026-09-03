<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Infrastructure\Persistence\Eloquent\Models\User;
use Illuminate\Console\Command;

final class ManageAdminUser extends Command
{
    protected $signature = 'mytree:admin
        {action : Action to perform: create or reset}
        {email? : Administrator email address}
        {--name= : Administrator display name when creating an account}';

    protected $description = 'Create an administrator or reset an existing administrator password';

    public function handle(): int
    {
        $action = strtolower((string) $this->argument('action'));

        if (! in_array($action, ['create', 'reset'], true)) {
            $this->error('Action must be either "create" or "reset".');

            return self::INVALID;
        }

        $email = $this->readEmail();

        if ($email === null) {
            return self::INVALID;
        }

        $password = $this->readPassword();

        if ($password === null) {
            return self::INVALID;
        }

        return $action === 'create'
            ? $this->createAdministrator($email, $password)
            : $this->resetAdministrator($email, $password);
    }

    private function readEmail(): ?string
    {
        $email = trim((string) ($this->argument('email') ?? ''));

        if ($email === '') {
            $email = trim((string) $this->ask('Administrator email address'));
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->error('A valid administrator email address is required.');

            return null;
        }

        return strtolower($email);
    }

    private function readPassword(): ?string
    {
        $password = (string) $this->secret('Password (minimum 12 characters)');

        if (strlen($password) < 12) {
            $this->error('The administrator password must contain at least 12 characters.');

            return null;
        }

        $confirmation = (string) $this->secret('Confirm password');

        if (! hash_equals($password, $confirmation)) {
            $this->error('Password confirmation does not match.');

            return null;
        }

        return $password;
    }

    private function createAdministrator(string $email, string $password): int
    {
        if (User::query()->where('email', $email)->exists()) {
            $this->error('A user with this email address already exists.');

            return self::FAILURE;
        }

        $name = trim((string) ($this->option('name') ?? ''));

        if ($name === '') {
            $name = trim((string) $this->ask('Administrator display name'));
        }

        if ($name === '') {
            $this->error('Administrator display name is required.');

            return self::INVALID;
        }

        $administrator = new User;
        $administrator->name = $name;
        $administrator->email = $email;
        $administrator->password = $password;
        $administrator->is_admin = true;
        $administrator->save();

        $this->info(sprintf('Administrator %s created.', $email));

        return self::SUCCESS;
    }

    private function resetAdministrator(string $email, string $password): int
    {
        $administrator = User::query()->where('email', $email)->first();

        if (! $administrator instanceof User || ! $administrator->is_admin) {
            $this->error('No administrator exists with this email address.');

            return self::FAILURE;
        }

        $administrator->password = $password;
        $administrator->save();

        $this->info(sprintf('Administrator password reset for %s.', $email));

        return self::SUCCESS;
    }
}
