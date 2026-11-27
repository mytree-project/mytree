<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Acquisition\ApproveDerivedClaimProposal;
use App\Application\Acquisition\CaptureEvidenceStateForSource;
use App\Application\Acquisition\ClaimRepository;
use App\Application\Acquisition\ClaimRevisionRepository;
use App\Application\Acquisition\CreateClaim;
use App\Application\Acquisition\CreateMention;
use App\Application\Acquisition\CreateSource;
use App\Application\Acquisition\DerivedClaimProposalRepository;
use App\Application\Acquisition\GetEvidenceState;
use App\Application\Acquisition\MentionRevisionRepository;
use App\Application\Acquisition\ProposeDerivedClaim;
use App\Application\Acquisition\RejectDerivedClaimProposal;
use App\Application\Acquisition\UpdateClaim;
use App\Application\Acquisition\UpdateMention;
use App\Application\Acquisition\UpsertDerivedClaim;
use App\Domain\Acquisition\Claim;
use App\Domain\Acquisition\ClaimDerivation;
use App\Domain\Acquisition\ClaimOriginKind;
use App\Domain\Acquisition\DerivedClaimProposal;
use App\Domain\Acquisition\DerivedClaimReviewStatus;
use App\Domain\Acquisition\Mention;
use App\Domain\Acquisition\MentionKind;
use App\Domain\Acquisition\PredicateKey;
use App\Domain\Acquisition\PredicateVocabulary;
use App\Domain\Acquisition\Source;
use App\Domain\Acquisition\SourceType;
use App\Domain\Acquisition\TextClaimValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

final class DerivedClaimReviewLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_is_not_evidence_and_approval_records_a_new_accepted_snapshot(): void
    {
        $f = $this->fixture();
        $before = app(CaptureEvidenceStateForSource::class)->capture($f['source']->id);
        $pending = $this->propose($f);

        self::assertSame(DerivedClaimReviewStatus::Pending, $pending->status);
        self::assertSame([], $this->derived($f['source']));
        self::assertCount(3, $before->snapshot->claimRevisionIds);

        $stillPending = app(CaptureEvidenceStateForSource::class)->capture($f['source']->id);
        self::assertCount(3, $stillPending->snapshot->claimRevisionIds);

        $accepted = app(ApproveDerivedClaimProposal::class)
            ->handle($f['source']->id, $pending->id, 'reviewer@example.test');

        $current = app(DerivedClaimProposalRepository::class)->find($f['source']->id, $pending->id);
        self::assertNotNull($current);
        self::assertSame(DerivedClaimReviewStatus::Accepted, $current->status);
        self::assertNotNull($current->acceptedClaimId);
        self::assertCount(4, $accepted->snapshot->claimRevisionIds);

        $derived = $this->derived($f['source']);
        self::assertCount(1, $derived);
        self::assertSame('Kowalski', $derived[0]->value?->raw());
        self::assertSame(ClaimOriginKind::Derived, $derived[0]->origin->kind);
        self::assertSame($pending->derivation->fingerprint(), $derived[0]->derivation?->fingerprint());
        self::assertSame($current->acceptedClaimId->value, $derived[0]->id->value);

        $history = app(DerivedClaimProposalRepository::class)->history($f['source']->id, $pending->id);
        self::assertSame(['pending', 'accepted'], array_column($history, 'status'));
        self::assertSame('reviewer@example.test', $history[1]['actor']);
        self::assertSame($accepted->id->value, $history[1]['evidence_state_id']);
        self::assertNotSame('', $history[1]['recorded_at']);
        self::assertCount(3, app(GetEvidenceState::class)->get($before->id)->claimRevisions);
    }

    public function test_rejection_is_durable_and_idempotent_for_same_complete_fingerprint(): void
    {
        $f = $this->fixture();
        $pending = $this->propose($f);

        app(RejectDerivedClaimProposal::class)->handle($f['source']->id, $pending->id, 'reviewer');
        $rejected = app(DerivedClaimProposalRepository::class)->find($f['source']->id, $pending->id);
        self::assertSame(DerivedClaimReviewStatus::Rejected, $rejected?->status);
        self::assertSame($pending->id, $this->propose($f)->id);

        self::assertSame([], $this->derived($f['source']));
        self::assertSame(['pending', 'rejected'], array_column(
            app(DerivedClaimProposalRepository::class)->history($f['source']->id, $pending->id),
            'status',
        ));
        self::assertCount(1, app(DerivedClaimProposalRepository::class)->forSource($f['source']->id));
    }

    public function test_changed_input_revision_can_be_proposed_after_rejection_even_for_identical_text(): void
    {
        $f = $this->fixture();
        $old = $this->propose($f);
        app(RejectDerivedClaimProposal::class)->handle($f['source']->id, $old->id, 'reviewer');

        $this->reviseFatherSurname($f, 'Kowalski', 'corrected direct transcription context');
        $new = $this->propose($f);
        self::assertNotSame($old->id, $new->id);
        self::assertNotSame($old->fingerprint, $new->fingerprint);
        self::assertSame(DerivedClaimReviewStatus::Pending, $new->status);

        $oldAgain = app(DerivedClaimProposalRepository::class)->find($f['source']->id, $old->id);
        self::assertSame(DerivedClaimReviewStatus::Rejected, $oldAgain?->status);
        self::assertCount(2, app(DerivedClaimProposalRepository::class)->forSource($f['source']->id));
    }

    public function test_input_change_invalidates_accepted_output_without_erasing_history(): void
    {
        $f = $this->fixture();
        $pending = $this->propose($f);
        $historical = app(ApproveDerivedClaimProposal::class)->handle($f['source']->id, $pending->id, 'reviewer');
        $oldClaim = $this->derived($f['source'])[0];
        $oldRevision = app(ClaimRevisionRepository::class)->latestForClaim($f['source']->id, $oldClaim->id);
        self::assertNotNull($oldRevision);

        $this->reviseFatherSurname($f, 'Nowak');
        $after = app(CaptureEvidenceStateForSource::class)->capture($f['source']->id);
        self::assertCount(3, $after->snapshot->claimRevisionIds);
        self::assertSame([], $this->derived($f['source']));
        self::assertSame(DerivedClaimReviewStatus::Invalidated, app(DerivedClaimProposalRepository::class)
            ->find($f['source']->id, $pending->id)?->status);
        self::assertSame(
            ['pending', 'accepted', 'invalidated'],
            array_column(app(DerivedClaimProposalRepository::class)->history($f['source']->id, $pending->id), 'status'),
        );

        $historicalClaims = app(GetEvidenceState::class)->get($historical->id)->claimRevisions;
        self::assertContains(
            $oldRevision->id->value,
            array_map(static fn ($revision): string => $revision->id->value, $historicalClaims),
        );

        $reapproval = $this->propose($f, 'Nowak');
        self::assertSame(DerivedClaimReviewStatus::Pending, $reapproval->status);
        self::assertSame([], $this->derived($f['source']));
        $next = app(ApproveDerivedClaimProposal::class)->handle($f['source']->id, $reapproval->id, 'reviewer2');
        self::assertCount(4, $next->snapshot->claimRevisionIds);
        self::assertNotSame($oldClaim->id->value, $this->derived($f['source'])[0]->id->value);
    }

    public function test_new_rule_version_requires_new_approval_even_for_same_output_and_roots(): void
    {
        $f = $this->fixture();
        $old = $this->propose($f);
        app(ApproveDerivedClaimProposal::class)->handle($f['source']->id, $old->id, 'reviewer');
        self::assertCount(1, $this->derived($f['source']));

        $new = $this->propose($f, 'Kowalski', '2');
        self::assertSame(DerivedClaimReviewStatus::Pending, $new->status);
        self::assertNotSame($old->fingerprint, $new->fingerprint);
        self::assertSame([], $this->derived($f['source']));
        self::assertSame(
            DerivedClaimReviewStatus::Invalidated,
            app(DerivedClaimProposalRepository::class)->find($f['source']->id, $old->id)?->status,
        );

        app(ApproveDerivedClaimProposal::class)->handle($f['source']->id, $new->id, 'second-reviewer');
        self::assertCount(1, $this->derived($f['source']));
    }

    public function test_stale_pending_proposal_cannot_be_approved_or_enter_evidence(): void
    {
        $f = $this->fixture();
        $pending = $this->propose($f);
        $this->reviseFatherSurname($f, 'Nowak');

        try {
            app(ApproveDerivedClaimProposal::class)->handle($f['source']->id, $pending->id, 'reviewer');
            self::fail('Stale approval should have been rejected.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('dependencies', $exception->getMessage());
        }

        app(CaptureEvidenceStateForSource::class)->capture($f['source']->id);
        self::assertSame(DerivedClaimReviewStatus::Invalidated, app(DerivedClaimProposalRepository::class)
            ->find($f['source']->id, $pending->id)?->status);
        self::assertSame([], $this->derived($f['source']));
    }

    public function test_approval_is_single_use_and_cannot_overwrite_a_matching_direct_claim(): void
    {
        $f = $this->fixture();
        $pending = $this->propose($f);
        app(ApproveDerivedClaimProposal::class)->handle($f['source']->id, $pending->id, 'reviewer');

        $this->expectException(InvalidArgumentException::class);
        app(ApproveDerivedClaimProposal::class)->handle($f['source']->id, $pending->id, 'reviewer');
    }

    public function test_new_direct_surname_suppresses_previously_approved_derived_output(): void
    {
        $f = $this->fixture();
        $pending = $this->propose($f);
        $historical = app(ApproveDerivedClaimProposal::class)->handle(
            $f['source']->id,
            $pending->id,
            'reviewer',
        );

        app(CreateClaim::class)->handle(
            sourceId: $f['source']->id,
            subjectMentionId: $f['child']->id,
            predicate: PredicateVocabulary::get(PredicateKey::PersonSurname),
            value: new TextClaimValue('Kowalski'),
        );

        $now = app(CaptureEvidenceStateForSource::class)->capture($f['source']->id);
        self::assertCount(4, $now->snapshot->claimRevisionIds);
        self::assertSame([], $this->derived($f['source']));
        self::assertSame(
            DerivedClaimReviewStatus::Invalidated,
            app(DerivedClaimProposalRepository::class)->find($f['source']->id, $pending->id)?->status,
        );
        self::assertCount(4, app(GetEvidenceState::class)->get($historical->id)->claimRevisions);
    }

    public function test_direct_same_surname_disallows_reviewed_duplicate(): void
    {
        $f = $this->fixture();
        app(CreateClaim::class)->handle(
            sourceId: $f['source']->id,
            subjectMentionId: $f['child']->id,
            predicate: PredicateVocabulary::get(PredicateKey::PersonSurname),
            value: new TextClaimValue('Kowalski'),
        );

        $this->expectException(InvalidArgumentException::class);
        $this->propose($f);
    }

    public function test_stale_mention_revision_invalidates_proposal(): void
    {
        $f = $this->fixture();
        $pending = $this->propose($f);

        $old = app(MentionRevisionRepository::class)->latestForMention($f['source']->id, $f['father']->id);
        self::assertNotNull($old);
        app(UpdateMention::class)->handle(
            sourceId: $f['source']->id,
            mentionId: $f['father']->id,
            kind: MentionKind::person(),
            displayLabel: 'Jan Kowalski (revised source descriptor)',
        );

        app(CaptureEvidenceStateForSource::class)->capture($f['source']->id);
        self::assertSame(DerivedClaimReviewStatus::Invalidated, app(DerivedClaimProposalRepository::class)
            ->find($f['source']->id, $pending->id)?->status);
        self::assertNotSame(
            $old->id->value,
            app(MentionRevisionRepository::class)->latestForMention($f['source']->id, $f['father']->id)?->id->value,
        );
    }

    public function test_review_required_upsert_cannot_bypass_explicit_approval_on_capture(): void
    {
        $f = $this->fixture();
        $pending = $this->propose($f);

        // Even an accidental direct call to the low-level accepted output
        // writer must not leak a pending contextual inference into evidence.
        app(UpsertDerivedClaim::class)->handle(
            sourceId: $f['source']->id,
            subjectMentionId: $f['child']->id,
            predicate: PredicateVocabulary::get(PredicateKey::PersonSurname),
            derivation: $pending->derivation,
            value: new TextClaimValue('Kowalski'),
        );

        $state = app(CaptureEvidenceStateForSource::class)->capture($f['source']->id);
        self::assertCount(3, $state->snapshot->claimRevisionIds);
        self::assertSame([], $this->derived($f['source']));
        self::assertSame(
            DerivedClaimReviewStatus::Pending,
            app(DerivedClaimProposalRepository::class)->find($f['source']->id, $pending->id)?->status,
        );
    }

    public function test_mutating_an_accepted_reviewed_output_requires_new_approval(): void
    {
        $f = $this->fixture();
        $pending = $this->propose($f);
        $historical = app(ApproveDerivedClaimProposal::class)->handle($f['source']->id, $pending->id, 'reviewer');
        $claim = $this->derived($f['source'])[0];

        app(UpdateClaim::class)->handle(
            sourceId: $f['source']->id,
            claimId: $claim->id,
            subjectMentionId: $f['child']->id,
            predicate: PredicateVocabulary::get(PredicateKey::PersonSurname),
            value: new TextClaimValue('Changed without review'),
            derivation: $pending->derivation,
        );

        $now = app(CaptureEvidenceStateForSource::class)->capture($f['source']->id);
        self::assertCount(3, $now->snapshot->claimRevisionIds);
        self::assertSame([], $this->derived($f['source']));
        self::assertSame(
            DerivedClaimReviewStatus::Invalidated,
            app(DerivedClaimProposalRepository::class)->find($f['source']->id, $pending->id)?->status,
        );
        self::assertCount(4, app(GetEvidenceState::class)->get($historical->id)->claimRevisions);
    }

    public function test_proposals_are_source_scoped_and_not_visible_as_claims(): void
    {
        $f = $this->fixture();
        $other = app(CreateSource::class)->handle(SourceType::generic());
        $pending = $this->propose($f);
        self::assertNull(app(DerivedClaimProposalRepository::class)->find($other->id, $pending->id));
        self::assertSame([], app(DerivedClaimProposalRepository::class)->history($other->id, $pending->id));

        $this->expectException(InvalidArgumentException::class);
        app(RejectDerivedClaimProposal::class)->handle($other->id, $pending->id, 'reviewer');
    }

    /**
     * @return array{source: Source, event: Mention, child: Mention, father: Mention, fatherSurname: Claim}
     */
    private function fixture(): array
    {
        $source = app(CreateSource::class)->handle(SourceType::generic());
        $event = app(CreateMention::class)->handle($source->id, MentionKind::event(), 'birth');
        $child = app(CreateMention::class)->handle($source->id, MentionKind::person(), 'child');
        $father = app(CreateMention::class)->handle($source->id, MentionKind::person(), 'father');

        app(CreateClaim::class)->handle(
            sourceId: $source->id,
            subjectMentionId: $event->id,
            predicate: PredicateVocabulary::get(PredicateKey::EventChild),
            objectMentionId: $child->id,
        );
        app(CreateClaim::class)->handle(
            sourceId: $source->id,
            subjectMentionId: $event->id,
            predicate: PredicateVocabulary::get(PredicateKey::EventParent),
            objectMentionId: $father->id,
        );
        $surname = app(CreateClaim::class)->handle(
            sourceId: $source->id,
            subjectMentionId: $father->id,
            predicate: PredicateVocabulary::get(PredicateKey::PersonSurname),
            value: new TextClaimValue('Kowalski'),
        );

        return ['source' => $source, 'event' => $event, 'child' => $child, 'father' => $father, 'fatherSurname' => $surname];
    }

    /** @param array{source: Source, event: Mention, child: Mention, father: Mention, fatherSurname: Claim} $f */
    private function propose(array $f, string $surname = 'Kowalski', string $ruleVersion = '1'): DerivedClaimProposal
    {
        $revisions = app(ClaimRevisionRepository::class);
        $roots = [];
        foreach (app(ClaimRepository::class)->forSource($f['source']->id) as $claim) {
            if ($claim->origin->kind === ClaimOriginKind::Derived) {
                continue;
            }
            $revision = $revisions->latestForClaim($f['source']->id, $claim->id);
            self::assertNotNull($revision);
            $roots[] = $revision->id;
        }

        $mentionRevisions = app(MentionRevisionRepository::class);
        $mentionRoots = [];
        foreach ([$f['event'], $f['child'], $f['father']] as $mention) {
            $revision = $mentionRevisions->latestForMention($f['source']->id, $mention->id);
            self::assertNotNull($revision);
            $mentionRoots[] = $revision->id;
        }

        return app(ProposeDerivedClaim::class)->handle(
            sourceId: $f['source']->id,
            subjectMentionId: $f['child']->id,
            predicate: PredicateVocabulary::get(PredicateKey::PersonSurname),
            derivation: new ClaimDerivation(
                ruleId: 'person.surname.from_parent.review',
                ruleVersion: $ruleVersion,
                implementationSignature: 'reviewable-parent-surname-v1',
                outputKey: 'surname:'.$f['child']->id->value,
                inputClaimRevisionIds: $roots,
                inputMentionRevisionIds: $mentionRoots,
                assumptions: ['father_role_direct' => true, 'requires_review' => true],
            ),
            value: new TextClaimValue($surname),
        );
    }

    /** @param array{source: Source, event: Mention, child: Mention, father: Mention, fatherSurname: Claim} $f */
    private function reviseFatherSurname(array $f, string $surname, ?string $rawText = null): void
    {
        app(UpdateClaim::class)->handle(
            sourceId: $f['source']->id,
            claimId: $f['fatherSurname']->id,
            subjectMentionId: $f['father']->id,
            predicate: PredicateVocabulary::get(PredicateKey::PersonSurname),
            value: new TextClaimValue($surname),
            rawText: $rawText,
        );
    }

    /** @return list<Claim> */
    private function derived(Source $source): array
    {
        return array_values(array_filter(
            app(ClaimRepository::class)->forSource($source->id),
            static fn (Claim $claim): bool => $claim->origin->kind === ClaimOriginKind::Derived,
        ));
    }
}
