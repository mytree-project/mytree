<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Acquisition\CaptureEvidenceStateForSource;
use App\Application\Acquisition\ClaimRepository;
use App\Application\Acquisition\ClaimRevisionRepository;
use App\Application\Acquisition\CreateClaim;
use App\Application\Acquisition\CreateMention;
use App\Application\Acquisition\CreateSource;
use App\Application\Acquisition\GetEvidenceState;
use App\Application\Acquisition\UpdateClaim;
use App\Domain\Acquisition\AgeClaimValue;
use App\Domain\Acquisition\Claim;
use App\Domain\Acquisition\ClaimOriginKind;
use App\Domain\Acquisition\ClaimQualifiers;
use App\Domain\Acquisition\DateClaimValue;
use App\Domain\Acquisition\HistoricalDate;
use App\Domain\Acquisition\Mention;
use App\Domain\Acquisition\MentionKind;
use App\Domain\Acquisition\PredicateKey;
use App\Domain\Acquisition\PredicateVocabulary;
use App\Domain\Acquisition\Source;
use App\Domain\Acquisition\SourceType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AgeAtEventBirthDerivationTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_role_and_age_create_one_soft_accepted_birth_claim_with_exact_roots(): void
    {
        [$source, $person, $event, $age, $date, $role] = $this->fixture();

        $firstState = app(CaptureEvidenceStateForSource::class)->capture($source->id);
        $derived = $this->derivedFor($source);
        self::assertCount(1, $derived);

        $born = $derived[0];
        self::assertSame($person->id->value, $born->subjectMentionId->value);
        self::assertSame(PredicateKey::PersonBirthDate, $born->predicate->key);
        self::assertSame('1822-05-11', $born->value?->data()['from']);
        self::assertSame('1823-05-10', $born->value?->data()['to']);
        self::assertSame(ClaimOriginKind::Derived, $born->origin->kind);
        $lineage = $born->derivation;
        self::assertNotNull($lineage);
        self::assertSame(true, $lineage->assumptions['soft_evidence']);
        self::assertSame(false, $lineage->assumptions['hard_identity_exclusion_allowed']);
        self::assertSame('completed_years', $lineage->assumptions['age_unit_semantics']);
        self::assertSame('conditional_single_birth_instant', $lineage->assumptions['birth_value_semantics']);

        $revisions = app(ClaimRevisionRepository::class);
        $ageRoot = $revisions->latestForClaim($source->id, $age->id);
        $dateRoot = $revisions->latestForClaim($source->id, $date->id);
        $roleRoot = $revisions->latestForClaim($source->id, $role->id);
        self::assertNotNull($ageRoot);
        self::assertNotNull($dateRoot);
        self::assertNotNull($roleRoot);
        $roots = [$ageRoot->id->value, $dateRoot->id->value, $roleRoot->id->value];
        sort($roots, SORT_STRING);
        self::assertSame($roots, array_map(static fn ($id): string => $id->value, $lineage->inputClaimRevisionIds));

        self::assertCount(4, $firstState->snapshot->claimRevisionIds);
        self::assertCount(1, $revisions->forClaim($source->id, $born->id));
        app(CaptureEvidenceStateForSource::class)->capture($source->id);
        self::assertSame($born->id->value, $this->derivedFor($source)[0]->id->value);
        self::assertCount(1, $revisions->forClaim($source->id, $born->id));
    }

    public function test_compatible_direct_birth_claim_suppresses_derived_even_with_partial_overlap(): void
    {
        [$source, $person] = $this->fixture();

        $directBirth = app(CreateClaim::class)->handle(
            sourceId: $source->id,
            subjectMentionId: $person->id,
            predicate: PredicateVocabulary::get(PredicateKey::PersonBirthDate),
            value: DateClaimValue::exact('1823', HistoricalDate::year(1823)),
        );

        $state = app(CaptureEvidenceStateForSource::class)->capture($source->id);
        self::assertSame([], $this->derivedFor($source));
        self::assertCount(4, $state->snapshot->claimRevisionIds);

        app(UpdateClaim::class)->handle(
            sourceId: $source->id,
            claimId: $directBirth->id,
            subjectMentionId: $person->id,
            predicate: PredicateVocabulary::get(PredicateKey::PersonBirthDate),
            value: DateClaimValue::range(
                '1822 to 1824',
                HistoricalDate::year(1822),
                HistoricalDate::year(1824),
            ),
        );
        app(CaptureEvidenceStateForSource::class)->capture($source->id);
        self::assertSame([], $this->derivedFor($source));
    }

    public function test_contradictory_direct_birth_is_retained_and_discrepancy_is_explicit(): void
    {
        [$source, $person] = $this->fixture();

        $directBirth = app(CreateClaim::class)->handle(
            sourceId: $source->id,
            subjectMentionId: $person->id,
            predicate: PredicateVocabulary::get(PredicateKey::PersonBirthDate),
            value: DateClaimValue::exact('1820', HistoricalDate::year(1820)),
        );

        $state = app(CaptureEvidenceStateForSource::class)->capture($source->id);
        $derived = $this->derivedFor($source);
        self::assertCount(1, $derived);
        self::assertCount(5, $state->snapshot->claimRevisionIds);
        self::assertSame('1820', app(ClaimRepository::class)->find($source->id, $directBirth->id)?->value?->raw());

        $directRevision = app(ClaimRevisionRepository::class)->latestForClaim($source->id, $directBirth->id);
        self::assertNotNull($directRevision);
        self::assertSame(
            [$directRevision->id->value],
            $derived[0]->derivation?->assumptions['direct_birth_discrepancy_revision_ids'],
        );
    }

    public function test_new_direct_birth_suppresses_previous_derived_without_rewriting_history(): void
    {
        [$source, $person] = $this->fixture();
        $historic = app(CaptureEvidenceStateForSource::class)->capture($source->id);
        self::assertCount(1, $this->derivedFor($source));

        app(CreateClaim::class)->handle(
            sourceId: $source->id,
            subjectMentionId: $person->id,
            predicate: PredicateVocabulary::get(PredicateKey::PersonBirthDate),
            value: DateClaimValue::exact('1823', HistoricalDate::year(1823)),
        );

        $current = app(CaptureEvidenceStateForSource::class)->capture($source->id);
        self::assertSame([], $this->derivedFor($source));
        self::assertCount(4, $current->snapshot->claimRevisionIds);
        self::assertCount(4, app(GetEvidenceState::class)->get($historic->id)->claimRevisions);
    }

    public function test_input_change_advances_derived_revision_and_old_evidence_is_immutable(): void
    {
        [$source, $person, $event, $age] = $this->fixture();
        $historical = app(CaptureEvidenceStateForSource::class)->capture($source->id);
        $derived = $this->derivedFor($source)[0];

        app(UpdateClaim::class)->handle(
            sourceId: $source->id,
            claimId: $age->id,
            subjectMentionId: $person->id,
            predicate: PredicateVocabulary::get(PredicateKey::PersonAge),
            value: AgeClaimValue::exact('41 years', 41),
        );

        $next = app(CaptureEvidenceStateForSource::class)->capture($source->id);
        $updated = $this->derivedFor($source)[0];
        self::assertSame($derived->id->value, $updated->id->value);
        self::assertSame('1821-05-11', $updated->value?->data()['from']);
        self::assertSame('1822-05-10', $updated->value?->data()['to']);
        self::assertCount(2, app(ClaimRevisionRepository::class)->forClaim($source->id, $updated->id));
        self::assertCount(4, $next->snapshot->claimRevisionIds);

        $old = app(GetEvidenceState::class)->get($historical->id);
        $oldDerived = array_values(array_filter(
            $old->claimRevisions,
            static fn ($revision): bool => $revision->reconstruct()->claim->origin->kind === ClaimOriginKind::Derived,
        ));
        self::assertCount(1, $oldDerived);
        self::assertSame('1822-05-11', $oldDerived[0]->reconstruct()->claim->value?->data()['from']);
    }

    public function test_ambiguous_event_context_and_approximate_ages_never_create_false_birth_dates(): void
    {
        [$source, $person] = $this->fixture(AgeClaimValue::approximate('about 40 years', 40));
        app(CaptureEvidenceStateForSource::class)->capture($source->id);
        self::assertSame([], $this->derivedFor($source));

        [$otherSource, $otherPerson] = $this->fixture();
        $secondEvent = app(CreateMention::class)->handle(
            $otherSource->id,
            MentionKind::event(),
            'second-event',
        );
        app(CreateClaim::class)->handle(
            sourceId: $otherSource->id,
            subjectMentionId: $secondEvent->id,
            predicate: PredicateVocabulary::get(PredicateKey::EventDate),
            value: DateClaimValue::exact('1870', HistoricalDate::year(1870)),
        );
        app(CreateClaim::class)->handle(
            sourceId: $otherSource->id,
            subjectMentionId: $secondEvent->id,
            predicate: PredicateVocabulary::get(PredicateKey::EventWitness),
            objectMentionId: $otherPerson->id,
        );

        app(CaptureEvidenceStateForSource::class)->capture($otherSource->id);
        self::assertSame([], $this->derivedFor($otherSource));
    }

    public function test_explicit_age_effective_time_resolves_two_dated_events(): void
    {
        [$source, $person, $event, $age] = $this->fixture(
            ageValue: AgeClaimValue::exact('40 years', 40),
            qualifiers: new ClaimQualifiers(
                effectiveTime: DateClaimValue::exact('10 May 1863', HistoricalDate::day(1863, 5, 10)),
            ),
        );

        $secondEvent = app(CreateMention::class)->handle($source->id, MentionKind::event(), 'later-event');
        app(CreateClaim::class)->handle(
            sourceId: $source->id,
            subjectMentionId: $secondEvent->id,
            predicate: PredicateVocabulary::get(PredicateKey::EventDate),
            value: DateClaimValue::exact('3 June 1870', HistoricalDate::day(1870, 6, 3)),
        );
        app(CreateClaim::class)->handle(
            sourceId: $source->id,
            subjectMentionId: $secondEvent->id,
            predicate: PredicateVocabulary::get(PredicateKey::EventParticipant),
            objectMentionId: $person->id,
        );

        app(CaptureEvidenceStateForSource::class)->capture($source->id);
        self::assertCount(1, $this->derivedFor($source));
        self::assertSame('1822-05-11', $this->derivedFor($source)[0]->value?->data()['from']);
    }

    /**
     * @return array{Source, Mention, Mention, Claim, Claim, Claim}
     */
    private function fixture(
        ?AgeClaimValue $ageValue = null,
        ?ClaimQualifiers $qualifiers = null,
    ): array {
        $source = app(CreateSource::class)->handle(SourceType::generic());
        $person = app(CreateMention::class)->handle(
            sourceId: $source->id,
            kind: MentionKind::person(),
            localKey: 'person-john',
        );
        $event = app(CreateMention::class)->handle(
            sourceId: $source->id,
            kind: MentionKind::event(),
            localKey: 'event-death',
        );
        $age = app(CreateClaim::class)->handle(
            sourceId: $source->id,
            subjectMentionId: $person->id,
            predicate: PredicateVocabulary::get(PredicateKey::PersonAge),
            value: $ageValue ?? AgeClaimValue::exact('40 years', 40),
            qualifiers: $qualifiers,
        );
        $date = app(CreateClaim::class)->handle(
            sourceId: $source->id,
            subjectMentionId: $event->id,
            predicate: PredicateVocabulary::get(PredicateKey::EventDate),
            value: DateClaimValue::exact('10 May 1863', HistoricalDate::day(1863, 5, 10)),
        );
        $role = app(CreateClaim::class)->handle(
            sourceId: $source->id,
            subjectMentionId: $event->id,
            predicate: PredicateVocabulary::get(PredicateKey::EventParticipant),
            objectMentionId: $person->id,
        );

        return [$source, $person, $event, $age, $date, $role];
    }

    /** @return list<Claim> */
    private function derivedFor(Source $source): array
    {
        return array_values(array_filter(
            app(ClaimRepository::class)->forSource($source->id),
            static fn (Claim $claim): bool => $claim->origin->kind === ClaimOriginKind::Derived,
        ));
    }
}
