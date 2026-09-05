<?php

declare(strict_types=1);

namespace App\Domain\Acquisition;

enum AgeExpressionKind: string
{
    case Exact = 'exact';
    case Approximate = 'approximate';
    case Range = 'range';
    case Uncertain = 'uncertain';
}
