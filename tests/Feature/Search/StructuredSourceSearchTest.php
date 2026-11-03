<?php

declare(strict_types=1);

namespace Tests\Feature\Search;

use App\Application\Acquisition\CaptureEvidenceStateForSource;
use App\Application\Acquisition\CreateClaim;
use App\Application\Acquisition\CreateMention;
use App\Application\Acquisition\CreateSource;
use App\Application\Search\RebuildSourceSearchDocument;
use App\Application\Search\SearchDocumentRepository;
use App\Application\Search\SearchRequest;
use App\Application\Search\SearchRuntimeConfiguration;
use App\Application\Search\SearchSortMode;
use App\Application\Search\SearchSources;
use App\Application\Search\SearchVariantDictionary;
use App\Domain\Acquisition\DateClaimValue;
use App\Domain\Acquisition\HistoricalDate;
use App\Domain\Acquisition\MentionKind;
use App\Domain\Acquisition\PredicateKey;
use App\Domain\Acquisition\PredicateVocabulary;
use App\Domain\Acquisition\Source;
use App\Domain\Acquisition\SourceMetadata;
use App\Domain\Acquisition\SourceType;
use App\Domain\Acquisition\TextClaimValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

final class StructuredSourceSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_date_filters_intersect_accepted_claim_date_intervals_including_month_and_year_precision(): void
    {
        $inRange = $this->source('dated range');
        $this->addClaim($inRange, PredicateKey::PersonGivenName, new TextClaimValue('Jan'));
        $this->addClaim($inRange, PredicateKey::PersonBirthDate, DateClaimValue::range(
            '1901/1903', HistoricalDate::month(1901, 12), HistoricalDate::month(1903, 2),
        ));
        $this->index($inRange);

        $outside = $this->source('outside');
        $this->addClaim($outside, PredicateKey::PersonGivenName, new TextClaimValue('Jan'));
        $this->addClaim($outside, PredicateKey::PersonBirthDate, DateClaimValue::exact(
            '1899', HistoricalDate::year(1899),
        ));
        $this->index($outside);

        $page = app(SearchSources::class)->handle(new SearchRequest(query: 'Jan', dateFrom: '1902', dateTo: '1902'));
        self::assertCount(1, $page->results);
        self::assertSame($inRange->id->value, $page->results[0]->sourceId->value);

        $february = app(SearchSources::class)->handle(new SearchRequest(query: '', dateFrom: '1903-02', dateTo: '1903-02'));
        self::assertCount(1, $february->results);
        self::assertSame($inRange->id->value, $february->results[0]->sourceId->value);
        self::assertSame(0, $february->results[0]->score);
        self::assertSame([], $february->results[0]->matchReasons);

        $april = app(SearchSources::class)->handle(new SearchRequest(query: 'Jan', dateFrom: '1903-04', dateTo: '1903-04'));
        self::assertSame([], $april->results);
    }

    public function test_source_level_date_metadata_and_sort_priority_to_metadata_over_claim_dates(): void
    {
        $dated = $this->source('source date', ['date' => '1905-04']);
        $this->addClaim($dated, PredicateKey::PersonGivenName, new TextClaimValue('Jan'));
        $this->addClaim($dated, PredicateKey::PersonBirthDate, DateClaimValue::exact(
            '1880', HistoricalDate::year(1880),
        ));
        $this->index($dated);

        $claimOnly = $this->source('claim date');
        $this->addClaim($claimOnly, PredicateKey::PersonGivenName, new TextClaimValue('Jan'));
        $this->addClaim($claimOnly, PredicateKey::PersonBirthDate, DateClaimValue::exact(
            '1902', HistoricalDate::year(1902),
        ));
        $this->index($claimOnly);

        $undated = $this->source('undated');
        $this->addClaim($undated, PredicateKey::PersonGivenName, new TextClaimValue('Jan'));
        $this->index($undated);

        $results = app(SearchSources::class)->handle(new SearchRequest(
            query: 'Jan',
            sort: SearchSortMode::SourceDate,
        ))->results;
        self::assertSame(
            [$dated->id->value, $claimOnly->id->value, $undated->id->value],
            array_map(static fn ($result) => $result->sourceId->value, $results),
        );

        $metadataDate = app(SearchSources::class)->handle(new SearchRequest(query: '', dateFrom: '1905-04-30', dateTo: '1905-04-30'));
        self::assertSame([$dated->id->value], array_map(static fn ($result) => $result->sourceId->value, $metadataDate->results));

        $claimDate = app(SearchSources::class)->handle(new SearchRequest(query: '', dateFrom: '1880'));
        self::assertContains($dated->id->value, array_map(static fn ($result) => $result->sourceId->value, $claimDate->results));
    }

    public function test_source_metadata_date_ranges_are_recognized_but_unknown_date_strings_are_not_invented(): void
    {
        $source = $this->source('range metadata', ['date_from' => '1910-03', 'date_to' => '1912', 'date_note' => '1900']);
        $this->index($source);

        $inside = app(SearchSources::class)->handle(new SearchRequest(query: '', dateFrom: '1911'));
        self::assertCount(1, $inside->results);

        $outside = app(SearchSources::class)->handle(new SearchRequest(query: '', dateTo: '1900'));
        self::assertSame([], $outside->results);
    }

    public function test_place_filter_uses_technical_and_dictionary_expansion_with_source_local_and(): void
    {
        $matching = $this->source('matching');
        $this->addClaim($matching, PredicateKey::PersonGivenName, new TextClaimValue('Jan'));
        $this->addClaim($matching, PredicateKey::PlaceName, new TextClaimValue('Posen'));
        $this->addClaim($matching, PredicateKey::PersonBirthDate, DateClaimValue::exact('1902', HistoricalDate::year(1902)));
        $this->index($matching);

        $surnameNotPlace = $this->source('surname only');
        $this->addClaim($surnameNotPlace, PredicateKey::PersonGivenName, new TextClaimValue('Jan'));
        $this->addClaim($surnameNotPlace, PredicateKey::PersonSurname, new TextClaimValue('Posen'));
        $this->addClaim($surnameNotPlace, PredicateKey::PersonBirthDate, DateClaimValue::exact('1902', HistoricalDate::year(1902)));
        $this->index($surnameNotPlace);

        $otherType = $this->source('other type', [], 'church.birth');
        $this->addClaim($otherType, PredicateKey::PersonGivenName, new TextClaimValue('Jan'));
        $this->addClaim($otherType, PredicateKey::PlaceName, new TextClaimValue('Posen'));
        $this->index($otherType);

        app(SearchVariantDictionary::class)->import(json_encode([
            'schema' => 'mytree.name-variants.v1',
            'id' => 'given-names.v1',
            'version' => '1.2.0',
            'groups' => [
                [
                    'id' => 'poznan',
                    'variants' => [
                        ['value' => 'Poznań', 'language' => 'pl', 'script' => 'Latn', 'type' => 'place_name', 'metadata' => []],
                        ['value' => 'Posen', 'language' => 'de', 'script' => 'Latn', 'type' => 'place_name', 'metadata' => []],
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

        $page = app(SearchSources::class)->handle(new SearchRequest(
            query: 'Jan',
            sourceTypeKeys: ['civil.birth'],
            place: 'Poznań',
            dateFrom: '1902',
            dateTo: '1902',
        ));
        self::assertCount(1, $page->results);
        self::assertSame($matching->id->value, $page->results[0]->sourceId->value);
        self::assertCount(1, $page->results[0]->matchReasons);
        self::assertSame('Jan', $page->results[0]->matchReasons[0]->matchedValue);

        $filteredOnly = app(SearchSources::class)->handle(new SearchRequest(query: '', place: 'Poznań'));
        self::assertCount(2, $filteredOnly->results);

        $technical = app(SearchSources::class)->handle(new SearchRequest(query: '', place: 'POSEN'));
        self::assertCount(2, $technical->results);
    }

    public function test_filters_precede_candidate_limit_and_creation_date_sort_is_deterministic(): void
    {
        config()->set('search.candidate_limit', 1);

        $first = $this->source('older', ['date' => '1901']);
        $this->addClaim($first, PredicateKey::PersonGivenName, new TextClaimValue('Jan'));
        $this->index($first);

        $second = $this->source('newer', ['date' => '1902']);
        $this->addClaim($second, PredicateKey::PersonGivenName, new TextClaimValue('Jan'));
        $this->index($second);

        $only = app(SearchSources::class)->handle(new SearchRequest(query: 'Jan', dateFrom: '1902', dateTo: '1902'));
        self::assertCount(1, $only->results);
        self::assertSame($second->id->value, $only->results[0]->sourceId->value);
        self::assertFalse($only->candidateWindowTruncated);

        config()->set('search.candidate_limit', 10);
        app()->forgetInstance(SearchRuntimeConfiguration::class);
        DB::table('sources')->where('id', $first->id->value)->update(['created_at' => '2026-01-01 00:00:00']);
        DB::table('sources')->where('id', $second->id->value)->update(['created_at' => '2026-03-01 00:00:00']);

        $recent = app(SearchSources::class)->handle(new SearchRequest(query: '', sort: SearchSortMode::SourceAdded));
        self::assertSame(
            [$second->id->value, $first->id->value],
            array_map(static fn ($result) => $result->sourceId->value, $recent->results),
        );

        $p1 = app(SearchSources::class)->handle(new SearchRequest(query: 'Jan', sort: SearchSortMode::SourceAdded, page: 1, perPage: 1));
        $p2 = app(SearchSources::class)->handle(new SearchRequest(query: 'Jan', sort: SearchSortMode::SourceAdded, page: 2, perPage: 1));
        self::assertSame($second->id->value, $p1->results[0]->sourceId->value);
        self::assertSame($first->id->value, $p2->results[0]->sourceId->value);
    }

    public function test_rebuild_replaces_date_projection_without_duplicating_rows(): void
    {
        $source = $this->source('one date');
        $this->addClaim($source, PredicateKey::PersonBirthDate, DateClaimValue::exact('1902', HistoricalDate::year(1902)));
        $this->index($source);

        app(RebuildSourceSearchDocument::class)->handle($source->id);
        app(RebuildSourceSearchDocument::class)->handle($source->id);

        self::assertSame(1, DB::table('search_document_dates')->where('source_id', $source->id->value)->count());
        $document = app(SearchDocumentRepository::class)->find($source->id);
        self::assertNotNull($document);
        self::assertCount(1, $document->dates);
        self::assertSame('person.birth_date', $document->dates[0]->field);
        self::assertNotNull($document->dates[0]->claimRevisionId);
    }

    public function test_request_rejects_reversed_or_malformed_date_ranges(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new SearchRequest(query: '', dateFrom: '1903', dateTo: '1902');
    }

    /** @param  array<string, mixed>  $metadata */
    private function source(string $name, array $metadata = [], string $type = 'civil.birth'): Source
    {
        return app(CreateSource::class)->handle(
            new SourceType($type),
            new SourceMetadata(['title' => $name, ...$metadata]),
            name: $name,
        );
    }

    private function addClaim(Source $source, PredicateKey $predicate, TextClaimValue|DateClaimValue $value): void
    {
        $kind = $predicate === PredicateKey::PlaceName ? MentionKind::place() : MentionKind::person();
        $mention = app(CreateMention::class)->handle(
            sourceId: $source->id,
            kind: $kind,
            localKey: 'mention-'.uniqid(),
        );
        app(CreateClaim::class)->handle(
            sourceId: $source->id,
            subjectMentionId: $mention->id,
            predicate: PredicateVocabulary::get($predicate),
            value: $value,
        );
    }

    private function index(Source $source): void
    {
        app(CaptureEvidenceStateForSource::class)->capture($source->id);
        app(RebuildSourceSearchDocument::class)->handle($source->id);
    }
}
