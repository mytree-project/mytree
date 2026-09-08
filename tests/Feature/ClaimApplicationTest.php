<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Acquisition\CreateClaim;
use App\Application\Acquisition\CreateMention;
use App\Application\Acquisition\CreateSource;
use App\Application\Acquisition\CreateSourceLocator;
use App\Application\Acquisition\GetClaim;
use App\Application\Acquisition\ListClaimSourceLocators;
use App\Application\Acquisition\ListSourceClaims;
use App\Application\Acquisition\MentionNotFound;
use App\Application\Acquisition\RemoveClaim;
use App\Application\Acquisition\UpdateClaim;
use App\Domain\Acquisition\ClaimCertainty;
use App\Domain\Acquisition\ClaimOrigin;
use App\Domain\Acquisition\ClaimOriginKind;
use App\Domain\Acquisition\ClaimQualifiers;
use App\Domain\Acquisition\DateClaimValue;
use App\Domain\Acquisition\HistoricalDate;
use App\Domain\Acquisition\ImageBoundingBoxLocatorValue;
use App\Domain\Acquisition\MediaTimestampLocatorValue;
use App\Domain\Acquisition\MentionKind;
use App\Domain\Acquisition\PdfPageLocatorValue;
use App\Domain\Acquisition\PredicateKey;
use App\Domain\Acquisition\PredicateVocabulary;
use App\Domain\Acquisition\QuotedFragmentLocatorValue;
use App\Domain\Acquisition\SourceType;
use App\Domain\Acquisition\TextClaimValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ClaimApplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_literal_claim_preserves_temporal_qualifier_provenance_and_certainties(): void
    {
        $source = app(CreateSource::class)->handle(SourceType::generic());
        $person = app(CreateMention::class)->handle($source->id, MentionKind::person(), 'person.subject');
        $place = app(CreateMention::class)->handle($source->id, MentionKind::place(), 'place.work');

        $created = app(CreateClaim::class)->handle(
            sourceId: $source->id,
            subjectMentionId: $person->id,
            predicate: PredicateVocabulary::get(PredicateKey::PersonOccupation),
            value: new TextClaimValue('włościan'),
            qualifiers: new ClaimQualifiers(
                effectiveTime: DateClaimValue::range(
                    '1890-1895',
                    HistoricalDate::year(1890),
                    HistoricalDate::year(1895),
                ),
            ),
            rawText: 'Jan Kowalski, włościan ze wsi X',
            origin: ClaimOrigin::manualDirectSource(),
            transcriptionCertainty: new ClaimCertainty('certain'),
            interpretationCertainty: new ClaimCertainty('probable'),
        );

        app(CreateClaim::class)->handle(
            sourceId: $source->id,
            subjectMentionId: $person->id,
            predicate: PredicateVocabulary::get(PredicateKey::PersonWorkPlace),
            objectMentionId: $place->id,
        );

        $loaded = app(GetClaim::class)->handle($source->id, $created->id);
        $claims = app(ListSourceClaims::class)->handle($source->id);

        self::assertSame('person.occupation', $loaded->predicate->key->value);
        self::assertInstanceOf(TextClaimValue::class, $loaded->value);
        self::assertSame('włościan', $loaded->value->rawValue);
        self::assertSame('range', $loaded->qualifiers->effectiveTime?->kind->value);
        self::assertSame('1890-1895', $loaded->qualifiers->effectiveTime?->rawValue);
        self::assertSame('manual_direct_source', $loaded->origin->kind->value);
        self::assertSame('certain', $loaded->transcriptionCertainty->code);
        self::assertSame('probable', $loaded->interpretationCertainty->code);
        self::assertSame('Jan Kowalski, włościan ze wsi X', $loaded->rawText);
        self::assertContains('person.work_place', array_map(
            static fn ($claim): string => $claim->predicate->key->value,
            $claims,
        ));
    }

    public function test_reified_migration_event_persists_as_atomic_source_local_claim_graph(): void
    {
        $source = app(CreateSource::class)->handle(SourceType::generic());
        $migration = app(CreateMention::class)->handle($source->id, MentionKind::event(), 'event.migration');
        $jan = app(CreateMention::class)->handle($source->id, MentionKind::person(), 'person.jan');
        $anna = app(CreateMention::class)->handle($source->id, MentionKind::person(), 'person.anna');
        $origin = app(CreateMention::class)->handle($source->id, MentionKind::place(), 'place.origin');
        $destination = app(CreateMention::class)->handle($source->id, MentionKind::place(), 'place.destination');

        foreach ([$jan->id, $anna->id] as $participantId) {
            app(CreateClaim::class)->handle(
                sourceId: $source->id,
                subjectMentionId: $migration->id,
                predicate: PredicateVocabulary::get(PredicateKey::EventParticipant),
                objectMentionId: $participantId,
            );
        }

        app(CreateClaim::class)->handle(
            sourceId: $source->id,
            subjectMentionId: $migration->id,
            predicate: PredicateVocabulary::get(PredicateKey::EventOriginPlace),
            objectMentionId: $origin->id,
        );
        app(CreateClaim::class)->handle(
            sourceId: $source->id,
            subjectMentionId: $migration->id,
            predicate: PredicateVocabulary::get(PredicateKey::EventDestinationPlace),
            objectMentionId: $destination->id,
        );
        app(CreateClaim::class)->handle(
            sourceId: $source->id,
            subjectMentionId: $migration->id,
            predicate: PredicateVocabulary::get(PredicateKey::EventDate),
            value: DateClaimValue::exact('1912', HistoricalDate::year(1912)),
        );
        app(CreateClaim::class)->handle(
            sourceId: $source->id,
            subjectMentionId: $migration->id,
            predicate: PredicateVocabulary::get(PredicateKey::EventReason),
            value: new TextClaimValue('praca'),
        );

        $eventClaims = array_values(array_filter(
            app(ListSourceClaims::class)->handle($source->id),
            static fn ($claim): bool => $claim->subjectMentionId->value === $migration->id->value,
        ));

        self::assertCount(6, $eventClaims);
        self::assertEqualsCanonicalizing(
            [
                'event.participant',
                'event.participant',
                'event.origin_place',
                'event.destination_place',
                'event.date',
                'event.reason',
            ],
            array_map(static fn ($claim): string => $claim->predicate->key->value, $eventClaims),
        );
    }

    public function test_mention_to_mention_claim_cannot_cross_source_boundary(): void
    {
        $left = app(CreateSource::class)->handle(SourceType::generic());
        $right = app(CreateSource::class)->handle(SourceType::generic());
        $subject = app(CreateMention::class)->handle($left->id, MentionKind::person(), 'person.subject');
        $foreignObject = app(CreateMention::class)->handle($right->id, MentionKind::person(), 'person.spouse');

        $this->expectException(MentionNotFound::class);

        app(CreateClaim::class)->handle(
            sourceId: $left->id,
            subjectMentionId: $subject->id,
            predicate: PredicateVocabulary::get(PredicateKey::PersonSpouse),
            objectMentionId: $foreignObject->id,
        );
    }

    public function test_conflicting_provider_observations_remain_independent_claims(): void
    {
        $source = app(CreateSource::class)->handle(SourceType::generic());
        $person = app(CreateMention::class)->handle($source->id, MentionKind::person(), 'person.subject');
        $predicate = PredicateVocabulary::get(PredicateKey::PersonGivenName);

        foreach ([['provider-a', 'A-17', 'Jan'], ['provider-b', 'B-99', 'Iwan']] as [$provider, $record, $value]) {
            app(CreateClaim::class)->handle(
                sourceId: $source->id,
                subjectMentionId: $person->id,
                predicate: $predicate,
                value: new TextClaimValue($value),
                origin: new ClaimOrigin(
                    kind: ClaimOriginKind::ProviderObservation,
                    providerKey: $provider,
                    providerRecordId: $record,
                    sourceUrl: 'https://example.test/index',
                    requestContext: ['query' => 'kowalski'],
                    parserVersion: '1.0.0',
                ),
            );
        }

        $claims = app(ListSourceClaims::class)->handle($source->id);

        self::assertCount(2, $claims);
        self::assertEqualsCanonicalizing(['provider-a', 'provider-b'], array_map(
            static fn ($claim): ?string => $claim->origin->providerKey,
            $claims,
        ));
    }

    public function test_source_locators_round_trip_for_supported_locator_forms(): void
    {
        $source = app(CreateSource::class)->handle(SourceType::generic());
        $person = app(CreateMention::class)->handle($source->id, MentionKind::person(), 'person.subject');
        $claim = app(CreateClaim::class)->handle(
            sourceId: $source->id,
            subjectMentionId: $person->id,
            predicate: PredicateVocabulary::get(PredicateKey::PersonSurname),
            value: new TextClaimValue('Kowalski'),
        );

        $values = [
            new PdfPageLocatorValue(7),
            new ImageBoundingBoxLocatorValue(15, 25, 240, 60),
            new MediaTimestampLocatorValue(1000, 2500),
            new QuotedFragmentLocatorValue('Kowalski'),
        ];

        foreach ($values as $value) {
            app(CreateSourceLocator::class)->handle($source->id, $claim->id, $value);
        }

        $locators = app(ListClaimSourceLocators::class)->handle($source->id, $claim->id);

        self::assertCount(4, $locators);
        self::assertEqualsCanonicalizing(
            ['pdf_page', 'image_bounding_box', 'media_timestamp', 'quoted_fragment'],
            array_map(static fn ($locator): string => $locator->value->type()->value, $locators),
        );
    }

    public function test_claim_can_be_updated_and_removed_without_changing_source_ownership(): void
    {
        $source = app(CreateSource::class)->handle(SourceType::generic());
        $person = app(CreateMention::class)->handle($source->id, MentionKind::person(), 'person.subject');
        $claim = app(CreateClaim::class)->handle(
            sourceId: $source->id,
            subjectMentionId: $person->id,
            predicate: PredicateVocabulary::get(PredicateKey::PersonGivenName),
            value: new TextClaimValue('Jan'),
        );

        $updated = app(UpdateClaim::class)->handle(
            sourceId: $source->id,
            claimId: $claim->id,
            subjectMentionId: $person->id,
            predicate: PredicateVocabulary::get(PredicateKey::PersonGivenName),
            value: new TextClaimValue('Jan Józef'),
        );

        self::assertSame($source->id->value, $updated->sourceId->value);
        self::assertSame($claim->id->value, $updated->id->value);
        self::assertSame('Jan Józef', $updated->value?->raw());

        app(RemoveClaim::class)->handle($source->id, $claim->id);
        self::assertSame([], app(ListSourceClaims::class)->handle($source->id));
    }
}
