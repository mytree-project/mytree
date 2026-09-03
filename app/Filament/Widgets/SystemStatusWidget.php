<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Support\SystemStatus;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class SystemStatusWidget extends StatsOverviewWidget
{
    protected function getHeading(): ?string
    {
        return 'System status';
    }

    protected function getDescription(): ?string
    {
        return 'Non-sensitive runtime diagnostics for the MyTree application.';
    }

    /** @return list<Stat> */
    protected function getStats(): array
    {
        $status = app(SystemStatus::class);
        $databaseStatus = $status->databaseStatus();
        $redisStatus = $status->redisStatus();

        return [
            Stat::make('Environment', $status->environment()),
            Stat::make('Application URL', $status->applicationUrl()),
            Stat::make('PHP', $status->phpVersion()),
            Stat::make('Laravel', $status->laravelVersion()),
            Stat::make('Filament', $status->filamentVersion()),
            Stat::make('Database', $databaseStatus)
                ->description($status->databaseDriver())
                ->color($databaseStatus === 'Connected' ? 'success' : 'danger'),
            Stat::make('Redis', $redisStatus)
                ->color($redisStatus === 'Connected' ? 'success' : 'danger'),
            Stat::make('Session backend', $status->sessionDriver()),
            Stat::make('Queue connection', $status->queueConnection()),
        ];
    }
}
