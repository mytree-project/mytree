<?php

declare(strict_types=1);

namespace App\Domain\Acquisition;

enum AgeUnit: string
{
    case Years = 'years';
    case Months = 'months';
    case Days = 'days';
}
