<?php

declare(strict_types=1);

namespace App\Domain\Acquisition;

/**
 * Controlled semantic classification of a source-local event Mention.
 *
 * In particular, baptism is not a birth event for genealogical derivation.
 */
enum EventTypeClaimValueKey: string
{
    case Birth = 'birth';
    case Baptism = 'baptism';
    case Marriage = 'marriage';
    case Death = 'death';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(
            static fn (self $key): string => $key->value,
            self::cases(),
        );
    }
}
