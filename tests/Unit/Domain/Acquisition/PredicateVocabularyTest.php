<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Acquisition;

use App\Domain\Acquisition\AgeClaimValue;
use App\Domain\Acquisition\ClaimValueType;
use App\Domain\Acquisition\Mention;
use App\Domain\Acquisition\MentionId;
use App\Domain\Acquisition\MentionKind;
use App\Domain\Acquisition\MentionRawData;
use App\Domain\Acquisition\Predicate;
use App\Domain\Acquisition\PredicateKey;
use App\Domain\Acquisition\PredicateVocabulary;
use App\Domain\Acquisition\SourceId;
use App\Domain\Acquisition\TextClaimValue;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PredicateVocabularyTest extends TestCase
{
    public function test_controlled_vocabulary_contains_distinct_person_descriptor_predicates(): void
    {
        $keys = array_map(
            static fn (Predicate $predicate): string => $predicate->key->value,
            PredicateVocabulary::all(),
        );

        self::assertContains('person.occupation', $keys);
        self::assertContains('person.social_status', $keys);
        self::assertContains('person.social_estate', $keys);
        self::assertContains('person.office', $keys);
        self::assertContains('person.rank', $keys);
        self::assertContains('person.title', $keys);
        self::assertContains('person.academic_degree', $keys);
        self::assertSame($keys, array_values(array_unique($keys)));
    }

    public function test_location_and_event_predicates_keep_distinct_semantics(): void
    {
        $residence = PredicateVocabulary::get(PredicateKey::PersonResidence);
        $presence = PredicateVocabulary::get(PredicateKey::PersonPresence);
        $eventPlace = PredicateVocabulary::get(PredicateKey::EventPlace);

        self::assertNotSame($residence->key, $presence->key);
        self::assertNotSame($presence->key, $eventPlace->key);
        self::assertSame(MentionKind::PERSON, $residence->subjectMentionKind);
        self::assertSame(MentionKind::EVENT, $eventPlace->subjectMentionKind);
        self::assertSame(MentionKind::PLACE, $residence->objectMentionKind);
        self::assertSame(MentionKind::PLACE, $eventPlace->objectMentionKind);
    }

    public function test_predicate_definitions_validate_subject_and_value_forms(): void
    {
        $occupation = PredicateVocabulary::get(PredicateKey::PersonOccupation);

        $occupation->assertSubjectKind(MentionKind::person());
        $occupation->assertLiteralValue(new TextClaimValue('rolnik'));

        self::assertSame(ClaimValueType::Text, $occupation->literalValueType);

        $this->expectException(InvalidArgumentException::class);

        $occupation->assertLiteralValue(AgeClaimValue::exact('42 lata', 42));
    }

    public function test_relationship_predicate_rejects_wrong_object_kind(): void
    {
        $parent = PredicateVocabulary::get(PredicateKey::PersonParent);

        $parent->assertObjectKind(MentionKind::person());

        $this->expectException(InvalidArgumentException::class);

        $parent->assertObjectKind(MentionKind::place());
    }

    public function test_unclassified_person_descriptor_can_remain_in_mention_raw_data_while_later_value_is_classified(): void
    {
        $mention = new Mention(
            id: new MentionId('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa'),
            sourceId: new SourceId('11111111-1111-4111-8111-111111111111'),
            kind: MentionKind::person(),
            localKey: 'person.subject',
            displayLabel: 'Jan Kowalski',
            rawData: new MentionRawData([
                'descriptor' => 'однодворец ze wsi XYZ',
            ]),
        );

        $socialEstate = PredicateVocabulary::get(PredicateKey::PersonSocialEstate);
        $socialEstate->assertLiteralValue(new TextClaimValue('однодворец'));

        self::assertSame(
            'однодворец ze wsi XYZ',
            $mention->rawData->toArray()['descriptor'],
        );
    }

    public function test_unknown_or_unsupported_predicate_identity_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        PredicateVocabulary::get('person.uncontrolled_value');
    }

    public function test_unsupported_predicate_version_is_rejected_explicitly(): void
    {
        $this->expectException(InvalidArgumentException::class);

        PredicateVocabulary::get(PredicateKey::PersonOccupation, 2);
    }

    public function test_predicate_identity_is_stable_and_versioned(): void
    {
        $predicate = PredicateVocabulary::get(PredicateKey::PersonAcademicDegree);

        self::assertSame([
            'key' => 'person.academic_degree',
            'schema_version' => 1,
        ], $predicate->identity());
    }
}
