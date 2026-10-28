<?php

declare(strict_types=1);

namespace Tests\Feature\Search;

use App\Application\Acquisition\CaptureEvidenceStateForSource;
use App\Application\Acquisition\CreateClaim;
use App\Application\Acquisition\CreateMention;
use App\Application\Acquisition\CreateSource;
use App\Application\Search\SearchMatchType;
use App\Application\Search\SearchRequest;
use App\Application\Search\SearchSources;
use App\Domain\Acquisition\MentionKind;
use App\Domain\Acquisition\PredicateKey;
use App\Domain\Acquisition\PredicateVocabulary;
use App\Domain\Acquisition\SourceMetadata;
use App\Domain\Acquisition\SourceType;
use App\Domain\Acquisition\TextClaimValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TechnicalSourceSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_exact_claim_outscores_folding_and_retains_comparison_form_provenance(): void
    {
        $exact = $this->sourceWithGivenName('Jozef', 'exact');
        $folded = $this->sourceWithGivenName('Józef', 'folded');

        $page = app(SearchSources::class)->handle(new SearchRequest('Jozef'));

        self::assertCount(2, $page->results);
        self::assertSame($exact, $page->results[0]->sourceId->value);
        self::assertSame($folded, $page->results[1]->sourceId->value);
        self::assertSame(SearchMatchType::DirectClaimValue, $page->results[0]->matchReasons[0]->type);
        self::assertSame(SearchMatchType::TechnicalComparison, $page->results[1]->matchReasons[0]->type);
        self::assertSame('Jozef', $page->results[1]->matchReasons[0]->queryTerm);
        self::assertSame('Józef', $page->results[1]->matchReasons[0]->matchedValue);
        self::assertSame('jozef', $page->results[1]->matchReasons[0]->matchedForm);
        self::assertNotNull($page->results[1]->matchReasons[0]->claimRevisionId);
    }

    public function test_latin_query_matches_cyrillic_claim_without_modifying_source_value(): void
    {
        $source = $this->sourceWithGivenName('Александр', 'cyrillic');

        $page = app(SearchSources::class)->handle(new SearchRequest('Aleksandr'));

        self::assertCount(1, $page->results);
        self::assertSame($source, $page->results[0]->sourceId->value);
        $reason = $page->results[0]->matchReasons[0];
        self::assertSame(SearchMatchType::TechnicalComparison, $reason->type);
        self::assertSame('Александр', $reason->matchedValue);
        self::assertSame('aleksandr', $reason->matchedForm);
        self::assertNotNull($reason->mentionId);
        self::assertNotNull($reason->claimId);
    }

    public function test_candidate_limit_counts_sources_not_matching_claim_rows(): void
    {
        config()->set('search.candidate_limit', 1);

        $source = app(CreateSource::class)->handle(
            new SourceType('civil.birth'),
            new SourceMetadata(['title' => 'complete source']),
        );
        $mention = app(CreateMention::class)->handle(
            sourceId: $source->id,
            kind: new MentionKind('person'),
            localKey: 'person-1',
        );
        foreach ([
            [PredicateKey::PersonGivenName, 'Józef'],
            [PredicateKey::PersonSurname, 'Kowalski'],
        ] as [$predicate, $value]) {
            app(CreateClaim::class)->handle(
                sourceId: $source->id,
                subjectMentionId: $mention->id,
                predicate: PredicateVocabulary::get($predicate),
                value: new TextClaimValue($value),
            );
        }
        app(CaptureEvidenceStateForSource::class)->capture($source->id);

        $page = app(SearchSources::class)->handle(new SearchRequest('Jozef Kowalski'));

        self::assertCount(1, $page->results);
        self::assertSame($source->id->value, $page->results[0]->sourceId->value);
        self::assertCount(2, $page->results[0]->matchReasons);
        self::assertSame(1, $page->total);
        self::assertFalse($page->candidateWindowTruncated);
    }

    private function sourceWithGivenName(string $value, string $title): string
    {
        $source = app(CreateSource::class)->handle(
            new SourceType('civil.birth'),
            new SourceMetadata(['title' => $title]),
        );
        $mention = app(CreateMention::class)->handle(
            sourceId: $source->id,
            kind: new MentionKind('person'),
            localKey: 'person-1',
        );
        app(CreateClaim::class)->handle(
            sourceId: $source->id,
            subjectMentionId: $mention->id,
            predicate: PredicateVocabulary::get(PredicateKey::PersonGivenName),
            value: new TextClaimValue($value),
        );
        app(CaptureEvidenceStateForSource::class)->capture($source->id);

        return $source->id->value;
    }
}
