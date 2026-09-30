<?php

declare(strict_types=1);

namespace Tests\Feature\Search;

use App\Application\Acquisition\CaptureEvidenceStateForSource;
use App\Application\Acquisition\CreateClaim;
use App\Application\Acquisition\CreateMention;
use App\Application\Acquisition\CreateSource;
use App\Application\Search\RebuildSourceSearchDocument;
use App\Application\Search\SearchIndexProfile;
use App\Application\Search\SearchNameInput;
use App\Application\Search\SearchNameProcessor;
use App\Application\Search\SearchNameType;
use App\Application\Search\SearchValueOrigin;
use App\Domain\Acquisition\MentionKind;
use App\Domain\Acquisition\PredicateKey;
use App\Domain\Acquisition\PredicateVocabulary;
use App\Domain\Acquisition\SourceMetadata;
use App\Domain\Acquisition\SourceType;
use App\Domain\Acquisition\TextClaimValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class NameProcessingSearchProjectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_application_adapter_derives_deterministic_index_forms_and_signature(): void
    {
        $processor = app(SearchNameProcessor::class);
        $profile = app(SearchIndexProfile::class)->nameProcessing;

        self::assertSame(
            ['Józef', 'józef', 'jozef'],
            $processor->indexForms(
                new SearchNameInput('Józef', SearchNameType::GivenName, language: 'pl', script: 'Latn'),
                $profile,
            ),
        );

        $cyrillicForms = $processor->indexForms(
            new SearchNameInput('Александр', SearchNameType::GivenName, language: 'ru', script: 'Cyrl'),
            $profile,
        );
        self::assertContains('Александр', $cyrillicForms);
        self::assertContains('александр', $cyrillicForms);
        self::assertContains('aleksandr', $cyrillicForms);

        $signature = $processor->signature($profile);
        self::assertSame(64, strlen($signature));
        self::assertSame($signature, $processor->signature($profile));
    }

    public function test_rebuild_persists_processed_forms_without_rewriting_claim_value(): void
    {
        $source = app(CreateSource::class)->handle(
            type: new SourceType('civil.birth'),
            metadata: new SourceMetadata(['title' => 'Cyrillic birth record']),
        );
        $person = app(CreateMention::class)->handle(
            sourceId: $source->id,
            kind: new MentionKind('person'),
            localKey: 'person-1',
        );
        app(CreateClaim::class)->handle(
            sourceId: $source->id,
            subjectMentionId: $person->id,
            predicate: PredicateVocabulary::get(PredicateKey::PersonGivenName),
            value: new TextClaimValue('Александр'),
        );
        app(CaptureEvidenceStateForSource::class)->capture($source->id);

        $document = app(RebuildSourceSearchDocument::class)->handle($source->id);
        $claimEntries = array_values(array_filter(
            $document->entries,
            static fn ($entry): bool => $entry->origin === SearchValueOrigin::ClaimValue,
        ));

        self::assertCount(1, $claimEntries);
        self::assertSame('Александр', $claimEntries[0]->value);
        self::assertContains('александр', $claimEntries[0]->indexForms);
        self::assertContains('aleksandr', $claimEntries[0]->indexForms);
        self::assertSame(app(SearchIndexProfile::class)->signature(), $document->indexSignature);
    }
}
