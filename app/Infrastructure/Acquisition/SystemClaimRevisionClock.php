<?php

declare(strict_types=1);

namespace App\Infrastructure\Acquisition;

use App\Application\Acquisition\ClaimRevisionClock;
use DateTimeImmutable;
use DateTimeZone;

final class SystemClaimRevisionClock implements ClaimRevisionClock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
