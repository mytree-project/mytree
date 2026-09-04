<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use DateTimeImmutable;

interface SourceRevisionClock
{
    public function now(): DateTimeImmutable;
}
