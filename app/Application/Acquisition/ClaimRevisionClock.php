<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use DateTimeImmutable;

interface ClaimRevisionClock
{
    public function now(): DateTimeImmutable;
}
