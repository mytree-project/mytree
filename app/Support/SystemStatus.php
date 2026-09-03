<?php

declare(strict_types=1);

namespace App\Support;

use Composer\InstalledVersions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

final class SystemStatus
{
    public function environment(): string
    {
        return app()->environment();
    }

    public function applicationUrl(): string
    {
        $url = (string) config('app.url', '');
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['host'])) {
            return 'Configured';
        }

        $scheme = isset($parts['scheme']) ? $parts['scheme'].'://' : '';
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return $scheme.$parts['host'].$port;
    }

    public function laravelVersion(): string
    {
        return app()->version();
    }

    public function filamentVersion(): string
    {
        return InstalledVersions::getPrettyVersion('filament/filament') ?? 'Unknown';
    }

    public function phpVersion(): string
    {
        return PHP_VERSION;
    }

    public function databaseDriver(): string
    {
        return (string) config('database.default', 'unknown');
    }

    public function databaseStatus(): string
    {
        try {
            DB::connection()->getPdo();

            return 'Connected';
        } catch (Throwable) {
            return 'Unavailable';
        }
    }

    public function redisStatus(): string
    {
        try {
            Redis::connection()->command('ping');

            return 'Connected';
        } catch (Throwable) {
            return 'Unavailable';
        }
    }

    public function queueConnection(): string
    {
        return (string) config('queue.default', 'unknown');
    }

    public function sessionDriver(): string
    {
        return (string) config('session.driver', 'unknown');
    }
}
