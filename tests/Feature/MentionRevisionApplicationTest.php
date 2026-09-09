<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Acquisition\CreateMention;
use App\Application\Acquisition\CreateSource;
use App\Application\Acquisition\GetMentionRevision;
use App\Application\Acquisition\ListMentionRevisions;
use App\Application\Acquisition\ListSourceMentionRevisions;
use App\Application\Acquisition\RemoveMention;
use App\Application\Acquisition\UpdateMention;
use App\Domain\Acquisition\MentionKind;
use App\Domain\Acquisition\MentionRawData;
use App\Domain\Acquisition\MentionRevision;
use App\Domain\Acquisition\SourceType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MentionRevisionApplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_creation_records_an_initial_immutable_revision(): void
    {
        $source = app(CreateSource::class)->handle(SourceType::generic());
        $mention = app(CreateMention::class)->handle(
            sourceId: $source->id,
            kind: MentionKind::person(),
            localKey: 'person.subject',
            role: 'subject',
            displayLabel: 'Иосифъ Гайда',
            rawData: new MentionRawData(['descriptor' => 'однодворец']),
            changeNote: 'Initial transcription',
            changedBy: 'manual:test',
        );

        $revisions = app(ListMentionRevisions::class)->handle($source->id, $mention->id);

        self::assertCount(1, $revisions);
        self::assertSame(1, $revisions[0]->revisionNumber);
        self::assertSame($mention->id->value, $revisions[0]->mentionId->value);
        self::assertSame($source->id->value, $revisions[0]->sourceId->value);
        self::assertSame('Initial transcription', $revisions[0]->changeNote);
        self::assertSame('manual:test', $revisions[0]->changedBy);
        self::assertSame('однодворец', $revisions[0]->reconstruct()->rawData->toArray()['descriptor']);

        $this->assertDatabaseHas('mention_revisions', [
            'id' => $revisions[0]->id->value,
            'source_id' => $source->id->value,
            'mention_id' => $mention->id->value,
            'revision_number' => 1,
            'snapshot_schema_version' => 1,
        ]);
    }

    public function test_semantic_update_appends_history_and_no_op_update_does_not(): void
    {
        $source = app(CreateSource::class)->handle(SourceType::generic());
        $mention = app(CreateMention::class)->handle(
            sourceId: $source->id,
            kind: MentionKind::person(),
            localKey: 'person.subject',
            role: 'subject',
            displayLabel: 'Jan Gajda',
            rawData: new MentionRawData(['occupation' => 'rolnik']),
        );

        $updated = app(UpdateMention::class)->handle(
            sourceId: $source->id,
            mentionId: $mention->id,
            kind: MentionKind::person(),
            role: 'subject',
            displayLabel: 'Jan Gajda',
            rawData: new MentionRawData([
                'occupation' => 'rolnik',
                'descriptor' => 'włościanin',
            ]),
            changeNote: 'Preserve source descriptor',
        );

        app(UpdateMention::class)->handle(
            sourceId: $source->id,
            mentionId: $mention->id,
            kind: MentionKind::person(),
            role: 'subject',
            displayLabel: 'Jan Gajda',
            rawData: new MentionRawData([
                'descriptor' => 'włościanin',
                'occupation' => 'rolnik',
            ]),
            changeNote: 'This no-op must not create a revision',
        );

        $revisions = app(ListMentionRevisions::class)->handle($source->id, $mention->id);

        self::assertCount(2, $revisions);
        self::assertSame([1, 2], array_map(
            static fn (MentionRevision $revision): int => $revision->revisionNumber,
            $revisions,
        ));
        self::assertNotSame($revisions[0]->id->value, $revisions[1]->id->value);
        self::assertSame('person.subject', $revisions[0]->reconstruct()->localKey);
        self::assertSame('person.subject', $revisions[1]->reconstruct()->localKey);
        self::assertSame(['occupation' => 'rolnik'], $revisions[0]->reconstruct()->rawData->toArray());
        self::assertEquals($updated->rawData->toArray(), $revisions[1]->reconstruct()->rawData->toArray());
        self::assertSame('Preserve source descriptor', $revisions[1]->changeNote);
    }

    public function test_history_remains_readable_after_current_mention_is_removed(): void
    {
        $source = app(CreateSource::class)->handle(SourceType::generic());
        $mention = app(CreateMention::class)->handle(
            sourceId: $source->id,
            kind: MentionKind::place(),
            localKey: 'place.birth',
            displayLabel: 'Kraków',
        );
        app(UpdateMention::class)->handle(
            sourceId: $source->id,
            mentionId: $mention->id,
            kind: MentionKind::place(),
            displayLabel: 'Kraków, Polska',
        );

        app(RemoveMention::class)->handle($source->id, $mention->id);

        $revisions = app(ListMentionRevisions::class)->handle($source->id, $mention->id);
        $first = app(GetMentionRevision::class)->handle($source->id, $mention->id, 1);
        $sourceHistory = app(ListSourceMentionRevisions::class)->handle($source->id);

        self::assertCount(2, $revisions);
        self::assertCount(2, $sourceHistory);
        self::assertSame('Kraków', $first->reconstruct()->displayLabel);
        self::assertSame('Kraków, Polska', $revisions[1]->reconstruct()->displayLabel);
        $this->assertDatabaseMissing('mentions', ['id' => $mention->id->value]);
        $this->assertDatabaseCount('mention_revisions', 2);
    }
}
