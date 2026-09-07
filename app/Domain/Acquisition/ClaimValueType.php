<?php

declare(strict_types=1);

namespace App\Domain\Acquisition;

enum ClaimValueType: string
{
    case Text = 'text';
    case Integer = 'integer';
    case Date = 'date';
    case Age = 'age';
    case Boolean = 'boolean';
    case Enum = 'enum';
}
