<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Acquisition;

use App\Application\Acquisition\SupportedAcquisitionFieldCatalog;
use App\Application\Acquisition\SupportedAcquisitionFieldEditorKind;
use App\Domain\Acquisition\ClaimValueType;
use App\Domain\Acquisition\EnumClaimValue;
use App\Domain\Acquisition\EventTypeClaimValueKey;
use App\Domain\Acquisition\MentionKind;
use App\Domain\Acquisition\PredicateKey;
use App\Domain\Acquisition\PredicateVocabulary;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class EventTypePredicateVocabularyTest extends TestCase
{
    public function test_event_type_is_a_controlled_direct_enum_claim_on_event_mentions(): void
    {
        $predicate = PredicateVocabulary::get(PredicateKey::EventType);
        self::assertSame('event.type', $predicate->key->value);
        self::assertSame(MentionKind::EVENT, $predicate->subjectMentionKind);
        self::assertSame(ClaimValueType::Enum, $predicate->literalValueType);
        self::assertSame(EventTypeClaimValueKey::values(), $predicate->allowedEnumKeys);
        self::assertSame(['key' => 'event.type', 'schema_version' => 1], $predicate->identity());

        $predicate->assertSubjectKind(MentionKind::event());
        $predicate->assertLiteralValue(new EnumClaimValue('urodzenie', EventTypeClaimValueKey::Birth->value));
        $predicate->assertLiteralValue(new EnumClaimValue('chrzest', EventTypeClaimValueKey::Baptism->value));

        $catalog = new SupportedAcquisitionFieldCatalog;
        self::assertSame(SupportedAcquisitionFieldEditorKind::Enum, $catalog->get('event.type')->editorKind);
        self::assertContains(PredicateKey::EventType, $catalog->get('event.context')->contextPredicateKeys);
    }

    public function test_uncontrolled_event_type_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        PredicateVocabulary::get(PredicateKey::EventType)
            ->assertLiteralValue(new EnumClaimValue('celebration', 'generic_celebration'));
    }

    public function test_event_type_is_not_inferred_from_source_type_or_untyped_event_role(): void
    {
        self::assertNotSame(EventTypeClaimValueKey::Birth->value, EventTypeClaimValueKey::Baptism->value);
        self::assertNotContains('civil.birth', EventTypeClaimValueKey::values());
    }
}
