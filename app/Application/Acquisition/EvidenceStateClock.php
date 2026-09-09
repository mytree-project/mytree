<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use DateTimeImmutable;

interface EvidenceStateClock
{
    public function now(): DateTimeImmutable;
}
