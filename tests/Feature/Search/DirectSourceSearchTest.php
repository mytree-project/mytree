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
use App\Domain\Acquisition\Source;
use App\Domain\Acquisition\SourceLinguisticRepresentation;
use App\Domain\Acquisition\SourceLinguisticRepresentationRelation;
use App\Domain\Acquisition\SourceMetadata;
use App\Domain\Acquisition\SourceType;
use App\Domain\Acquisition\TextClaimValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DirectSourceSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_plain_multi_term_query_uses_and_with_one_source_result_and_all_material_reasons(): void
    {
        $sameMention = $this->sourceWithNames('same mention', [['Jan', 'Kowalski']]);
        $differentMentions = $this->sourceWithNames('different mentions', [['Jan'], ['Kowalski']]);
        $this->sourceWithNames('partial', [['Jan']]);

        $page = app(SearchSources::class)->handle(new SearchRequest('Jan Kowalski'));

        self::assertCount(2, $page->results);
        self::assertSame($sameMention->id->value, $page->results[0]->sourceId->value);
        self::assertSame($differentMentions->id->value, $page->results[1]->sourceId->value);
        self::assertGreaterThan($page->results[1]->score, $page->results[0]->score);
        self::assertCount(2, $page->results[0]->matchReasons);
        self::assertSame(
            [SearchMatchType::DirectClaimValue, SearchMatchType::DirectClaimValue],
            array_map(static fn ($reason) => $reason->type, $page->results[0]->matchReasons),
        );
        self::assertSame(2, $page->total);
        self::assertFalse($page->candidateWindowTruncated);
    }

    public function test_source_supplied_representation_matches_but_ranks_below_direct_claim_value_for_the_term(): void
    {
        $direct = $this->sourceWithNames('direct', [['Piotr', 'Kowalski']]);

        $represented = app(CreateSource::class)->handle(
            new SourceType('civil.birth'),
            new SourceMetadata(['title' => 'represented']),
            name: 'represented',
        );
        $person = app(CreateMention::class)->handle(
            sourceId: $represented->id,
            kind: new MentionKind('person'),
            localKey: 'person-1',
        );
        app(CreateClaim::class)->handle(
            sourceId: $represented->id,
            subjectMentionId: $person->id,
            predicate: PredicateVocabulary::get(PredicateKey::PersonGivenName),
            value: new TextClaimValue('Peter'),
            sourceLinguisticRepresentations: [
                new SourceLinguisticRepresentation(
                    value: 'Piotr',
                    language: 'pl',
                    script: 'Latn',
                    relation: SourceLinguisticRepresentationRelation::LanguageEquivalent,
                ),
            ],
        );
        app(CreateClaim::class)->handle(
            sourceId: $represented->id,
            subjectMentionId: $person->id,
            predicate: PredicateVocabulary::get(PredicateKey::PersonSurname),
            value: new TextClaimValue('Kowalski'),
        );
        app(CaptureEvidenceStateForSource::class)->capture($represented->id);

        $page = app(SearchSources::class)->handle(new SearchRequest('Piotr Kowalski'));

        self::assertCount(2, $page->results);
        self::assertSame($direct->id->value, $page->results[0]->sourceId->value);
        self::assertSame($represented->id->value, $page->results[1]->sourceId->value);
        self::assertGreaterThan($page->results[1]->score, $page->results[0]->score);
        self::assertContains(
            SearchMatchType::SourceRepresentation,
            array_map(static fn ($reason) => $reason->type, $page->results[1]->matchReasons),
        );
    }

    public function test_source_type_filter_and_bounded_pagination_are_applied_deterministically(): void
    {
        $first = $this->sourceWithNames('first', [['Jan']], 'civil.birth');
        $this->sourceWithNames('other type', [['Jan']], 'church.birth');
        $second = $this->sourceWithNames('second', [['Jan']], 'civil.birth');

        $page = app(SearchSources::class)->handle(new SearchRequest(
            query: 'Jan',
            page: 1,
            perPage: 1,
            sourceTypeKeys: ['civil.birth'],
        ));

        self::assertCount(1, $page->results);
        self::assertContains($page->results[0]->sourceId->value, [$first->id->value, $second->id->value]);
        self::assertSame(2, $page->total);
        self::assertTrue($page->hasMore);
    }

    /**
     * @param  list<list<string>>  $namesByMention
     */
    private function sourceWithNames(
        string $name,
        array $namesByMention,
        string $sourceType = 'civil.birth',
    ): Source {
        $source = app(CreateSource::class)->handle(
            new SourceType($sourceType),
            new SourceMetadata(['title' => $name]),
            name: $name,
        );

        foreach ($namesByMention as $mentionIndex => $names) {
            $mention = app(CreateMention::class)->handle(
                sourceId: $source->id,
                kind: new MentionKind('person'),
                localKey: 'person-'.($mentionIndex + 1),
            );

            foreach ($names as $nameIndex => $value) {
                app(CreateClaim::class)->handle(
                    sourceId: $source->id,
                    subjectMentionId: $mention->id,
                    predicate: PredicateVocabulary::get(
                        $nameIndex === 0 ? PredicateKey::PersonGivenName : PredicateKey::PersonSurname,
                    ),
                    value: new TextClaimValue($value),
                );
            }
        }

        app(CaptureEvidenceStateForSource::class)->capture($source->id);

        return $source;
    }
}
