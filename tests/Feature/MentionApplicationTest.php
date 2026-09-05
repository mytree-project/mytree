<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Acquisition\CreateMention;
use App\Application\Acquisition\CreateSource;
use App\Application\Acquisition\GetMention;
use App\Application\Acquisition\ListSourceMentions;
use App\Application\Acquisition\MentionNotFound;
use App\Application\Acquisition\RemoveMention;
use App\Application\Acquisition\UpdateMention;
use App\Domain\Acquisition\MentionKind;
use App\Domain\Acquisition\MentionRawData;
use App\Domain\Acquisition\SourceType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MentionApplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_mention_can_be_created_retrieved_and_persisted_for_one_source(): void
    {
        $source = app(CreateSource::class)->handle(SourceType::generic());

        $created = app(CreateMention::class)->handle(
            sourceId: $source->id,
            kind: MentionKind::person(),
            localKey: 'person.child',
            role: 'child',
            displayLabel: 'Иосифъ Гайда',
            rawData: new MentionRawData([
                'given_name' => 'Иосифъ',
                'age' => '20?',
            ]),
        );

        $loaded = app(GetMention::class)->handle($source->id, $created->id);

        self::assertSame($source->id->value, $loaded->sourceId->value);
        self::assertSame($created->id->value, $loaded->id->value);
        self::assertSame('person', $loaded->kind->key);
        self::assertSame('person.child', $loaded->localKey);
        self::assertSame('child', $loaded->role);
        self::assertSame('Иосифъ Гайда', $loaded->displayLabel);
        self::assertSame('20?', $loaded->rawData->toArray()['age']);

        $this->assertDatabaseHas('mentions', [
            'id' => $created->id->value,
            'source_id' => $source->id->value,
            'schema_version' => 1,
            'kind' => 'person',
            'local_key' => 'person.child',
        ]);
    }

    public function test_same_source_local_key_in_different_sources_stays_independent(): void
    {
        $leftSource = app(CreateSource::class)->handle(SourceType::generic());
        $rightSource = app(CreateSource::class)->handle(SourceType::generic());

        $left = app(CreateMention::class)->handle(
            sourceId: $leftSource->id,
            kind: MentionKind::person(),
            localKey: 'person.subject',
            displayLabel: 'Jan Kowalski',
        );
        $right = app(CreateMention::class)->handle(
            sourceId: $rightSource->id,
            kind: MentionKind::person(),
            localKey: 'person.subject',
            displayLabel: 'Jan Kowalski',
        );

        self::assertNotSame($left->id->value, $right->id->value);
        self::assertSame('person.subject', $left->localKey);
        self::assertSame('person.subject', $right->localKey);
        self::assertCount(1, app(ListSourceMentions::class)->handle($leftSource->id));
        self::assertCount(1, app(ListSourceMentions::class)->handle($rightSource->id));
    }

    public function test_source_mentions_are_listed_by_stable_local_key_not_creation_order(): void
    {
        $source = app(CreateSource::class)->handle(SourceType::generic());

        app(CreateMention::class)->handle(
            sourceId: $source->id,
            kind: MentionKind::person(),
            localKey: 'person.witness.2',
        );
        app(CreateMention::class)->handle(
            sourceId: $source->id,
            kind: MentionKind::person(),
            localKey: 'person.witness.1',
        );

        $mentions = app(ListSourceMentions::class)->handle($source->id);

        self::assertCount(2, $mentions);
        self::assertSame('person.witness.1', $mentions[0]->localKey);
        self::assertSame('person.witness.2', $mentions[1]->localKey);
    }

    public function test_update_preserves_mention_source_and_local_key(): void
    {
        $source = app(CreateSource::class)->handle(SourceType::generic());
        $mention = app(CreateMention::class)->handle(
            sourceId: $source->id,
            kind: MentionKind::person(),
            localKey: 'person.witness.1',
            role: 'witness',
            displayLabel: 'Jan Kowalski',
            rawData: new MentionRawData(['surname' => 'Kowalski']),
        );

        $updated = app(UpdateMention::class)->handle(
            sourceId: $source->id,
            mentionId: $mention->id,
            kind: MentionKind::person(),
            role: 'godparent',
            displayLabel: 'Jan Kowalski',
            rawData: new MentionRawData(['surname' => 'Kowalski', 'note' => 'chrzestny']),
        );

        self::assertSame($source->id->value, $updated->sourceId->value);
        self::assertSame('person.witness.1', $updated->localKey);
        self::assertSame('godparent', $updated->role);
        self::assertSame('chrzestny', $updated->rawData->toArray()['note']);
    }

    public function test_mention_cannot_be_updated_through_another_source(): void
    {
        $owner = app(CreateSource::class)->handle(SourceType::generic());
        $other = app(CreateSource::class)->handle(SourceType::generic());
        $mention = app(CreateMention::class)->handle(
            sourceId: $owner->id,
            kind: MentionKind::person(),
            localKey: 'person.subject',
        );

        try {
            app(UpdateMention::class)->handle(
                sourceId: $other->id,
                mentionId: $mention->id,
                kind: MentionKind::person(),
                displayLabel: 'Changed',
            );

            self::fail('Expected MentionNotFound to be thrown.');
        } catch (MentionNotFound) {
            $loaded = app(GetMention::class)->handle($owner->id, $mention->id);

            self::assertSame($owner->id->value, $loaded->sourceId->value);
            self::assertNull($loaded->displayLabel);
        }
    }

    public function test_mention_can_be_removed_only_from_its_source(): void
    {
        $source = app(CreateSource::class)->handle(SourceType::generic());
        $mention = app(CreateMention::class)->handle(
            sourceId: $source->id,
            kind: MentionKind::place(),
            localKey: 'place.birth',
            displayLabel: 'Kraków',
        );

        $removed = app(RemoveMention::class)->handle($source->id, $mention->id);

        self::assertSame($mention->id->value, $removed->id->value);
        self::assertSame([], app(ListSourceMentions::class)->handle($source->id));

        $this->expectException(MentionNotFound::class);
        app(GetMention::class)->handle($source->id, $mention->id);
    }
}
