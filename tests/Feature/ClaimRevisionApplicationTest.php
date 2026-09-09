<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Acquisition\CreateClaim;
use App\Application\Acquisition\CreateMention;
use App\Application\Acquisition\CreateSource;
use App\Application\Acquisition\CreateSourceLocator;
use App\Application\Acquisition\GetClaimRevision;
use App\Application\Acquisition\ListClaimRevisions;
use App\Application\Acquisition\ListMentionRevisions;
use App\Application\Acquisition\ListSourceClaimRevisions;
use App\Application\Acquisition\RemoveClaim;
use App\Application\Acquisition\RemoveSourceLocator;
use App\Application\Acquisition\UpdateClaim;
use App\Application\Acquisition\UpdateMention;
use App\Domain\Acquisition\ClaimCertainty;
use App\Domain\Acquisition\ClaimOrigin;
use App\Domain\Acquisition\ClaimOriginKind;
use App\Domain\Acquisition\ClaimQualifiers;
use App\Domain\Acquisition\ClaimRevision;
use App\Domain\Acquisition\DateClaimValue;
use App\Domain\Acquisition\HistoricalDate;
use App\Domain\Acquisition\MentionKind;
use App\Domain\Acquisition\PdfPageLocatorValue;
use App\Domain\Acquisition\PredicateKey;
use App\Domain\Acquisition\PredicateVocabulary;
use App\Domain\Acquisition\SourceType;
use App\Domain\Acquisition\TextClaimValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ClaimRevisionApplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_creation_records_rich_initial_revision_with_exact_subject_mention_revision(): void
    {
        $source = app(CreateSource::class)->handle(SourceType::generic());
        $person = app(CreateMention::class)->handle($source->id, MentionKind::person(), 'person.subject');
        $mentionRevision = app(ListMentionRevisions::class)->handle($source->id, $person->id)[0];

        $claim = app(CreateClaim::class)->handle(
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
            origin: new ClaimOrigin(
                kind: ClaimOriginKind::ProviderObservation,
                providerKey: 'provider-a',
                providerRecordId: 'A-17',
                sourceUrl: 'https://example.test/index',
            ),
            transcriptionCertainty: new ClaimCertainty('certain'),
            interpretationCertainty: new ClaimCertainty('probable'),
            changeNote: 'Initial indexed assertion',
            changedBy: 'provider:test',
        );

        $revisions = app(ListClaimRevisions::class)->handle($source->id, $claim->id);
        $state = $revisions[0]->reconstruct();

        self::assertCount(1, $revisions);
        self::assertSame(1, $revisions[0]->revisionNumber);
        self::assertSame('Initial indexed assertion', $revisions[0]->changeNote);
        self::assertSame('provider:test', $revisions[0]->changedBy);
        self::assertSame($mentionRevision->id->value, $state->subjectMentionRevisionId->value);
        self::assertSame('włościan', $state->claim->value?->raw());
        self::assertSame('range', $state->claim->qualifiers->effectiveTime?->kind->value);
        self::assertSame('provider-a', $state->claim->origin->providerKey);
        self::assertSame('certain', $state->claim->transcriptionCertainty->code);
        self::assertSame('probable', $state->claim->interpretationCertainty->code);

        $this->assertDatabaseHas('claim_revisions', [
            'id' => $revisions[0]->id->value,
            'source_id' => $source->id->value,
            'claim_id' => $claim->id->value,
            'revision_number' => 1,
            'subject_mention_revision_id' => $mentionRevision->id->value,
            'snapshot_schema_version' => 1,
        ]);
    }

    public function test_semantic_update_appends_revision_and_no_op_update_does_not(): void
    {
        $source = app(CreateSource::class)->handle(SourceType::generic());
        $person = app(CreateMention::class)->handle($source->id, MentionKind::person(), 'person.subject');
        $predicate = PredicateVocabulary::get(PredicateKey::PersonGivenName);
        $claim = app(CreateClaim::class)->handle(
            sourceId: $source->id,
            subjectMentionId: $person->id,
            predicate: $predicate,
            value: new TextClaimValue('Jan'),
        );

        app(UpdateClaim::class)->handle(
            sourceId: $source->id,
            claimId: $claim->id,
            subjectMentionId: $person->id,
            predicate: $predicate,
            value: new TextClaimValue('Jan Józef'),
            changeNote: 'Expanded given name',
        );
        app(UpdateClaim::class)->handle(
            sourceId: $source->id,
            claimId: $claim->id,
            subjectMentionId: $person->id,
            predicate: $predicate,
            value: new TextClaimValue('Jan Józef'),
            changeNote: 'No-op must not create history',
        );

        $revisions = app(ListClaimRevisions::class)->handle($source->id, $claim->id);

        self::assertCount(2, $revisions);
        self::assertSame([1, 2], array_map(
            static fn (ClaimRevision $revision): int => $revision->revisionNumber,
            $revisions,
        ));
        self::assertSame('Jan', $revisions[0]->reconstruct()->claim->value?->raw());
        self::assertSame('Jan Józef', $revisions[1]->reconstruct()->claim->value?->raw());
        self::assertSame('Expanded given name', $revisions[1]->changeNote);
    }

    public function test_mention_object_revision_refs_remain_historical_after_mentions_change(): void
    {
        $source = app(CreateSource::class)->handle(SourceType::generic());
        $person = app(CreateMention::class)->handle(
            $source->id,
            MentionKind::person(),
            'person.subject',
            displayLabel: 'Jan Kowalski',
        );
        $place = app(CreateMention::class)->handle(
            $source->id,
            MentionKind::place(),
            'place.birth',
            displayLabel: 'Kraków',
        );
        $subjectRevision = app(ListMentionRevisions::class)->handle($source->id, $person->id)[0];
        $objectRevision = app(ListMentionRevisions::class)->handle($source->id, $place->id)[0];
        $claim = app(CreateClaim::class)->handle(
            sourceId: $source->id,
            subjectMentionId: $person->id,
            predicate: PredicateVocabulary::get(PredicateKey::PersonBirthPlace),
            objectMentionId: $place->id,
        );

        app(UpdateMention::class)->handle(
            sourceId: $source->id,
            mentionId: $person->id,
            kind: MentionKind::person(),
            displayLabel: 'Jan Józef Kowalski',
        );
        app(UpdateMention::class)->handle(
            sourceId: $source->id,
            mentionId: $place->id,
            kind: MentionKind::place(),
            displayLabel: 'Kraków, Polska',
        );

        $state = app(GetClaimRevision::class)->handle($source->id, $claim->id, 1)->reconstruct();
        $currentMentionRevisions = app(ListMentionRevisions::class)->handle($source->id, $person->id);

        if ($currentMentionRevisions === []) {
            self::fail('Expected current Mention revision history.');
        }

        $latestMentionRevision = $currentMentionRevisions[count($currentMentionRevisions) - 1];

        self::assertSame($subjectRevision->id->value, $state->subjectMentionRevisionId->value);
        self::assertSame($objectRevision->id->value, $state->objectMentionRevisionId?->value);
        self::assertNotSame(
            $state->subjectMentionRevisionId->value,
            $latestMentionRevision->id->value,
        );
    }

    public function test_locator_add_and_remove_append_claim_revisions_with_exact_locator_state(): void
    {
        $source = app(CreateSource::class)->handle(SourceType::generic());
        $person = app(CreateMention::class)->handle($source->id, MentionKind::person(), 'person.subject');
        $claim = app(CreateClaim::class)->handle(
            sourceId: $source->id,
            subjectMentionId: $person->id,
            predicate: PredicateVocabulary::get(PredicateKey::PersonSurname),
            value: new TextClaimValue('Kowalski'),
        );
        $locator = app(CreateSourceLocator::class)->handle(
            $source->id,
            $claim->id,
            new PdfPageLocatorValue(7),
            changeNote: 'Attach exact page',
        );
        app(RemoveSourceLocator::class)->handle(
            $source->id,
            $claim->id,
            $locator->id,
            changeNote: 'Locator was incorrect',
        );

        $revisions = app(ListClaimRevisions::class)->handle($source->id, $claim->id);

        self::assertCount(3, $revisions);
        self::assertCount(0, $revisions[0]->reconstruct()->sourceLocators);
        self::assertCount(1, $revisions[1]->reconstruct()->sourceLocators);
        self::assertSame('pdf_page', $revisions[1]->reconstruct()->sourceLocators[0]->value->type()->value);
        self::assertCount(0, $revisions[2]->reconstruct()->sourceLocators);
        self::assertSame('Attach exact page', $revisions[1]->changeNote);
        self::assertSame('Locator was incorrect', $revisions[2]->changeNote);
    }

    public function test_history_remains_readable_after_current_claim_is_removed(): void
    {
        $source = app(CreateSource::class)->handle(SourceType::generic());
        $person = app(CreateMention::class)->handle($source->id, MentionKind::person(), 'person.subject');
        $predicate = PredicateVocabulary::get(PredicateKey::PersonSurname);
        $claim = app(CreateClaim::class)->handle(
            sourceId: $source->id,
            subjectMentionId: $person->id,
            predicate: $predicate,
            value: new TextClaimValue('Gajda'),
        );
        app(UpdateClaim::class)->handle(
            sourceId: $source->id,
            claimId: $claim->id,
            subjectMentionId: $person->id,
            predicate: $predicate,
            value: new TextClaimValue('Gayda'),
        );

        app(RemoveClaim::class)->handle($source->id, $claim->id);

        $revisions = app(ListClaimRevisions::class)->handle($source->id, $claim->id);
        $first = app(GetClaimRevision::class)->handle($source->id, $claim->id, 1);
        $sourceHistory = app(ListSourceClaimRevisions::class)->handle($source->id);

        self::assertCount(2, $revisions);
        self::assertCount(2, $sourceHistory);
        self::assertSame('Gajda', $first->reconstruct()->claim->value?->raw());
        self::assertSame('Gayda', $revisions[1]->reconstruct()->claim->value?->raw());
        $this->assertDatabaseMissing('claims', ['id' => $claim->id->value]);
        $this->assertDatabaseCount('claim_revisions', 2);
    }
}
