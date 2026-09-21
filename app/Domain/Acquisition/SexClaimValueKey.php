<?php

declare(strict_types=1);

namespace App\Domain\Acquisition;

enum SexClaimValueKey: string
{
    case Male = 'sex.male';
    case Female = 'sex.female';
    case Unknown = 'sex.unknown';
    case Unmapped = 'sex.unmapped';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(
            static fn (self $key): string => $key->value,
            self::cases(),
        );
    }
}
