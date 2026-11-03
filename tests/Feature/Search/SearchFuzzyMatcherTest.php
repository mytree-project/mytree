<?php

declare(strict_types=1);

namespace Tests\Feature\Search;

use App\Application\Search\DirectSearchCandidate;
use App\Application\Search\SearchFuzzyMatcher;
use App\Application\Search\SearchQueryTerm;
use App\Application\Search\SearchValueOrigin;
use App\Domain\Acquisition\SourceId;
use PHPUnit\Framework\TestCase;

final class SearchFuzzyMatcherTest extends TestCase
{
    public function test_unicode_characters_are_compared_as_code_points_not_utf8_bytes(): void
    {
        $candidate = new DirectSearchCandidate(
            sourceId: new SourceId('00000000-0000-4000-8000-000000000000'),
            sourceName: null,
            sourceTypeKey: 'civil.birth',
            stale: false,
            matchedValue: 'Ковалев',
            field: 'person.surname',
            origin: SearchValueOrigin::ClaimValue,
            indexForms: ['Ковалев'],
        );

        $match = (new SearchFuzzyMatcher)->match(
            new SearchQueryTerm('Ковалёв', ['Ковалёв']),
            $candidate,
            0.8,
        );

        self::assertNotNull($match);
        self::assertEqualsWithDelta(6 / 7, $match->similarity, 0.0001);
        self::assertSame('Ковалев', $match->matchedForm);
    }

    public function test_fuzzy_never_matches_source_metadata(): void
    {
        $candidate = new DirectSearchCandidate(
            sourceId: new SourceId('00000000-0000-4000-8000-000000000000'),
            sourceName: 'Kutniewicz',
            sourceTypeKey: 'civil.birth',
            stale: false,
            matchedValue: 'Kutniewicz',
            field: 'source.name',
            origin: SearchValueOrigin::SourceMetadata,
            indexForms: ['Kutniewicz'],
        );

        self::assertNull((new SearchFuzzyMatcher)->match(
            new SearchQueryTerm('Kupniewicz', ['Kupniewicz']),
            $candidate,
            0.78,
        ));
    }
}
