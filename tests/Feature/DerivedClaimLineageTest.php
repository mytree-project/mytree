<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Acquisition\CaptureEvidenceStateForSource;
use App\Application\Acquisition\CreateClaim;
use App\Application\Acquisition\CreateMention;
use App\Application\Acquisition\CreateSource;
use App\Application\Acquisition\GetEvidenceState;
use App\Application\Acquisition\ListClaimRevisions;
use App\Application\Acquisition\ListMentionRevisions;
use App\Application\Acquisition\UpdateClaim;
use App\Application\Acquisition\UpsertDerivedClaim;
use App\Domain\Acquisition\CanonicalJson;
use App\Domain\Acquisition\Claim;
use App\Domain\Acquisition\ClaimDerivation;
use App\Domain\Acquisition\ClaimId;
use App\Domain\Acquisition\ClaimOrigin;
use App\Domain\Acquisition\ClaimOriginKind;
use App\Domain\Acquisition\ClaimRevision;
use App\Domain\Acquisition\ClaimRevisionId;
use App\Domain\Acquisition\ClaimRevisionSnapshot;
use App\Domain\Acquisition\Mention;
use App\Domain\Acquisition\MentionKind;
use App\Domain\Acquisition\MentionRevision;
use App\Domain\Acquisition\MentionRevisionId;
use App\Domain\Acquisition\PredicateKey;
use App\Domain\Acquisition\PredicateVocabulary;
use App\Domain\Acquisition\Source;
use App\Domain\Acquisition\SourceType;
use App\Domain\Acquisition\TextClaimValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

final class DerivedClaimLineageTest extends TestCase
{
    use RefreshDatabase;

    public function test_accepted_derived_claim_is_in_same_evidence_state_and_replays_immutable_inputs(): void
    {
        [$source, $person, $direct, $directRevision, $mentionRevision] = $this->fixture();

        $lineage = $this->lineage(
            directRevisionId: $directRevision->id,
            mentionRevisionId: $mentionRevision->id,
        );

        $derived = app(UpsertDerivedClaim::class)->handle(
            sourceId: $source->id,
            subjectMentionId: $person->id,
            predicate: PredicateVocabulary::get(PredicateKey::PersonSurname),
            value: new TextClaimValue('Kowalski'),
            derivation: $lineage,
        );

        $first = app(CaptureEvidenceStateForSource::class)->capture($source->id);
        $historical = app(GetEvidenceState::class)->get($first->id);
        self::assertCount(2, $historical->claimRevisions);
        self::assertSame('mytree.evidence-state.v2', CanonicalJson::decodeObject(
            $first->snapshot->canonicalPayload,
        )['schema']);

        $derivedRevision = app(ListClaimRevisions::class)->handle($source->id, $derived->id)[0];
        self::assertContains(
            $derivedRevision->id->value,
            array_map(static fn ($id): string => $id->value, $first->snapshot->claimRevisionIds),
        );
        self::assertSame(ClaimOriginKind::Derived, $derivedRevision->reconstruct()->claim->origin->kind);
        self::assertSame($directRevision->id->value, $derivedRevision->reconstruct()->claim->derivation?->inputClaimRevisionIds[0]->value);
        self::assertSame($mentionRevision->id->value, $derivedRevision->reconstruct()->claim->derivation?->inputMentionRevisionIds[0]->value);
        self::assertSame(ClaimRevisionSnapshot::SCHEMA_VERSION, $derivedRevision->snapshot->schemaVersion);

        $repeat = app(UpsertDerivedClaim::class)->handle(
            sourceId: $source->id,
            subjectMentionId: $person->id,
            predicate: PredicateVocabulary::get(PredicateKey::PersonSurname),
            value: new TextClaimValue('Kowalski'),
            derivation: $lineage,
        );

        self::assertSame($derived->id->value, $repeat->id->value);
        self::assertCount(1, app(ListClaimRevisions::class)->handle($source->id, $derived->id));

        $secondLineage = $this->lineage(
            directRevisionId: $directRevision->id,
            mentionRevisionId: $mentionRevision->id,
            ruleVersion: '2',
        );
        app(UpsertDerivedClaim::class)->handle(
            sourceId: $source->id,
            subjectMentionId: $person->id,
            predicate: PredicateVocabulary::get(PredicateKey::PersonSurname),
            value: new TextClaimValue('Kowalski'),
            derivation: $secondLineage,
        );
        $all = app(ListClaimRevisions::class)->handle($source->id, $derived->id);
        self::assertCount(2, $all);
        self::assertSame('1', $all[0]->reconstruct()->claim->derivation?->ruleVersion);
        self::assertSame('2', $all[1]->reconstruct()->claim->derivation?->ruleVersion);
        self::assertNotSame($all[0]->snapshot->payloadHash, $all[1]->snapshot->payloadHash);

        $oldState = app(GetEvidenceState::class)->get($first->id);
        self::assertSame($historical->evidenceState->snapshot->canonicalPayload, $oldState->evidenceState->snapshot->canonicalPayload);
        self::assertContains(
            $all[0]->id->value,
            array_map(static fn ($revision): string => $revision->id->value, $oldState->claimRevisions),
        );
        self::assertNotContains(
            $all[1]->id->value,
            array_map(static fn ($revision): string => $revision->id->value, $oldState->claimRevisions),
        );
    }

    public function test_changed_direct_revision_excludes_stale_derivation_until_rebuilt(): void
    {
        [$source, $person, $direct, $directRevision, $mentionRevision] = $this->fixture();

        $derived = app(UpsertDerivedClaim::class)->handle(
            sourceId: $source->id,
            subjectMentionId: $person->id,
            predicate: PredicateVocabulary::get(PredicateKey::PersonSurname),
            value: new TextClaimValue('Kowalski'),
            derivation: $this->lineage($directRevision->id, $mentionRevision->id),
        );

        $oldState = app(CaptureEvidenceStateForSource::class)->capture($source->id);
        app(UpdateClaim::class)->handle(
            sourceId: $source->id,
            claimId: $direct->id,
            subjectMentionId: $person->id,
            predicate: PredicateVocabulary::get(PredicateKey::PersonGivenName),
            value: new TextClaimValue('Jan Antoni'),
        );
        $newState = app(CaptureEvidenceStateForSource::class)->capture($source->id);
        self::assertCount(1, $newState->snapshot->claimRevisionIds);
        self::assertCount(2, $oldState->snapshot->claimRevisionIds);

        $directRevisions = app(ListClaimRevisions::class)->handle($source->id, $direct->id);
        $latestDirect = $directRevisions[count($directRevisions) - 1];
        app(UpsertDerivedClaim::class)->handle(
            sourceId: $source->id,
            subjectMentionId: $person->id,
            predicate: PredicateVocabulary::get(PredicateKey::PersonSurname),
            value: new TextClaimValue('Kowalski'),
            derivation: $this->lineage($latestDirect->id, $mentionRevision->id),
        );

        $reconciled = app(CaptureEvidenceStateForSource::class)->capture($source->id);
        self::assertCount(2, $reconciled->snapshot->claimRevisionIds);
        self::assertSame($derived->id->value, app(ListClaimRevisions::class)->handle($source->id, $derived->id)[1]->claimId->value);
        self::assertCount(2, app(GetEvidenceState::class)->get($oldState->id)->claimRevisions);
    }

    public function test_derivation_cannot_point_to_another_derived_or_different_source(): void
    {
        [$source, $person, $direct, $directRevision, $mentionRevision] = $this->fixture();
        $derived = app(UpsertDerivedClaim::class)->handle(
            sourceId: $source->id,
            subjectMentionId: $person->id,
            predicate: PredicateVocabulary::get(PredicateKey::PersonSurname),
            value: new TextClaimValue('Kowalski'),
            derivation: $this->lineage($directRevision->id, $mentionRevision->id),
        );

        $derivedRevision = app(ListClaimRevisions::class)->handle($source->id, $derived->id)[0];
        try {
            app(UpsertDerivedClaim::class)->handle(
                sourceId: $source->id,
                subjectMentionId: $person->id,
                predicate: PredicateVocabulary::get(PredicateKey::PersonOccupation),
                value: new TextClaimValue('kowal'),
                derivation: new ClaimDerivation(
                    ruleId: 'test.second',
                    ruleVersion: '1',
                    implementationSignature: 'test:1',
                    outputKey: 'occupation:person',
                    inputClaimRevisionIds: [$derivedRevision->id],
                ),
            );
            self::fail('Derived-to-Derived input must be rejected.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('cannot depend', $exception->getMessage());
        }

        $other = app(CreateSource::class)->handle(SourceType::generic());
        $otherPerson = app(CreateMention::class)->handle($other->id, MentionKind::person(), 'person.other');
        $this->expectException(InvalidArgumentException::class);
        app(UpsertDerivedClaim::class)->handle(
            sourceId: $other->id,
            subjectMentionId: $otherPerson->id,
            predicate: PredicateVocabulary::get(PredicateKey::PersonSurname),
            value: new TextClaimValue('Kowalski'),
            derivation: $this->lineage($directRevision->id, $mentionRevision->id),
        );
    }

    public function test_derived_origin_requires_lineage_and_non_derived_origin_forbids_it(): void
    {
        [$source, $person, $direct, $directRevision, $mentionRevision] = $this->fixture();
        $args = [
            'id' => new ClaimId('a8888888-8888-4888-8888-888888888888'),
            'sourceId' => $source->id,
            'subjectMentionId' => $person->id,
            'predicate' => PredicateVocabulary::get(PredicateKey::PersonSurname),
            'value' => new TextClaimValue('Kowalski'),
        ];

        try {
            new Claim(...$args, origin: ClaimOrigin::derived());
            self::fail('Missing lineage must be rejected.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('requires derivation lineage', $exception->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        new Claim(
            ...$args,
            origin: ClaimOrigin::manualDirectSource(),
            derivation: $this->lineage($directRevision->id, $mentionRevision->id),
        );
    }

    public function test_old_claim_revision_v2_payload_still_rehydrates_without_mutation(): void
    {
        [$source, $person, $direct] = $this->fixture();
        $revision = app(ListClaimRevisions::class)->handle($source->id, $direct->id)[0];
        $payload = CanonicalJson::decodeObject($revision->snapshot->canonicalPayload);
        unset($payload['claim']['derivation']);
        $payload['claim']['schema_version'] = 2;
        $payload['schema'] = ClaimRevisionSnapshot::PREVIOUS_SCHEMA_ID;
        $oldCanonical = CanonicalJson::encode($payload);
        $oldHash = hash('sha256', $oldCanonical);
        $rehydrated = ClaimRevisionSnapshot::rehydrate(
            ClaimRevisionSnapshot::PREVIOUS_SCHEMA_VERSION,
            $oldCanonical,
            $oldHash,
        );

        self::assertSame($oldCanonical, $rehydrated->canonicalPayload);
        self::assertSame($oldHash, $rehydrated->payloadHash);
        self::assertNull($rehydrated->reconstruct()->claim->derivation);
        self::assertSame('Jan', $rehydrated->reconstruct()->claim->value?->raw());
    }

    /** @return  array{Source, Mention, Claim, ClaimRevision, MentionRevision} */
    private function fixture(): array
    {
        $source = app(CreateSource::class)->handle(SourceType::generic());
        $person = app(CreateMention::class)->handle($source->id, MentionKind::person(), 'person.subject');
        $direct = app(CreateClaim::class)->handle(
            sourceId: $source->id,
            subjectMentionId: $person->id,
            predicate: PredicateVocabulary::get(PredicateKey::PersonGivenName),
            value: new TextClaimValue('Jan'),
        );
        $directRevision = app(ListClaimRevisions::class)->handle($source->id, $direct->id)[0];
        $mentionRevision = app(ListMentionRevisions::class)->handle($source->id, $person->id)[0];

        return [$source, $person, $direct, $directRevision, $mentionRevision];
    }

    private function lineage(
        \App\Domain\Acquisition\ClaimRevisionId $directRevisionId,
        \App\Domain\Acquisition\MentionRevisionId $mentionRevisionId,
        string $ruleVersion = '1',
    ): ClaimDerivation {
        return new ClaimDerivation(
            ruleId: 'test.child-surname',
            ruleVersion: $ruleVersion,
            implementationSignature: 'impl:abcdef',
            outputKey: 'surname:child',
            inputClaimRevisionIds: [$directRevisionId],
            inputMentionRevisionIds: [$mentionRevisionId],
            assumptions: ['origin' => 'test'],
        );
    }
}
