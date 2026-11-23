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
use App\Domain\Acquisition\Claim;
use App\Domain\Acquisition\ClaimOriginKind;
use App\Domain\Acquisition\EnumClaimValue;
use App\Domain\Acquisition\EventTypeClaimValueKey;
use App\Domain\Acquisition\Mention;
use App\Domain\Acquisition\MentionKind;
use App\Domain\Acquisition\PredicateKey;
use App\Domain\Acquisition\PredicateVocabulary;
use App\Domain\Acquisition\Source;
use App\Domain\Acquisition\SourceType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class BirthEventParentDerivationTest extends TestCase
{
    use RefreshDatabase;

    public function test_explicit_birth_event_derives_one_canonical_child_to_each_parent_edge(): void
    {
        $f = $this->fixture();

        $state = app(CaptureEvidenceStateForSource::class)->capture($f['source']->id);
        $parents = $this->derivedParents($f['source']);

        self::assertCount(2, $parents);
        self::assertCount(6, $state->snapshot->claimRevisionIds);
        $expectedParents = [$f['father']->id->value, $f['mother']->id->value];
        sort($expectedParents, SORT_STRING);
        self::assertSame($expectedParents, $this->parentIdsOf($parents, $f['child']));

        $revisions = app(ClaimRevisionRepository::class);
        $typeRevision = $revisions->latestForClaim($f['source']->id, $f['typeClaim']->id);
        $childRevision = $revisions->latestForClaim($f['source']->id, $f['childRole']->id);
        $fatherRevision = $revisions->latestForClaim($f['source']->id, $f['fatherRole']->id);
        self::assertNotNull($typeRevision);
        self::assertNotNull($childRevision);
        self::assertNotNull($fatherRevision);

        $fatherDerived = $this->withParent($parents, $f['father']);
        $derivation = $fatherDerived->derivation;
        self::assertNotNull($derivation);
        $expectedRoots = [$typeRevision->id->value, $childRevision->id->value, $fatherRevision->id->value];
        sort($expectedRoots, SORT_STRING);
        self::assertSame(
            $expectedRoots,
            array_map(static fn ($id): string => $id->value, $derivation->inputClaimRevisionIds),
        );
        self::assertCount(3, $derivation->inputMentionRevisionIds);
        self::assertSame('child_to_parent', $derivation->assumptions['relation_direction']);
        self::assertSame('birth', $derivation->assumptions['event_type']);
        self::assertSame(PredicateKey::PersonParent, $fatherDerived->predicate->key);
        self::assertSame($f['child']->id->value, $fatherDerived->subjectMentionId->value);

        // No independent inverse, spouse, or extra view-only Claim is stored.
        $all = app(ClaimRepository::class)->forSource($f['source']->id);
        self::assertSame(2, count(array_filter(
            $all,
            static fn (Claim $claim): bool => $claim->origin->kind === ClaimOriginKind::Derived,
        )));

        app(CaptureEvidenceStateForSource::class)->capture($f['source']->id);
        self::assertCount(2, $this->derivedParents($f['source']));
        self::assertCount(1, $revisions->forClaim($f['source']->id, $fatherDerived->id));
    }

    public function test_direct_same_parent_suppresses_only_that_pair(): void
    {
        $f = $this->fixture();

        app(CreateClaim::class)->handle(
            sourceId: $f['source']->id,
            subjectMentionId: $f['child']->id,
            predicate: PredicateVocabulary::get(PredicateKey::PersonParent),
            objectMentionId: $f['father']->id,
        );

        app(CaptureEvidenceStateForSource::class)->capture($f['source']->id);
        $derived = $this->derivedParents($f['source']);
        self::assertCount(1, $derived);
        self::assertSame($f['mother']->id->value, $derived[0]->objectMentionId?->value);

        // Two parents are compatible. Distinct parent identities do not prove
        // a contradiction and must not suppress the second relation.
        $extra = app(CreateMention::class)->handle($f['source']->id, MentionKind::person(), 'other-parent');
        app(CreateClaim::class)->handle(
            sourceId: $f['source']->id,
            subjectMentionId: $f['child']->id,
            predicate: PredicateVocabulary::get(PredicateKey::PersonParent),
            objectMentionId: $extra->id,
        );
        app(CaptureEvidenceStateForSource::class)->capture($f['source']->id);
        self::assertCount(1, $this->derivedParents($f['source']));
    }

    public function test_new_direct_parent_retires_redundant_derived_without_destroying_history(): void
    {
        $f = $this->fixture();
        $before = app(CaptureEvidenceStateForSource::class)->capture($f['source']->id);
        self::assertCount(2, $this->derivedParents($f['source']));

        app(CreateClaim::class)->handle(
            sourceId: $f['source']->id,
            subjectMentionId: $f['child']->id,
            predicate: PredicateVocabulary::get(PredicateKey::PersonParent),
            objectMentionId: $f['father']->id,
        );

        $after = app(CaptureEvidenceStateForSource::class)->capture($f['source']->id);
        self::assertCount(1, $this->derivedParents($f['source']));
        self::assertCount(6, $after->snapshot->claimRevisionIds);
        self::assertCount(6, app(GetEvidenceState::class)->get($before->id)->claimRevisions);
    }

    public function test_untyped_or_baptism_event_never_derives_parent_relation_even_in_birth_source(): void
    {
        $untyped = $this->fixture(null, new SourceType('civil.birth'));
        app(CaptureEvidenceStateForSource::class)->capture($untyped['source']->id);
        self::assertSame([], $this->derivedParents($untyped['source']));

        $baptism = $this->fixture(EventTypeClaimValueKey::Baptism->value);
        app(CaptureEvidenceStateForSource::class)->capture($baptism['source']->id);
        self::assertSame([], $this->derivedParents($baptism['source']));

        $generic = $this->fixture();
        app(CaptureEvidenceStateForSource::class)->capture($generic['source']->id);
        self::assertCount(2, $this->derivedParents($generic['source']));
    }

    public function test_ambiguous_birth_roles_are_not_turned_into_family_relations(): void
    {
        $multipleChildren = $this->fixture();
        $secondChild = app(CreateMention::class)->handle(
            $multipleChildren['source']->id,
            MentionKind::person(),
            'second-child',
        );
        app(CreateClaim::class)->handle(
            sourceId: $multipleChildren['source']->id,
            subjectMentionId: $multipleChildren['event']->id,
            predicate: PredicateVocabulary::get(PredicateKey::EventChild),
            objectMentionId: $secondChild->id,
        );
        app(CaptureEvidenceStateForSource::class)->capture($multipleChildren['source']->id);
        self::assertSame([], $this->derivedParents($multipleChildren['source']));

        $manyParents = $this->fixture();
        $third = app(CreateMention::class)->handle($manyParents['source']->id, MentionKind::person(), 'third-parent');
        app(CreateClaim::class)->handle(
            sourceId: $manyParents['source']->id,
            subjectMentionId: $manyParents['event']->id,
            predicate: PredicateVocabulary::get(PredicateKey::EventParent),
            objectMentionId: $third->id,
        );
        app(CaptureEvidenceStateForSource::class)->capture($manyParents['source']->id);
        self::assertSame([], $this->derivedParents($manyParents['source']));

        $contradictoryTypes = $this->fixture();
        app(CreateClaim::class)->handle(
            sourceId: $contradictoryTypes['source']->id,
            subjectMentionId: $contradictoryTypes['event']->id,
            predicate: PredicateVocabulary::get(PredicateKey::EventType),
            value: new EnumClaimValue('chrzest', EventTypeClaimValueKey::Baptism->value),
        );
        app(CaptureEvidenceStateForSource::class)->capture($contradictoryTypes['source']->id);
        self::assertSame([], $this->derivedParents($contradictoryTypes['source']));
    }

    public function test_repeated_agreeing_event_type_claims_still_support_one_relation_per_parent(): void
    {
        $f = $this->fixture();
        $secondType = app(CreateClaim::class)->handle(
            sourceId: $f['source']->id,
            subjectMentionId: $f['event']->id,
            predicate: PredicateVocabulary::get(PredicateKey::EventType),
            value: new EnumClaimValue('narodziny', EventTypeClaimValueKey::Birth->value),
        );

        app(CaptureEvidenceStateForSource::class)->capture($f['source']->id);
        $parents = $this->derivedParents($f['source']);
        self::assertCount(2, $parents);

        $father = $this->withParent($parents, $f['father']);
        self::assertCount(4, $father->derivation?->inputClaimRevisionIds);
        $secondTypeRevision = app(ClaimRevisionRepository::class)
            ->latestForClaim($f['source']->id, $secondType->id);
        self::assertNotNull($secondTypeRevision);
        self::assertContains(
            $secondTypeRevision->id->value,
            array_map(
                static fn ($id): string => $id->value,
                $father->derivation?->inputClaimRevisionIds ?? [],
            ),
        );
    }

    public function test_changed_event_type_with_same_meaning_advances_derived_lineage(): void
    {
        $f = $this->fixture();
        $historical = app(CaptureEvidenceStateForSource::class)->capture($f['source']->id);
        $oldFather = $this->withParent($this->derivedParents($f['source']), $f['father']);
        $oldRevision = app(ClaimRevisionRepository::class)->latestForClaim($f['source']->id, $oldFather->id);
        self::assertNotNull($oldRevision);

        app(UpdateClaim::class)->handle(
            sourceId: $f['source']->id,
            claimId: $f['typeClaim']->id,
            subjectMentionId: $f['event']->id,
            predicate: PredicateVocabulary::get(PredicateKey::EventType),
            value: new EnumClaimValue('narodziny', EventTypeClaimValueKey::Birth->value),
        );

        $next = app(CaptureEvidenceStateForSource::class)->capture($f['source']->id);
        $newFather = $this->withParent($this->derivedParents($f['source']), $f['father']);
        self::assertSame($oldFather->id->value, $newFather->id->value);
        $newRevision = app(ClaimRevisionRepository::class)->latestForClaim($f['source']->id, $newFather->id);
        self::assertNotNull($newRevision);
        self::assertNotSame($oldRevision->id->value, $newRevision->id->value);
        self::assertCount(2, app(ClaimRevisionRepository::class)->forClaim($f['source']->id, $oldFather->id));
        self::assertCount(6, $next->snapshot->claimRevisionIds);

        $oldState = app(GetEvidenceState::class)->get($historical->id);
        self::assertContains(
            $oldRevision->id->value,
            array_map(static fn ($rev): string => $rev->id->value, $oldState->claimRevisions),
        );
        self::assertNotContains(
            $newRevision->id->value,
            array_map(static fn ($rev): string => $rev->id->value, $oldState->claimRevisions),
        );
    }

    public function test_removing_birth_semantics_retires_derived_but_keeps_old_evidence(): void
    {
        $f = $this->fixture();
        $historic = app(CaptureEvidenceStateForSource::class)->capture($f['source']->id);

        app(UpdateClaim::class)->handle(
            sourceId: $f['source']->id,
            claimId: $f['typeClaim']->id,
            subjectMentionId: $f['event']->id,
            predicate: PredicateVocabulary::get(PredicateKey::EventType),
            value: new EnumClaimValue('chrzest', EventTypeClaimValueKey::Baptism->value),
        );

        $current = app(CaptureEvidenceStateForSource::class)->capture($f['source']->id);
        self::assertSame([], $this->derivedParents($f['source']));
        self::assertCount(4, $current->snapshot->claimRevisionIds);
        self::assertCount(6, app(GetEvidenceState::class)->get($historic->id)->claimRevisions);
    }

    public function test_reassigned_child_role_retires_old_relation_and_creates_new_subject(): void
    {
        $f = $this->fixture();
        $old = app(CaptureEvidenceStateForSource::class)->capture($f['source']->id);
        $replacement = app(CreateMention::class)->handle($f['source']->id, MentionKind::person(), 'new-child');

        app(UpdateClaim::class)->handle(
            sourceId: $f['source']->id,
            claimId: $f['childRole']->id,
            subjectMentionId: $f['event']->id,
            predicate: PredicateVocabulary::get(PredicateKey::EventChild),
            objectMentionId: $replacement->id,
        );

        app(CaptureEvidenceStateForSource::class)->capture($f['source']->id);
        $parents = $this->derivedParents($f['source']);
        self::assertCount(2, $parents);
        foreach ($parents as $claim) {
            self::assertSame($replacement->id->value, $claim->subjectMentionId->value);
        }
        self::assertCount(6, app(GetEvidenceState::class)->get($old->id)->claimRevisions);
    }

    public function test_two_birth_event_contexts_with_same_relation_produce_one_derived_claim(): void
    {
        $f = $this->fixture();
        $secondEvent = app(CreateMention::class)->handle($f['source']->id, MentionKind::event(), 'second-birth');
        $type = app(CreateClaim::class)->handle(
            sourceId: $f['source']->id,
            subjectMentionId: $secondEvent->id,
            predicate: PredicateVocabulary::get(PredicateKey::EventType),
            value: new EnumClaimValue('birth', EventTypeClaimValueKey::Birth->value),
        );
        app(CreateClaim::class)->handle(
            sourceId: $f['source']->id,
            subjectMentionId: $secondEvent->id,
            predicate: PredicateVocabulary::get(PredicateKey::EventChild),
            objectMentionId: $f['child']->id,
        );
        app(CreateClaim::class)->handle(
            sourceId: $f['source']->id,
            subjectMentionId: $secondEvent->id,
            predicate: PredicateVocabulary::get(PredicateKey::EventParent),
            objectMentionId: $f['father']->id,
        );

        app(CaptureEvidenceStateForSource::class)->capture($f['source']->id);
        $parents = $this->derivedParents($f['source']);
        self::assertCount(2, $parents);
        $father = $this->withParent($parents, $f['father']);
        self::assertCount(6, $father->derivation?->inputClaimRevisionIds);
        self::assertCount(2, $father->derivation?->assumptions['birth_event_mention_ids']);
        self::assertSame('child:'.$f['child']->id->value.':parent:'.$f['father']->id->value, $father->derivation?->outputKey);
    }

    /**
     * @return array{
     *     source: Source,
     *     event: Mention,
     *     child: Mention,
     *     father: Mention,
     *     mother: Mention,
     *     typeClaim: ?Claim,
     *     childRole: Claim,
     *     fatherRole: Claim,
     *     motherRole: Claim
     * }
     */
    private function fixture(?string $eventType = 'birth', ?SourceType $sourceType = null): array
    {
        $source = app(CreateSource::class)->handle($sourceType ?? SourceType::generic());
        $event = app(CreateMention::class)->handle($source->id, MentionKind::event(), 'birth-occurrence');
        $child = app(CreateMention::class)->handle($source->id, MentionKind::person(), 'child');
        $father = app(CreateMention::class)->handle($source->id, MentionKind::person(), 'father');
        $mother = app(CreateMention::class)->handle($source->id, MentionKind::person(), 'mother');

        $type = $eventType === null ? null : app(CreateClaim::class)->handle(
            sourceId: $source->id,
            subjectMentionId: $event->id,
            predicate: PredicateVocabulary::get(PredicateKey::EventType),
            value: new EnumClaimValue($eventType, $eventType),
        );
        $childRole = app(CreateClaim::class)->handle(
            sourceId: $source->id,
            subjectMentionId: $event->id,
            predicate: PredicateVocabulary::get(PredicateKey::EventChild),
            objectMentionId: $child->id,
        );
        $fatherRole = app(CreateClaim::class)->handle(
            sourceId: $source->id,
            subjectMentionId: $event->id,
            predicate: PredicateVocabulary::get(PredicateKey::EventParent),
            objectMentionId: $father->id,
        );
        $motherRole = app(CreateClaim::class)->handle(
            sourceId: $source->id,
            subjectMentionId: $event->id,
            predicate: PredicateVocabulary::get(PredicateKey::EventParent),
            objectMentionId: $mother->id,
        );

        return [
            'source' => $source,
            'event' => $event,
            'child' => $child,
            'father' => $father,
            'mother' => $mother,
            'typeClaim' => $type,
            'childRole' => $childRole,
            'fatherRole' => $fatherRole,
            'motherRole' => $motherRole,
        ];
    }

    /** @return list<Claim> */
    private function derivedParents(Source $source): array
    {
        return array_values(array_filter(
            app(ClaimRepository::class)->forSource($source->id),
            static fn (Claim $claim): bool => $claim->origin->kind === ClaimOriginKind::Derived
                && $claim->predicate->key === PredicateKey::PersonParent,
        ));
    }

    /**
     * @param  list<Claim>  $parents
     * @return list<string>
     */
    private function parentIdsOf(array $parents, Mention $child): array
    {
        $ids = [];
        foreach ($parents as $claim) {
            self::assertSame($child->id->value, $claim->subjectMentionId->value);
            self::assertNotNull($claim->objectMentionId);
            $ids[] = $claim->objectMentionId->value;
        }
        sort($ids, SORT_STRING);

        return $ids;
    }

    /** @param list<Claim> $relations */
    private function withParent(array $relations, Mention $parent): Claim
    {
        foreach ($relations as $relation) {
            if ($relation->objectMentionId?->value === $parent->id->value) {
                return $relation;
            }
        }

        throw new \LogicException('Missing Derived Claim for expected parent.');
    }
}
