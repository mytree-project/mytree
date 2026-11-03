<?php

declare(strict_types=1);

namespace Tests\Feature\Search;

use App\Application\Acquisition\CaptureEvidenceStateForSource;
use App\Application\Acquisition\CreateClaim;
use App\Application\Acquisition\CreateMention;
use App\Application\Acquisition\CreateSource;
use App\Application\Search\RebuildSourceSearchDocument;
use App\Application\Search\SearchMatchType;
use App\Application\Search\SearchRequest;
use App\Application\Search\SearchResult;
use App\Application\Search\SearchResultCategory;
use App\Application\Search\SearchSourceResult;
use App\Application\Search\SearchSources;
use App\Domain\Acquisition\MentionKind;
use App\Domain\Acquisition\PredicateKey;
use App\Domain\Acquisition\PredicateVocabulary;
use App\Domain\Acquisition\SourceMetadata;
use App\Domain\Acquisition\SourceType;
use App\Domain\Acquisition\TextClaimValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class SearchResultEnvelopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_source_search_returns_typed_category_envelope_with_provenance_and_scoped_score(): void
    {
        $source = app(CreateSource::class)->handle(
            new SourceType('civil.birth'),
            new SourceMetadata(['title' => 'Record 1879']),
            name: 'Record 1879',
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
            value: new TextClaimValue('Józef'),
        );
        app(CaptureEvidenceStateForSource::class)->capture($source->id);

        $page = app(SearchSources::class)->handle(new SearchRequest(
            query: 'Jozef',
            categories: [SearchResultCategory::Source],
        ));

        self::assertSame([SearchResultCategory::Source], $page->categories);
        self::assertCount(1, $page->results);
        self::assertSame(1, $page->total);
        self::assertFalse($page->candidateWindowTruncated);

        $result = $page->results[0];
        self::assertInstanceOf(SearchResult::class, $result);
        self::assertSame(SearchResultCategory::Source, $result->category);
        self::assertSame('source:'.$source->id->value, $result->resultKey);
        self::assertSame('Record 1879', $result->title);
        self::assertNotNull($result->score);
        self::assertSame('source_retrieval_v1', $result->score->scale);
        self::assertInstanceOf(SearchSourceResult::class, $result->details);
        self::assertSame($result->detailsAs(SearchSourceResult::class)->score, $result->score->value);
        self::assertSame($source->id->value, $result->detailsAs(SearchSourceResult::class)->sourceId->value);
        self::assertCount(1, $result->detailsAs(SearchSourceResult::class)->matchReasons);
        $reason = $result->detailsAs(SearchSourceResult::class)->matchReasons[0];
        self::assertSame(SearchMatchType::TechnicalComparison, $reason->type);
        self::assertSame($mention->id->value, $reason->mentionId);
        self::assertNotNull($reason->claimRevisionId);
    }

    public function test_unsupported_category_scopes_do_not_fabricate_external_results(): void
    {
        $source = app(CreateSource::class)->handle(
            new SourceType('civil.birth'),
            new SourceMetadata(['title' => 'Sources']),
            name: 'Sources',
        );
        app(CaptureEvidenceStateForSource::class)->capture($source->id);

        $externalOnly = app(SearchSources::class)->handle(new SearchRequest(
            query: 'Sources',
            categories: [SearchResultCategory::ExternalIndexObservation],
        ));
        self::assertSame([], $externalOnly->results);
        self::assertSame(0, $externalOnly->total);
        self::assertSame([SearchResultCategory::ExternalIndexObservation], $externalOnly->categories);

        $mixed = app(SearchSources::class)->handle(new SearchRequest(
            query: 'Sources',
            categories: [
                SearchResultCategory::ExternalIndexObservation,
                SearchResultCategory::Source,
                SearchResultCategory::Interpretation,
            ],
        ));
        self::assertCount(1, $mixed->results);
        self::assertSame(SearchResultCategory::Source, $mixed->results[0]->category);
        self::assertSame($source->id->value, $mixed->results[0]->detailsAs(SearchSourceResult::class)->sourceId->value);
    }

    public function test_unaccepted_acquisition_changes_remain_invisible_and_search_does_not_rewrite_claim_history(): void
    {
        $source = app(CreateSource::class)->handle(
            new SourceType('civil.birth'),
            new SourceMetadata(['title' => 'Unaccepted record']),
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
            value: new TextClaimValue('Filip'),
        );

        self::assertSame([], app(SearchSources::class)->handle(new SearchRequest('Filip'))->results);

        app(CaptureEvidenceStateForSource::class)->capture($source->id);
        app(RebuildSourceSearchDocument::class)->handle($source->id);

        $before = DB::table('claim_revisions')->where('source_id', $source->id->value)->count();
        $result = app(SearchSources::class)->handle(new SearchRequest('Filip'));
        self::assertCount(1, $result->results);
        self::assertSame('source:'.$source->id->value, $result->results[0]->resultKey);
        self::assertSame($before, DB::table('claim_revisions')->where('source_id', $source->id->value)->count());
    }

    public function test_filter_only_page_still_uses_common_result_envelope_without_match_reasons(): void
    {
        $source = app(CreateSource::class)->handle(
            new SourceType('civil.birth'),
            new SourceMetadata(['title' => 'Filter only']),
        );
        app(CaptureEvidenceStateForSource::class)->capture($source->id);

        $page = app(SearchSources::class)->handle(new SearchRequest(
            query: '',
            sourceTypeKeys: ['civil.birth'],
        ));
        self::assertCount(1, $page->results);
        self::assertSame('source:'.$source->id->value, $page->results[0]->resultKey);
        self::assertSame(0, $page->results[0]->score?->value);
        self::assertSame([], $page->results[0]->detailsAs(SearchSourceResult::class)->matchReasons);
    }
}
