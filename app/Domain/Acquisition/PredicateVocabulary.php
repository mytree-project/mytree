<?php

declare(strict_types=1);

namespace App\Domain\Acquisition;

use InvalidArgumentException;

final class PredicateVocabulary
{
    public const CURRENT_SCHEMA_VERSION = 1;

    public static function get(
        PredicateKey|string $key,
        int $schemaVersion = self::CURRENT_SCHEMA_VERSION,
    ): Predicate {
        $predicateKey = is_string($key) ? PredicateKey::tryFrom($key) : $key;

        if ($predicateKey === null) {
            $unknownKey = is_string($key) ? $key : $key->value;

            throw new InvalidArgumentException(sprintf('Unknown Predicate key "%s".', $unknownKey));
        }

        if ($schemaVersion !== self::CURRENT_SCHEMA_VERSION) {
            throw new InvalidArgumentException(sprintf(
                'Unsupported Predicate schema version %d for "%s".',
                $schemaVersion,
                $predicateKey->value,
            ));
        }

        return self::definition($predicateKey, $schemaVersion);
    }

    /** @return list<Predicate> */
    public static function all(): array
    {
        return array_map(
            static fn (PredicateKey $key): Predicate => self::definition($key, self::CURRENT_SCHEMA_VERSION),
            PredicateKey::cases(),
        );
    }

    private static function definition(PredicateKey $key, int $schemaVersion): Predicate
    {
        return match ($key) {
            PredicateKey::PersonGivenName,
            PredicateKey::PersonSurname,
            PredicateKey::PersonAddress,
            PredicateKey::PersonOccupation,
            PredicateKey::PersonSocialStatus,
            PredicateKey::PersonSocialEstate,
            PredicateKey::PersonOffice,
            PredicateKey::PersonRank,
            PredicateKey::PersonTitle,
            PredicateKey::PersonAcademicDegree => self::literal(
                $key,
                $schemaVersion,
                MentionKind::PERSON,
                ClaimValueType::Text,
            ),

            PredicateKey::PersonAge => self::literal(
                $key,
                $schemaVersion,
                MentionKind::PERSON,
                ClaimValueType::Age,
            ),

            PredicateKey::PersonBirthDate,
            PredicateKey::PersonDeathDate => self::literal(
                $key,
                $schemaVersion,
                MentionKind::PERSON,
                ClaimValueType::Date,
            ),

            PredicateKey::PersonParent,
            PredicateKey::PersonSpouse => self::object(
                $key,
                $schemaVersion,
                MentionKind::PERSON,
                MentionKind::PERSON,
            ),

            PredicateKey::PersonBirthPlace,
            PredicateKey::PersonResidence,
            PredicateKey::PersonPermanentResidence,
            PredicateKey::PersonTemporaryStay,
            PredicateKey::PersonPresence,
            PredicateKey::PersonOrigin,
            PredicateKey::PersonWorkPlace,
            PredicateKey::PersonStudyPlace,
            PredicateKey::PersonDetentionPlace,
            PredicateKey::PersonExilePlace,
            PredicateKey::PersonDeportationDestination => self::object(
                $key,
                $schemaVersion,
                MentionKind::PERSON,
                MentionKind::PLACE,
            ),

            PredicateKey::EventDate => self::literal(
                $key,
                $schemaVersion,
                MentionKind::EVENT,
                ClaimValueType::Date,
            ),

            PredicateKey::EventPlace,
            PredicateKey::EventOriginPlace,
            PredicateKey::EventDestinationPlace => self::object(
                $key,
                $schemaVersion,
                MentionKind::EVENT,
                MentionKind::PLACE,
            ),

            PredicateKey::EventParticipant => self::object(
                $key,
                $schemaVersion,
                MentionKind::EVENT,
                MentionKind::PERSON,
            ),

            PredicateKey::EventReason => self::literal(
                $key,
                $schemaVersion,
                MentionKind::EVENT,
                ClaimValueType::Text,
            ),

            PredicateKey::PlaceName => self::literal(
                $key,
                $schemaVersion,
                MentionKind::PLACE,
                ClaimValueType::Text,
            ),
        };
    }

    private static function literal(
        PredicateKey $key,
        int $schemaVersion,
        string $subjectMentionKind,
        ClaimValueType $valueType,
    ): Predicate {
        return new Predicate(
            key: $key,
            schemaVersion: $schemaVersion,
            subjectMentionKind: $subjectMentionKind,
            literalValueType: $valueType,
        );
    }

    private static function object(
        PredicateKey $key,
        int $schemaVersion,
        string $subjectMentionKind,
        string $objectMentionKind,
    ): Predicate {
        return new Predicate(
            key: $key,
            schemaVersion: $schemaVersion,
            subjectMentionKind: $subjectMentionKind,
            objectMentionKind: $objectMentionKind,
        );
    }
}
