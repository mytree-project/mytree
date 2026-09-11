<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Acquisition;

use App\Domain\Acquisition\MentionKind;
use App\Domain\Acquisition\PredicateKey;
use App\Domain\Acquisition\PredicateVocabulary;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class EventRolePredicateVocabularyTest extends TestCase
{
    public function test_controlled_event_roles_are_versioned_event_to_person_edges(): void
    {
        $roles = [
            [PredicateKey::EventChild, 'event.child'],
            [PredicateKey::EventParent, 'event.parent'],
            [PredicateKey::EventSpouse, 'event.spouse'],
            [PredicateKey::EventWitness, 'event.witness'],
            [PredicateKey::EventDeclarant, 'event.declarant'],
            [PredicateKey::EventOfficiant, 'event.officiant'],
        ];

        foreach ($roles as [$key, $expectedValue]) {
            $predicate = PredicateVocabulary::get($key);

            self::assertSame($expectedValue, $predicate->key->value);
            self::assertSame(PredicateVocabulary::INITIAL_SCHEMA_VERSION, $predicate->schemaVersion);
            self::assertSame(MentionKind::EVENT, $predicate->subjectMentionKind);
            self::assertNull($predicate->literalValueType);
            self::assertSame(MentionKind::PERSON, $predicate->objectMentionKind);
            self::assertSame([
                'key' => $expectedValue,
                'schema_version' => PredicateVocabulary::INITIAL_SCHEMA_VERSION,
            ], $predicate->identity());

            $predicate->assertSubjectKind(MentionKind::event());
            $predicate->assertObjectKind(MentionKind::person());
        }
    }

    public function test_specialized_event_role_rejects_non_person_object(): void
    {
        $witness = PredicateVocabulary::get(PredicateKey::EventWitness);

        $this->expectException(InvalidArgumentException::class);

        $witness->assertObjectKind(MentionKind::place());
    }

    public function test_generic_event_participant_remains_available_as_distinct_fallback(): void
    {
        $participant = PredicateVocabulary::get(PredicateKey::EventParticipant);
        $witness = PredicateVocabulary::get(PredicateKey::EventWitness);

        self::assertSame('event.participant', $participant->key->value);
        self::assertNotSame($participant->key->value, $witness->key->value);
        self::assertSame(MentionKind::EVENT, $participant->subjectMentionKind);
        self::assertSame(MentionKind::PERSON, $participant->objectMentionKind);
    }
}
