<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Application\Acquisition\SupportedAcquisitionFieldCatalog;
use App\Application\Acquisition\SupportedAcquisitionFieldDescriptor;
use App\Application\Acquisition\SupportedAcquisitionFieldEditorKind;
use App\Application\Acquisition\SupportedAcquisitionFieldMappingKind;
use App\Domain\Acquisition\ClaimValueType;
use App\Domain\Acquisition\MentionKind;
use App\Domain\Acquisition\PredicateKey;
use App\Domain\Acquisition\PredicateVocabulary;
use App\Domain\Acquisition\SexClaimValueKey;
use PHPUnit\Framework\TestCase;

final class SupportedAcquisitionFieldCatalogTest extends TestCase
{
    public function test_every_controlled_predicate_has_a_stable_direct_field_descriptor(): void
    {
        $catalog = new SupportedAcquisitionFieldCatalog;

        foreach (PredicateVocabulary::all() as $predicate) {
            $descriptor = $catalog->get($predicate->key->value);

            self::assertSame($predicate->key->value, $descriptor->key);
            self::assertSame(SupportedAcquisitionFieldMappingKind::DirectClaim, $descriptor->mappingKind);
            self::assertSame($predicate->key, $descriptor->predicateKey);
            self::assertSame($predicate->subjectMentionKind, $descriptor->subjectMentionKind);
            self::assertSame($predicate->literalValueType, $descriptor->literalValueType);
            self::assertSame($predicate->objectMentionKind, $descriptor->objectMentionKind);
            self::assertTrue($descriptor->repeatable);
        }
    }

    public function test_catalog_exposes_typed_editors_without_collapsing_person_descriptor_semantics(): void
    {
        $catalog = new SupportedAcquisitionFieldCatalog;

        self::assertSame(
            SupportedAcquisitionFieldEditorKind::Age,
            $catalog->get(PredicateKey::PersonAge->value)->editorKind,
        );
        self::assertSame(
            ClaimValueType::Date,
            $catalog->get(PredicateKey::PersonBirthDate->value)->literalValueType,
        );

        foreach ([
            PredicateKey::PersonOccupation,
            PredicateKey::PersonSocialStatus,
            PredicateKey::PersonSocialEstate,
            PredicateKey::PersonOffice,
            PredicateKey::PersonRank,
            PredicateKey::PersonTitle,
            PredicateKey::PersonAcademicDegree,
        ] as $key) {
            $descriptor = $catalog->get($key->value);
            self::assertSame(SupportedAcquisitionFieldEditorKind::Text, $descriptor->editorKind);
            self::assertSame(MentionKind::PERSON, $descriptor->subjectMentionKind);
        }
    }

    public function test_event_context_key_is_a_presentation_only_event_mention_preset(): void
    {
        $descriptor = (new SupportedAcquisitionFieldCatalog)->get(SupportedAcquisitionFieldCatalog::EVENT_MENTION_PRESET_KEY);

        self::assertSame(SupportedAcquisitionFieldMappingKind::MentionPreset, $descriptor->mappingKind);
        self::assertSame(SupportedAcquisitionFieldEditorKind::MentionPreset, $descriptor->editorKind);
        self::assertSame(MentionKind::EVENT, $descriptor->subjectMentionKind);
        self::assertContains(PredicateKey::EventDate, $descriptor->contextPredicateKeys);
        self::assertContains(PredicateKey::EventPlace, $descriptor->contextPredicateKeys);
        self::assertContains(PredicateKey::EventWitness, $descriptor->contextPredicateKeys);
        self::assertContains(PredicateKey::EventDeclarant, $descriptor->contextPredicateKeys);
    }

    public function test_catalog_order_is_deterministic_and_keys_are_unique(): void
    {
        $keys = array_map(
            static fn (SupportedAcquisitionFieldDescriptor $descriptor): string => $descriptor->key,
            (new SupportedAcquisitionFieldCatalog)->all(),
        );
        $sorted = $keys;
        sort($sorted, SORT_STRING);

        self::assertSame($sorted, $keys);
        self::assertSame($keys, array_values(array_unique($keys)));
    }

    public function test_source_recorded_sex_and_religious_affiliation_have_distinct_supported_field_contracts(): void
    {
        $catalog = new SupportedAcquisitionFieldCatalog;

        $sex = $catalog->get(PredicateKey::PersonSex->value);
        self::assertSame(SupportedAcquisitionFieldEditorKind::Enum, $sex->editorKind);
        self::assertSame(ClaimValueType::Enum, $sex->literalValueType);
        self::assertSame(SexClaimValueKey::values(), $sex->allowedEnumKeys);
        self::assertSame(MentionKind::PERSON, $sex->subjectMentionKind);

        $religion = $catalog->get(PredicateKey::PersonReligiousAffiliation->value);
        self::assertSame(SupportedAcquisitionFieldEditorKind::Text, $religion->editorKind);
        self::assertSame(ClaimValueType::Text, $religion->literalValueType);
        self::assertSame([], $religion->allowedEnumKeys);
        self::assertSame(MentionKind::PERSON, $religion->subjectMentionKind);
    }
}
