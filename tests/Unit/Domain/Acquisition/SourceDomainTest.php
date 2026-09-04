<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Acquisition;

use App\Domain\Acquisition\Source;
use App\Domain\Acquisition\SourceId;
use App\Domain\Acquisition\SourceMetadata;
use App\Domain\Acquisition\SourceText;
use App\Domain\Acquisition\SourceTextId;
use App\Domain\Acquisition\SourceTextKind;
use App\Domain\Acquisition\SourceType;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class SourceDomainTest extends TestCase
{
    public function test_source_type_is_explicit_and_versioned(): void
    {
        $type = new SourceType('civil.birth', 3);

        self::assertSame('civil.birth', $type->key);
        self::assertSame(3, $type->schemaVersion);
    }

    public function test_source_text_kinds_keep_derived_representations_distinct(): void
    {
        $transcription = new SourceText(
            id: new SourceTextId('11111111-1111-4111-8111-111111111111'),
            kind: SourceTextKind::Transcription,
            content: 'Иосиф Гайда',
            language: 'ru',
        );
        $translation = new SourceText(
            id: new SourceTextId('22222222-2222-4222-8222-222222222222'),
            kind: SourceTextKind::Translation,
            content: 'Józef Gajda',
            language: 'pl',
        );

        self::assertFalse($transcription->isDerivedRepresentation());
        self::assertTrue($translation->isDerivedRepresentation());
        self::assertSame('Иосиф Гайда', $transcription->content);
        self::assertSame('Józef Gajda', $translation->content);
    }

    public function test_source_preserves_raw_metadata_without_normalizing_it(): void
    {
        $metadata = new SourceMetadata([
            'archive_reference' => 'Житомир / 12-А',
            'raw_act_number' => '20?',
        ]);

        $source = new Source(
            id: new SourceId('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa'),
            type: SourceType::generic(),
            metadata: $metadata,
        );

        self::assertSame([
            'archive_reference' => 'Житомир / 12-А',
            'raw_act_number' => '20?',
        ], $source->metadata->toArray());
    }

    public function test_invalid_source_type_key_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SourceType('Civil Birth');
    }
}
