<?php

declare(strict_types=1);

namespace Tests\Feature\Search;

use App\Application\Acquisition\CaptureEvidenceStateForSource;
use App\Application\Acquisition\CreateClaim;
use App\Application\Acquisition\CreateMention;
use App\Application\Acquisition\CreateSource;
use App\Application\Search\CurrentEvidenceStateLookup;
use App\Application\Search\RebuildSourceSearchDocument;
use App\Application\Search\SearchDocumentEntry;
use App\Application\Search\SearchDocumentRepository;
use App\Application\Search\SearchValueOrigin;
use App\Domain\Acquisition\MentionKind;
use App\Domain\Acquisition\PredicateKey;
use App\Domain\Acquisition\PredicateVocabulary;
use App\Domain\Acquisition\SourceId;
use App\Domain\Acquisition\SourceLinguisticRepresentation;
use App\Domain\Acquisition\SourceLinguisticRepresentationRelation;
use App\Domain\Acquisition\SourceMetadata;
use App\Domain\Acquisition\SourceType;
use App\Domain\Acquisition\TextClaimValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SearchProjectionFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_rebuild_projects_current_accepted_evidence_with_claim_and_representation_provenance(): void
    {
        $source = app(CreateSource::class)->handle(
            type: new SourceType('civil.birth'),
            metadata: new SourceMetadata(['title' => 'Birth record']),
            name: 'Birth record 1901/42',
        );
        $person = app(CreateMention::class)->handle(
            sourceId: $source->id,
            kind: new MentionKind('person'),
            localKey: 'person-1',
        );
        $claim = app(CreateClaim::class)->handle(
            sourceId: $source->id,
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

        $evidence = app(CaptureEvidenceStateForSource::class)->capture($source->id);
        $document = app(RebuildSourceSearchDocument::class)->handle($source->id);

        self::assertSame($source->id->value, $document->sourceId->value);
        self::assertSame($evidence->id->value, $document->evidenceStateId->value);
        self::assertSame($evidence->snapshot->payloadHash, $document->inputFingerprint);
        self::assertFalse($document->stale);
        self::assertNotSame('', $document->indexSignature);

        $entries = $document->entries;
        self::assertCount(4, $entries);

        self::assertSame('source.name', $entries[0]->field);
        self::assertSame('Birth record 1901/42', $entries[0]->value);
        self::assertSame(SearchValueOrigin::SourceMetadata, $entries[0]->origin);

        self::assertSame('source.metadata.title', $entries[1]->field);
        self::assertSame('Birth record', $entries[1]->value);
        self::assertSame(SearchValueOrigin::SourceMetadata, $entries[1]->origin);

        self::assertSame(PredicateKey::PersonGivenName->value, $entries[2]->field);
        self::assertSame('Peter', $entries[2]->value);
        self::assertSame(SearchValueOrigin::ClaimValue, $entries[2]->origin);
        self::assertSame($person->id->value, $entries[2]->mentionId);
        self::assertSame($claim->id->value, $entries[2]->claimId);
        self::assertNotNull($entries[2]->claimRevisionId);

        self::assertSame('Piotr', $entries[3]->value);
        self::assertSame(SearchValueOrigin::SourceRepresentation, $entries[3]->origin);
        self::assertSame('pl', $entries[3]->language);
        self::assertSame('Latn', $entries[3]->script);
        self::assertSame('language_equivalent', $entries[3]->representationRelation);

        $persisted = app(SearchDocumentRepository::class)->find($source->id);
        self::assertNotNull($persisted);
        self::assertSame($document->inputFingerprint, $persisted->inputFingerprint);
        self::assertSame(
            array_map(static fn (SearchDocumentEntry $entry): string => $entry->value, $document->entries),
            array_map(static fn (SearchDocumentEntry $entry): string => $entry->value, $persisted->entries),
        );
    }

    public function test_current_evidence_lookup_moves_to_the_latest_accepted_state_and_stale_flag_is_persisted(): void
    {
        $source = app(CreateSource::class)->handle(
            SourceType::generic(),
            new SourceMetadata(['title' => 'Search source']),
        );
        $first = app(CaptureEvidenceStateForSource::class)->capture($source->id);

        $lookup = app(CurrentEvidenceStateLookup::class);
        self::assertSame($first->id->value, $lookup->forSource($source->id)?->value);

        $document = app(RebuildSourceSearchDocument::class)->handle($source->id);
        $repository = app(SearchDocumentRepository::class);
        $repository->markStale($source->id);

        self::assertTrue($repository->find($source->id)?->stale);

        $second = app(CaptureEvidenceStateForSource::class)->capture(
            sourceId: $source->id,
            changeNote: 'new accepted evidence state',
        );

        self::assertSame($second->id->value, $lookup->forSource($source->id)?->value);
        self::assertNotSame($document->evidenceStateId->value, $second->id->value);
    }

    public function test_missing_source_has_no_current_evidence_state(): void
    {
        self::assertNull(
            app(CurrentEvidenceStateLookup::class)->forSource(
                new SourceId('11111111-1111-4111-8111-111111111111'),
            ),
        );
    }
}
