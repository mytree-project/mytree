<?php

declare(strict_types=1);

namespace App\Infrastructure\Acquisition;

use App\Application\Acquisition\MentionRevisionClock;
use DateTimeImmutable;
use DateTimeZone;

final class SystemMentionRevisionClock implements MentionRevisionClock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
