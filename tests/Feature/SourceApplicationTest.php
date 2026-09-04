<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Acquisition\CreateSource;
use App\Application\Acquisition\GetSource;
use App\Application\Acquisition\SourceRepository;
use App\Application\Acquisition\SourceTextInput;
use App\Application\Acquisition\UpdateSource;
use App\Domain\Acquisition\SourceMetadata;
use App\Domain\Acquisition\SourceTextKind;
use App\Domain\Acquisition\SourceType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SourceApplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_source_can_be_created_retrieved_and_persisted_through_application_boundary(): void
    {
        $created = app(CreateSource::class)->handle(
            type: new SourceType('civil.birth', 2),
            metadata: new SourceMetadata([
                'archive_reference' => 'Fond 12 / Act 7',
                'raw_act_number' => '7?',
            ]),
            texts: [
                new SourceTextInput(
                    kind: SourceTextKind::Transcription,
                    content: 'Иосиф Гайда',
                    language: 'ru',
                ),
                new SourceTextInput(
                    kind: SourceTextKind::Translation,
                    content: 'Józef Gajda',
                    language: 'pl',
                ),
            ],
        );

        $loaded = app(GetSource::class)->handle($created->id);

        self::assertSame($created->id->value, $loaded->id->value);
        self::assertSame('civil.birth', $loaded->type->key);
        self::assertSame(2, $loaded->type->schemaVersion);
        self::assertSame('7?', $loaded->metadata->toArray()['raw_act_number']);
        self::assertCount(2, $loaded->texts);
        self::assertSame(SourceTextKind::Transcription, $loaded->texts[0]->kind);
        self::assertSame(SourceTextKind::Translation, $loaded->texts[1]->kind);
        self::assertSame('Иосиф Гайда', $loaded->texts[0]->content);

        $this->assertDatabaseHas('sources', [
            'id' => $created->id->value,
            'schema_version' => 1,
            'source_type_key' => 'civil.birth',
            'source_type_schema_version' => 2,
        ]);
        $this->assertDatabaseHas('source_texts', [
            'source_id' => $created->id->value,
            'kind' => 'translation',
            'content' => 'Józef Gajda',
        ]);
    }

    public function test_source_update_keeps_source_identity_and_replaces_current_text_state(): void
    {
        $created = app(CreateSource::class)->handle(
            type: SourceType::generic(),
            metadata: SourceMetadata::empty(),
        );

        $updated = app(UpdateSource::class)->handle(
            id: $created->id,
            type: new SourceType('oral_testimony'),
            metadata: new SourceMetadata(['speaker_name_raw' => 'Anna Nowak']),
            texts: [
                new SourceTextInput(
                    kind: SourceTextKind::ResearchNote,
                    content: 'Recorded from family interview.',
                    language: 'en',
                ),
            ],
        );

        self::assertSame($created->id->value, $updated->id->value);

        $loaded = app(SourceRepository::class)->find($created->id);

        self::assertNotNull($loaded);
        self::assertSame('oral_testimony', $loaded->type->key);
        self::assertSame('Anna Nowak', $loaded->metadata->toArray()['speaker_name_raw']);
        self::assertCount(1, $loaded->texts);
        self::assertSame(SourceTextKind::ResearchNote, $loaded->texts[0]->kind);
    }
}
