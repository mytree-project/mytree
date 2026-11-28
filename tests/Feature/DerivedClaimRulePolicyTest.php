<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Acquisition\ApproveDerivedClaimProposal;
use App\Application\Acquisition\CaptureEvidenceStateForSource;
use App\Application\Acquisition\ClaimRepository;
use App\Application\Acquisition\CreateClaim;
use App\Application\Acquisition\CreateMention;
use App\Application\Acquisition\CreateSource;
use App\Application\Acquisition\DerivedClaimProposalRepository;
use App\Application\Acquisition\GetEvidenceState;
use App\Application\Acquisition\RejectDerivedClaimProposal;
use App\Application\Acquisition\UpdateClaim;
use App\Application\Settings\Acquisition\DerivedClaimRulePolicies;
use App\Application\Settings\Acquisition\DerivedClaimRuleRegistry;
use App\Domain\Acquisition\AgeClaimValue;
use App\Domain\Acquisition\Claim;
use App\Domain\Acquisition\ClaimOriginKind;
use App\Domain\Acquisition\DateClaimValue;
use App\Domain\Acquisition\DerivedClaimReviewStatus;
use App\Domain\Acquisition\HistoricalDate;
use App\Domain\Acquisition\Mention;
use App\Domain\Acquisition\MentionKind;
use App\Domain\Acquisition\PredicateKey;
use App\Domain\Acquisition\PredicateVocabulary;
use App\Domain\Acquisition\Source;
use App\Domain\Acquisition\SourceType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class DerivedClaimRulePolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_registered_defaults_and_attributed_versioned_setting_changes(): void
    {
        $policies = app(DerivedClaimRulePolicies::class);
        $age = $policies->current(DerivedClaimRuleRegistry::AGE_AT_EVENT);
        $surname = $policies->current(DerivedClaimRuleRegistry::CHILD_SURNAME);

        self::assertTrue($age->enabled);
        self::assertTrue($age->autoApprove);
        self::assertFalse($surname->autoApprove);
        self::assertTrue($surname->enabled);
        self::assertSame(0, $age->enabledRevision);
        self::assertSame(0, $age->autoApproveRevision);
        self::assertSame(0, DB::table('application_settings')->count());

        $changed = $policies->update(DerivedClaimRuleRegistry::AGE_AT_EVENT, false, false, 'admin:42');
        self::assertFalse($changed->enabled);
        self::assertFalse($changed->autoApprove);
        self::assertSame(1, $changed->enabledRevision);
        self::assertSame(1, $changed->autoApproveRevision);
        $this->assertDatabaseHas('application_settings', [
            'section' => 'acquisition',
            'key' => 'age_at_event_enabled',
            'value' => 'false',
            'revision' => 1,
            'changed_by' => 'admin:42',
        ]);
        $policies->update(DerivedClaimRuleRegistry::AGE_AT_EVENT, false, false, 'admin:42');
        self::assertSame(1, $policies->current(DerivedClaimRuleRegistry::AGE_AT_EVENT)->enabledRevision);
        self::assertCount(2, DB::table('application_settings')->get());
    }

    public function test_enabled_automatic_age_rule_preserves_soft_interval_and_system_attribution(): void
    {
        [$source] = $this->fixture();
        $state = app(CaptureEvidenceStateForSource::class)->capture($source->id);
        self::assertCount(4, $state->snapshot->claimRevisionIds);
        $output = $this->derived($source);
        self::assertCount(1, $output);
        self::assertSame(true, $output[0]->derivation?->assumptions['soft_evidence']);
        self::assertSame(false, $output[0]->derivation?->assumptions['hard_identity_exclusion_allowed']);
        self::assertSame('1822-05-11', $output[0]->value?->data()['from']);

        $proposals = app(DerivedClaimProposalRepository::class)->forSource($source->id);
        self::assertCount(1, $proposals);
        self::assertSame(DerivedClaimReviewStatus::Accepted, $proposals[0]->status);
        $history = app(DerivedClaimProposalRepository::class)->history($source->id, $proposals[0]->id);
        self::assertSame(['pending', 'accepted'], array_column($history, 'status'));
        self::assertStringStartsWith('system:rule-policy:age_at_event:', $history[1]['actor']);
        self::assertSame($state->id->value, $history[1]['evidence_state_id']);
        self::assertSame($output[0]->id->value, $history[1]['accepted_claim_id']);

        app(CaptureEvidenceStateForSource::class)->capture($source->id);
        self::assertCount(1, $this->derived($source));
        self::assertSame($output[0]->id->value, $this->derived($source)[0]->id->value);
        self::assertCount(2, app(DerivedClaimProposalRepository::class)->history($source->id, $proposals[0]->id));
    }

    public function test_manual_age_policy_creates_pending_without_evidence_until_human_approval(): void
    {
        app(DerivedClaimRulePolicies::class)->update(DerivedClaimRuleRegistry::AGE_AT_EVENT, true, false, 'admin');
        [$source] = $this->fixture();

        $state = app(CaptureEvidenceStateForSource::class)->capture($source->id);
        self::assertCount(3, $state->snapshot->claimRevisionIds);
        self::assertSame([], $this->derived($source));
        $proposal = app(DerivedClaimProposalRepository::class)->forSource($source->id)[0];
        self::assertSame(DerivedClaimReviewStatus::Pending, $proposal->status);

        $approved = app(ApproveDerivedClaimProposal::class)->handle($source->id, $proposal->id, 'human:42');
        self::assertCount(4, $approved->snapshot->claimRevisionIds);
        self::assertSame('human:42', app(DerivedClaimProposalRepository::class)
            ->history($source->id, $proposal->id)[1]['actor']);
    }

    public function test_disabling_then_reenabling_retires_active_claim_and_keeps_historical_decisions(): void
    {
        [$source] = $this->fixture();
        $old = app(CaptureEvidenceStateForSource::class)->capture($source->id);
        $claimId = $this->derived($source)[0]->id->value;
        $proposal = app(DerivedClaimProposalRepository::class)->forSource($source->id)[0];
        $policies = app(DerivedClaimRulePolicies::class);

        $policies->update(DerivedClaimRuleRegistry::AGE_AT_EVENT, false, true, 'admin');
        $disabled = app(CaptureEvidenceStateForSource::class)->capture($source->id);
        self::assertCount(3, $disabled->snapshot->claimRevisionIds);
        self::assertSame([], $this->derived($source));
        self::assertSame(DerivedClaimReviewStatus::Invalidated, app(DerivedClaimProposalRepository::class)
            ->find($source->id, $proposal->id)?->status);
        self::assertCount(4, app(GetEvidenceState::class)->get($old->id)->claimRevisions);

        $policies->update(DerivedClaimRuleRegistry::AGE_AT_EVENT, true, true, 'admin');
        $restarted = app(CaptureEvidenceStateForSource::class)->capture($source->id);
        self::assertCount(4, $restarted->snapshot->claimRevisionIds);
        self::assertNotSame($claimId, $this->derived($source)[0]->id->value);
        $history = app(DerivedClaimProposalRepository::class)->history($source->id, $proposal->id);
        self::assertSame(
            ['pending', 'accepted', 'invalidated', 'pending', 'accepted'],
            array_column($history, 'status'),
        );
        self::assertSame($claimId, $history[1]['accepted_claim_id']);
        self::assertSame($restarted->id->value, $history[4]['evidence_state_id']);
    }

    public function test_rejection_blocks_automatic_acceptance_of_the_same_fingerprint(): void
    {
        $policies = app(DerivedClaimRulePolicies::class);
        $policies->update(DerivedClaimRuleRegistry::AGE_AT_EVENT, true, false, 'admin');
        [$source, $person, $age] = $this->fixture();
        app(CaptureEvidenceStateForSource::class)->capture($source->id);
        $proposal = app(DerivedClaimProposalRepository::class)->forSource($source->id)[0];
        app(RejectDerivedClaimProposal::class)->handle($source->id, $proposal->id, 'human');

        $policies->update(DerivedClaimRuleRegistry::AGE_AT_EVENT, true, true, 'admin');
        $unchanged = app(CaptureEvidenceStateForSource::class)->capture($source->id);
        self::assertCount(3, $unchanged->snapshot->claimRevisionIds);
        self::assertSame([], $this->derived($source));
        self::assertCount(1, app(DerivedClaimProposalRepository::class)->forSource($source->id));

        app(UpdateClaim::class)->handle(
            sourceId: $source->id,
            claimId: $age->id,
            subjectMentionId: $person->id,
            predicate: PredicateVocabulary::get(PredicateKey::PersonAge),
            value: AgeClaimValue::exact('41 years', 41),
        );
        $revised = app(CaptureEvidenceStateForSource::class)->capture($source->id);
        self::assertCount(4, $revised->snapshot->claimRevisionIds);
        self::assertCount(1, $this->derived($source));
        self::assertCount(2, app(DerivedClaimProposalRepository::class)->forSource($source->id));
    }

    public function test_compatible_direct_birth_date_invalidates_pending_manual_proposal(): void
    {
        $policies = app(DerivedClaimRulePolicies::class);
        $policies->update(DerivedClaimRuleRegistry::AGE_AT_EVENT, true, false, 'admin');
        [$source, $person] = $this->fixture();
        app(CaptureEvidenceStateForSource::class)->capture($source->id);
        $proposal = app(DerivedClaimProposalRepository::class)->forSource($source->id)[0];
        self::assertSame(DerivedClaimReviewStatus::Pending, $proposal->status);

        app(CreateClaim::class)->handle(
            sourceId: $source->id,
            subjectMentionId: $person->id,
            predicate: PredicateVocabulary::get(PredicateKey::PersonBirthDate),
            value: DateClaimValue::exact('1823', HistoricalDate::year(1823)),
        );

        $after = app(CaptureEvidenceStateForSource::class)->capture($source->id);
        self::assertCount(4, $after->snapshot->claimRevisionIds);
        self::assertSame([], $this->derived($source));
        self::assertSame(
            DerivedClaimReviewStatus::Invalidated,
            app(DerivedClaimProposalRepository::class)->find($source->id, $proposal->id)?->status,
        );
    }

    public function test_mode_switch_only_affects_new_inputs_and_manual_change_needs_reapproval(): void
    {
        [$source, $person, $age] = $this->fixture();
        $historical = app(CaptureEvidenceStateForSource::class)->capture($source->id);
        $oldClaim = $this->derived($source)[0];

        $policies = app(DerivedClaimRulePolicies::class);
        $policies->update(DerivedClaimRuleRegistry::AGE_AT_EVENT, true, false, 'admin');
        app(CaptureEvidenceStateForSource::class)->capture($source->id);
        self::assertSame($oldClaim->id->value, $this->derived($source)[0]->id->value);

        // Revising the Direct source expression even to the same semantic
        // value still changes the derivation's exact revision lineage.
        app(UpdateClaim::class)->handle(
            sourceId: $source->id,
            claimId: $age->id,
            subjectMentionId: $person->id,
            predicate: PredicateVocabulary::get(PredicateKey::PersonAge),
            value: AgeClaimValue::exact('40 years', 40),
            rawText: 'forty full years as stated by the informant',
        );
        $current = app(CaptureEvidenceStateForSource::class)->capture($source->id);
        self::assertCount(3, $current->snapshot->claimRevisionIds);
        self::assertSame([], $this->derived($source));

        $proposals = app(DerivedClaimProposalRepository::class)->forSource($source->id);
        self::assertCount(2, $proposals);
        $pending = array_values(array_filter(
            $proposals,
            static fn ($proposal): bool => $proposal->status === DerivedClaimReviewStatus::Pending,
        ));
        self::assertCount(1, $pending);
        self::assertSame(
            '1822-05-11',
            $pending[0]->value?->data()['from'],
        );
        self::assertCount(4, app(GetEvidenceState::class)->get($historical->id)->claimRevisions);
    }

    /** @return array{Source, Mention, Claim} */
    private function fixture(): array
    {
        $source = app(CreateSource::class)->handle(SourceType::generic());
        $person = app(CreateMention::class)->handle($source->id, MentionKind::person(), 'person');
        $event = app(CreateMention::class)->handle($source->id, MentionKind::event(), 'event');
        $age = app(CreateClaim::class)->handle(
            sourceId: $source->id,
            subjectMentionId: $person->id,
            predicate: PredicateVocabulary::get(PredicateKey::PersonAge),
            value: AgeClaimValue::exact('40 years', 40),
        );
        app(CreateClaim::class)->handle(
            sourceId: $source->id,
            subjectMentionId: $event->id,
            predicate: PredicateVocabulary::get(PredicateKey::EventDate),
            value: DateClaimValue::exact('10 May 1863', HistoricalDate::day(1863, 5, 10)),
        );
        app(CreateClaim::class)->handle(
            sourceId: $source->id,
            subjectMentionId: $event->id,
            predicate: PredicateVocabulary::get(PredicateKey::EventParticipant),
            objectMentionId: $person->id,
        );

        return [$source, $person, $age];
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
