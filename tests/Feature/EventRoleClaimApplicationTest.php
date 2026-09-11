<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Acquisition\CreateClaim;
use App\Application\Acquisition\CreateMention;
use App\Application\Acquisition\CreateSource;
use App\Application\Acquisition\ListSourceClaims;
use App\Domain\Acquisition\Claim;
use App\Domain\Acquisition\MentionKind;
use App\Domain\Acquisition\PredicateKey;
use App\Domain\Acquisition\PredicateVocabulary;
use App\Domain\Acquisition\SourceType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EventRoleClaimApplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_reified_birth_event_persists_controlled_source_local_person_roles(): void
    {
        $source = app(CreateSource::class)->handle(SourceType::generic());
        $birth = app(CreateMention::class)->handle($source->id, MentionKind::event(), 'event.birth');
        $child = app(CreateMention::class)->handle($source->id, MentionKind::person(), 'person.child');
        $mother = app(CreateMention::class)->handle($source->id, MentionKind::person(), 'person.mother');
        $father = app(CreateMention::class)->handle($source->id, MentionKind::person(), 'person.father');
        $declarant = app(CreateMention::class)->handle($source->id, MentionKind::person(), 'person.declarant');
        $witness = app(CreateMention::class)->handle($source->id, MentionKind::person(), 'person.witness');

        foreach ([
            [PredicateKey::EventChild, $child->id],
            [PredicateKey::EventParent, $mother->id],
            [PredicateKey::EventParent, $father->id],
            [PredicateKey::EventDeclarant, $declarant->id],
            [PredicateKey::EventWitness, $witness->id],
        ] as [$predicateKey, $personId]) {
            app(CreateClaim::class)->handle(
                sourceId: $source->id,
                subjectMentionId: $birth->id,
                predicate: PredicateVocabulary::get($predicateKey),
                objectMentionId: $personId,
            );
        }

        $eventClaims = array_values(array_filter(
            app(ListSourceClaims::class)->handle($source->id),
            static fn (Claim $claim): bool => $claim->subjectMentionId->value === $birth->id->value,
        ));
        $roleKeys = array_map(
            static fn (Claim $claim): string => $claim->predicate->key->value,
            $eventClaims,
        );

        self::assertCount(5, $eventClaims);
        self::assertEqualsCanonicalizing([
            'event.child',
            'event.parent',
            'event.parent',
            'event.declarant',
            'event.witness',
        ], $roleKeys);
        self::assertNotContains('event.participant', $roleKeys);
    }

    public function test_reified_marriage_event_persists_controlled_source_local_person_roles(): void
    {
        $source = app(CreateSource::class)->handle(SourceType::generic());
        $marriage = app(CreateMention::class)->handle($source->id, MentionKind::event(), 'event.marriage');
        $firstSpouse = app(CreateMention::class)->handle($source->id, MentionKind::person(), 'person.spouse.first');
        $secondSpouse = app(CreateMention::class)->handle($source->id, MentionKind::person(), 'person.spouse.second');
        $firstWitness = app(CreateMention::class)->handle($source->id, MentionKind::person(), 'person.witness.first');
        $secondWitness = app(CreateMention::class)->handle($source->id, MentionKind::person(), 'person.witness.second');
        $officiant = app(CreateMention::class)->handle($source->id, MentionKind::person(), 'person.officiant');

        foreach ([
            [PredicateKey::EventSpouse, $firstSpouse->id],
            [PredicateKey::EventSpouse, $secondSpouse->id],
            [PredicateKey::EventWitness, $firstWitness->id],
            [PredicateKey::EventWitness, $secondWitness->id],
            [PredicateKey::EventOfficiant, $officiant->id],
        ] as [$predicateKey, $personId]) {
            app(CreateClaim::class)->handle(
                sourceId: $source->id,
                subjectMentionId: $marriage->id,
                predicate: PredicateVocabulary::get($predicateKey),
                objectMentionId: $personId,
            );
        }

        $eventClaims = array_values(array_filter(
            app(ListSourceClaims::class)->handle($source->id),
            static fn (Claim $claim): bool => $claim->subjectMentionId->value === $marriage->id->value,
        ));
        $roleKeys = array_map(
            static fn (Claim $claim): string => $claim->predicate->key->value,
            $eventClaims,
        );

        self::assertCount(5, $eventClaims);
        self::assertEqualsCanonicalizing([
            'event.spouse',
            'event.spouse',
            'event.witness',
            'event.witness',
            'event.officiant',
        ], $roleKeys);
        self::assertNotContains('event.participant', $roleKeys);
    }
}
