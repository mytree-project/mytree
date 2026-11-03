<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Application\Search\SearchPage;
use App\Application\Search\SearchRequest;
use App\Application\Search\SearchResult;
use App\Application\Search\SearchResultCategory;
use App\Application\Search\SearchResultDetails;
use App\Application\Search\SearchResultScore;
use App\Application\Search\SearchSourceResult;
use App\Domain\Acquisition\SourceId;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class SearchResultContractTest extends TestCase
{
    public function test_result_envelope_supports_a_distinct_future_payload_without_pretending_it_is_a_source(): void
    {
        $external = new class implements SearchResultDetails {
            public function category(): SearchResultCategory
            {
                return SearchResultCategory::ExternalIndexObservation;
            }

            public function resultId(): string
            {
                return 'provider-x:123';
            }

            public function label(): ?string
            {
                return 'Observation 123';
            }

            public function ranking(): ?SearchResultScore
            {
                return new SearchResultScore('provider_x_relevance', 0.82);
            }
        };

        $source = new SearchSourceResult(
            sourceId: new SourceId('00000000-0000-4000-8000-000000000001'),
            sourceName: 'A Source',
            sourceTypeKey: 'civil.birth',
            score: 95,
            stale: false,
            matchReasons: [],
        );

        $externalResult = new SearchResult($external);
        $sourceResult = new SearchResult($source);
        $page = new SearchPage(
            results: [$externalResult, $sourceResult],
            page: 1,
            perPage: 20,
            total: 2,
            hasMore: false,
            candidateWindowTruncated: false,
            categories: [SearchResultCategory::ExternalIndexObservation, SearchResultCategory::Source],
        );

        self::assertSame('external_index_observation:provider-x:123', $page->results[0]->resultKey);
        self::assertSame('provider_x_relevance', $page->results[0]->score?->scale);
        self::assertSame('source_retrieval_v1', $page->results[1]->score?->scale);
        self::assertNotSame($page->results[0]->score?->scale, $page->results[1]->score?->scale);
        self::assertSame($external, $page->results[0]->details);
        self::assertSame($source, $page->results[1]->details);
    }

    public function test_page_rejects_results_outside_requested_categories(): void
    {
        $source = new SearchResult(new SearchSourceResult(
            sourceId: new SourceId('00000000-0000-4000-8000-000000000001'),
            sourceName: null,
            sourceTypeKey: 'civil.birth',
            score: 0,
            stale: false,
            matchReasons: [],
        ));

        $this->expectException(InvalidArgumentException::class);
        new SearchPage(
            results: [$source],
            page: 1,
            perPage: 20,
            total: 1,
            hasMore: false,
            candidateWindowTruncated: false,
            categories: [SearchResultCategory::Interpretation],
        );
    }

    public function test_empty_category_selection_does_not_mean_all_categories(): void
    {
        $request = new SearchRequest(
            query: 'Jan',
            categories: [],
        );
        self::assertSame([], $request->categories);
        self::assertFalse($request->includesCategory(SearchResultCategory::Source));
    }
}
