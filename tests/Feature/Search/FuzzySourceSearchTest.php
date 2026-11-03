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

final class FuzzySourceSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_fuzzy_is_disabled_by_default(): void
    {
        $this->sourceWithNames('Kutniewicz');

        $results = app(SearchSources::class)->handle(new SearchRequest('Kupniewicz'))->results;

        self::assertCount(0, $results);
    }

    public function test_enabled_fuzzy_recovers_a_typo_but_exact_sources_rank_first(): void
    {
        config()->set('search.fuzzy.enabled', true);

        $exact = $this->sourceWithNames('Kupniewicz', 'exact');
        $near = $this->sourceWithNames('Kutniewicz', 'near');
        $this->sourceWithNames('Kowalski', 'unrelated');

        $page = app(SearchSources::class)->handle(new SearchRequest('Kupniewicz'));

        self::assertCount(2, $page->results);
        self::assertSame($exact, $page->results[0]->sourceId->value);
        self::assertSame($near, $page->results[1]->sourceId->value);
        self::assertSame(SearchMatchType::DirectClaimValue, $page->results[0]->matchReasons[0]->type);

        $reason = $page->results[1]->matchReasons[0];
        self::assertSame(SearchMatchType::FuzzyComparison, $reason->type);
        self::assertSame('Kupniewicz', $reason->queryTerm);
        self::assertSame('Kutniewicz', $reason->matchedValue);
        self::assertSame('person.surname', $reason->field);
        self::assertGreaterThan(0.8, $reason->similarity);
        self::assertLessThan(1.0, $reason->similarity);
        self::assertNotNull($reason->claimRevisionId);
        self::assertGreaterThan($page->results[1]->score, $page->results[0]->score);
        self::assertFalse($page->candidateWindowTruncated);
    }

    public function test_fuzzy_threshold_rejects_similar_names_below_cutoff(): void
    {
        config()->set('search.fuzzy.enabled', true);
        config()->set('search.fuzzy.threshold', 0.95);
        $this->sourceWithNames('Kutniewicz');

        $page = app(SearchSources::class)->handle(new SearchRequest('Kupniewicz'));

        self::assertSame([], $page->results);
    }

    public function test_fuzzy_and_exact_terms_satisfy_source_local_and(): void
    {
        config()->set('search.fuzzy.enabled', true);
        $matching = $this->sourceWithNames('Kutniewicz', 'matching', 'civil.birth', 'Jan');
        $this->sourceWithNames('Kutniewicz', 'surname only');

        $page = app(SearchSources::class)->handle(new SearchRequest('Jan Kupniewicz'));

        self::assertCount(1, $page->results);
        self::assertSame($matching, $page->results[0]->sourceId->value);
        self::assertCount(2, $page->results[0]->matchReasons);
        self::assertContains(
            SearchMatchType::FuzzyComparison,
            array_map(static fn ($reason) => $reason->type, $page->results[0]->matchReasons),
        );
        self::assertContains(
            SearchMatchType::DirectClaimValue,
            array_map(static fn ($reason) => $reason->type, $page->results[0]->matchReasons),
        );
    }

    public function test_fuzzy_candidate_window_is_bounded_and_source_type_filter_is_honored(): void
    {
        config()->set('search.fuzzy.enabled', true);
        config()->set('search.fuzzy.candidate_limit', 1);

        $this->sourceWithNames('Kutniewicz', 'first');
        $this->sourceWithNames('Kutniewicz', 'second');
        $this->sourceWithNames('Kutniewicz', 'other type', 'church.birth');

        $page = app(SearchSources::class)->handle(new SearchRequest(
            query: 'Kupniewicz',
            sourceTypeKeys: ['civil.birth'],
        ));

        self::assertCount(1, $page->results);
        self::assertTrue($page->candidateWindowTruncated);
        self::assertNull($page->total);
        self::assertTrue($page->hasMore);
        self::assertSame('civil.birth', $page->results[0]->sourceTypeKey);
    }

    private function sourceWithNames(
        string $surname,
        string $name = 'record',
        string $sourceType = 'civil.birth',
        ?string $givenName = null,
    ): string {
        $source = app(CreateSource::class)->handle(
            new SourceType($sourceType),
            new SourceMetadata(['title' => $name]),
            name: $name,
        );
        $mention = app(CreateMention::class)->handle(
            sourceId: $source->id,
            kind: MentionKind::person(),
            localKey: 'person-1',
        );

        if ($givenName !== null) {
            app(CreateClaim::class)->handle(
                sourceId: $source->id,
                subjectMentionId: $mention->id,
                predicate: PredicateVocabulary::get(PredicateKey::PersonGivenName),
                value: new TextClaimValue($givenName),
            );
        }

        app(CreateClaim::class)->handle(
            sourceId: $source->id,
            subjectMentionId: $mention->id,
            predicate: PredicateVocabulary::get(PredicateKey::PersonSurname),
            value: new TextClaimValue($surname),
        );
        app(CaptureEvidenceStateForSource::class)->capture($source->id);

        return $source->id->value;
    }
}
