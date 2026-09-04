<?php

declare(strict_types=1);

namespace App\Infrastructure\Acquisition;

use App\Application\Acquisition\SourceRevisionClock;
use DateTimeImmutable;
use DateTimeZone;

final class SystemSourceRevisionClock implements SourceRevisionClock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
