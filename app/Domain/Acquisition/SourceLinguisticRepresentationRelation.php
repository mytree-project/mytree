<?php

declare(strict_types=1);

namespace App\Domain\Acquisition;

enum SourceLinguisticRepresentationRelation: string
{
    case Translation = 'translation';
    case Transliteration = 'transliteration';
    case LanguageEquivalent = 'language_equivalent';
    case HistoricalOrthographicVariant = 'historical_orthographic_variant';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(
            static fn (self $relation): string => $relation->value,
            self::cases(),
        );
    }
}
