<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Acquisition;

use App\Domain\Acquisition\Mention;
use App\Domain\Acquisition\MentionId;
use App\Domain\Acquisition\MentionKind;
use App\Domain\Acquisition\MentionRawData;
use App\Domain\Acquisition\SourceId;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use stdClass;

final class MentionDomainTest extends TestCase
{
    public function test_canonical_mention_kinds_are_explicit_and_custom_kinds_remain_possible(): void
    {
        self::assertSame('person', MentionKind::person()->key);
        self::assertSame('event', MentionKind::event()->key);
        self::assertSame('place', MentionKind::place()->key);
        self::assertSame('organization', MentionKind::organization()->key);
        self::assertSame('other', MentionKind::other()->key);
        self::assertTrue(MentionKind::person()->isCanonical());

        $custom = new MentionKind('archive_unit');

        self::assertSame('archive_unit', $custom->key);
        self::assertFalse($custom->isCanonical());
    }

    public function test_mention_preserves_source_local_identity_and_raw_values(): void
    {
        $mention = new Mention(
            id: new MentionId('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa'),
            sourceId: new SourceId('11111111-1111-4111-8111-111111111111'),
            kind: MentionKind::person(),
            localKey: 'person.father',
            role: 'father',
            displayLabel: 'Иосифъ Гайда',
            rawData: new MentionRawData([
                'surname' => 'Гайда',
                'age' => '20?',
                'note' => ['original' => 'сынъ Ивана'],
            ]),
        );

        self::assertSame('person.father', $mention->localKey);
        self::assertSame('Иосифъ Гайда', $mention->displayLabel);
        self::assertSame('20?', $mention->rawData->toArray()['age']);
        self::assertSame(['original' => 'сынъ Ивана'], $mention->rawData->toArray()['note']);
    }

    public function test_similar_mentions_in_different_sources_remain_independent(): void
    {
        $left = new Mention(
            id: new MentionId('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa'),
            sourceId: new SourceId('11111111-1111-4111-8111-111111111111'),
            kind: MentionKind::person(),
            localKey: 'person.subject',
            displayLabel: 'Jan Kowalski',
        );
        $right = new Mention(
            id: new MentionId('bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb'),
            sourceId: new SourceId('22222222-2222-4222-8222-222222222222'),
            kind: MentionKind::person(),
            localKey: 'person.subject',
            displayLabel: 'Jan Kowalski',
        );

        self::assertNotSame($left->id->value, $right->id->value);
        self::assertNotSame($left->sourceId->value, $right->sourceId->value);
        self::assertSame($left->localKey, $right->localKey);
        self::assertSame($left->displayLabel, $right->displayLabel);
    }

    public function test_raw_data_rejects_non_json_values(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new MentionRawData(['bad' => new stdClass]);
    }
}
