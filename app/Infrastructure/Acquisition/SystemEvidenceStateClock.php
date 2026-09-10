<?php

declare(strict_types=1);

namespace App\Infrastructure\Acquisition;

use App\Application\Acquisition\EvidenceStateClock;
use DateTimeImmutable;
use DateTimeZone;

final class SystemEvidenceStateClock implements EvidenceStateClock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
