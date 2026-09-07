<?php

declare(strict_types=1);

namespace App\Domain\Acquisition;

enum DatePrecision: string
{
    case Year = 'year';
    case Month = 'month';
    case Day = 'day';
}
