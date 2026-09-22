<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Acquisition;

use App\Domain\Acquisition\SourceLinguisticRepresentation;
use App\Domain\Acquisition\SourceLinguisticRepresentationRelation;
use App\Domain\Acquisition\SourceLinguisticRepresentationSerializer;
use App\Domain\Acquisition\SourceLocatorId;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class SourceLinguisticRepresentationTest extends TestCase
{
    public function test_serialized_set_is_deterministic_and_round_trips_optional_provenance(): void
    {
        $firstLocator = new SourceLocatorId('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa');
        $secondLocator = new SourceLocatorId('bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb');

        $translation = new SourceLinguisticRepresentation(
            value: 'Piotr',
            language: 'pl',
            script: 'Latn',
            relation: SourceLinguisticRepresentationRelation::LanguageEquivalent,
            sourceLocatorIds: [$secondLocator, $firstLocator],
        );
        $transliteration = new SourceLinguisticRepresentation(
            value: 'Petr',
            language: null,
            script: 'Latn',
            relation: SourceLinguisticRepresentationRelation::Transliteration,
        );

        $left = SourceLinguisticRepresentationSerializer::serializeList([$translation, $transliteration]);
        $right = SourceLinguisticRepresentationSerializer::serializeList([$transliteration, $translation]);

        self::assertSame($left, $right);

        $roundTrip = SourceLinguisticRepresentationSerializer::deserializeList($left);
        self::assertCount(2, $roundTrip);

        $piotr = array_values(array_filter(
            $roundTrip,
            static fn (SourceLinguisticRepresentation $representation): bool => $representation->value === 'Piotr',
        ));
        self::assertCount(1, $piotr);
        self::assertSame('pl', $piotr[0]->language);
        self::assertSame('Latn', $piotr[0]->script);
        self::assertSame(
            [$firstLocator->value, $secondLocator->value],
            array_map(
                static fn (SourceLocatorId $id): string => $id->value,
                $piotr[0]->sourceLocatorIds,
            ),
        );
    }

    public function test_exact_duplicate_representation_is_rejected(): void
    {
        $representation = new SourceLinguisticRepresentation(
            value: 'Piotr',
            language: 'pl',
            script: 'Latn',
            relation: SourceLinguisticRepresentationRelation::LanguageEquivalent,
        );

        $this->expectException(InvalidArgumentException::class);

        SourceLinguisticRepresentationSerializer::normalize([$representation, $representation]);
    }
}
