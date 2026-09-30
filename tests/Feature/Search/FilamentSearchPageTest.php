<?php

declare(strict_types=1);

namespace Tests\Feature\Search;

use App\Application\Acquisition\CaptureEvidenceStateForSource;
use App\Application\Acquisition\CreateClaim;
use App\Application\Acquisition\CreateMention;
use App\Application\Acquisition\CreateSource;
use App\Application\Search\RebuildSourceSearchDocument;
use App\Application\Search\SearchDocumentRepository;
use App\Domain\Acquisition\MentionKind;
use App\Domain\Acquisition\PredicateKey;
use App\Domain\Acquisition\PredicateVocabulary;
use App\Domain\Acquisition\Source;
use App\Domain\Acquisition\SourceMetadata;
use App\Domain\Acquisition\SourceType;
use App\Domain\Acquisition\TextClaimValue;
use App\Infrastructure\Persistence\Eloquent\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class FilamentSearchPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_search_sources_and_see_match_explanation(): void
    {
        $administrator = User::factory()->admin()->create();
        $source = $this->indexedSource();
        $sourceName = $source->name;
        self::assertNotNull($sourceName);

        $this->actingAs($administrator)
            ->get('/admin/search?q=Peter')
            ->assertOk()
            ->assertSee($sourceName)
            ->assertSee('Peter')
            ->assertSee(__('search.match_types.direct_claim_value'))
            ->assertSee(__('search.fields.person_given_name'))
            ->assertSee($source->id->value);
    }

    public function test_search_result_signals_stale_projection(): void
    {
        $administrator = User::factory()->admin()->create();
        $source = $this->indexedSource();
        $sourceName = $source->name;
        self::assertNotNull($sourceName);
        app(SearchDocumentRepository::class)->markStale($source->id);

        $this->actingAs($administrator)
            ->get('/admin/search?q=Peter')
            ->assertOk()
            ->assertSee($sourceName)
            ->assertSee(__('search.stale'));
    }

    private function indexedSource(): Source
    {
        $source = app(CreateSource::class)->handle(
            new SourceType('civil.birth'),
            new SourceMetadata(['title' => 'Birth record']),
            name: 'Birth record 1904/42',
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
            value: new TextClaimValue('Peter'),
        );
        app(CaptureEvidenceStateForSource::class)->capture($source->id);
        app(RebuildSourceSearchDocument::class)->handle($source->id);

        return $source;
    }
}
