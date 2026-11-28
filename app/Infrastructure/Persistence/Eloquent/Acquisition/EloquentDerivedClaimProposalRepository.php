<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Acquisition;

use App\Application\Acquisition\DerivedClaimProposalRepository;
use App\Domain\Acquisition\ClaimDerivation;
use App\Domain\Acquisition\ClaimId;
use App\Domain\Acquisition\ClaimQualifiers;
use App\Domain\Acquisition\ClaimValueSerializer;
use App\Domain\Acquisition\DerivedClaimProposal;
use App\Domain\Acquisition\DerivedClaimReviewStatus;
use App\Domain\Acquisition\EvidenceStateId;
use App\Domain\Acquisition\MentionId;
use App\Domain\Acquisition\PredicateVocabulary;
use App\Domain\Acquisition\SourceId;
use App\Infrastructure\Persistence\Eloquent\Acquisition\Models\DerivedClaimProposalRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use UnexpectedValueException;

final class EloquentDerivedClaimProposalRepository implements DerivedClaimProposalRepository
{
    public function add(DerivedClaimProposal $proposal, string $actor): void
    {
        if ($proposal->status !== DerivedClaimReviewStatus::Pending) {
            throw new LogicException('Only a pending proposal can be created.');
        }

        DerivedClaimProposalRecord::query()->create([
            'id' => $proposal->id,
            'source_id' => $proposal->sourceId->value,
            'subject_mention_id' => $proposal->subjectMentionId->value,
            'predicate_key' => $proposal->predicate->key->value,
            'predicate_schema_version' => $proposal->predicate->schemaVersion,
            'object_mention_id' => $proposal->objectMentionId?->value,
            'value_payload' => $proposal->value === null
                ? null : ClaimValueSerializer::serialize($proposal->value),
            'qualifiers_payload' => $proposal->qualifiers->serialize(),
            'derivation_payload' => $proposal->derivation->serialize(),
            'rule_id' => $proposal->derivation->ruleId,
            'output_key' => $proposal->derivation->outputKey,
            'fingerprint' => $proposal->fingerprint,
            'status' => $proposal->status->value,
            'accepted_claim_id' => null,
        ]);
        $this->appendDecision($proposal->id, 'pending', $actor);
    }

    public function find(SourceId $sourceId, string $proposalId): ?DerivedClaimProposal
    {
        $record = DerivedClaimProposalRecord::query()
            ->where('source_id', $sourceId->value)
            ->where('id', $proposalId)->first();

        return $record === null ? null : $this->map($record);
    }

    public function findByFingerprint(SourceId $sourceId, string $fingerprint): ?DerivedClaimProposal
    {
        $record = DerivedClaimProposalRecord::query()
            ->where('source_id', $sourceId->value)
            ->where('fingerprint', $fingerprint)->first();

        return $record === null ? null : $this->map($record);
    }

    public function forSource(SourceId $sourceId): array
    {
        $result = [];
        foreach (DerivedClaimProposalRecord::query()->where('source_id', $sourceId->value)
            ->orderBy('created_at')->orderBy('id')->get() as $record) {
            $result[] = $this->map($record);
        }

        return $result;
    }

    public function forOutput(SourceId $sourceId, string $ruleId, string $outputKey): array
    {
        $result = [];
        foreach (DerivedClaimProposalRecord::query()
            ->where('source_id', $sourceId->value)
            ->where('rule_id', $ruleId)
            ->where('output_key', $outputKey)
            ->orderBy('created_at')->orderBy('id')->get() as $record) {
            $result[] = $this->map($record);
        }

        return $result;
    }

    public function transition(
        DerivedClaimProposal $proposal,
        DerivedClaimReviewStatus $next,
        string $actor,
        ?ClaimId $acceptedClaimId = null,
        ?EvidenceStateId $evidenceStateId = null,
    ): void {
        $allowed = match ($proposal->status) {
            DerivedClaimReviewStatus::Pending => [
                DerivedClaimReviewStatus::Accepted,
                DerivedClaimReviewStatus::Rejected,
                DerivedClaimReviewStatus::Invalidated,
            ],
            DerivedClaimReviewStatus::Accepted => [DerivedClaimReviewStatus::Invalidated],
            DerivedClaimReviewStatus::Invalidated => [DerivedClaimReviewStatus::Pending],
            default => [],
        };

        if (! in_array($next, $allowed, true)
            || (($next === DerivedClaimReviewStatus::Accepted) !== ($acceptedClaimId !== null))) {
            throw new LogicException('Invalid Derived Claim proposal review transition.');
        }

        $updated = DerivedClaimProposalRecord::query()
            ->where('source_id', $proposal->sourceId->value)
            ->where('id', $proposal->id)
            ->where('status', $proposal->status->value)
            ->update([
                'status' => $next->value,
                'accepted_claim_id' => ($next === DerivedClaimReviewStatus::Invalidated
                    ? $proposal->acceptedClaimId : $acceptedClaimId)?->value,
                'updated_at' => now(),
            ]);

        if ($updated !== 1) {
            throw new LogicException('Derived Claim proposal review state changed concurrently.');
        }

        $this->appendDecision($proposal->id, $next->value, $actor, $evidenceStateId, $acceptedClaimId);
    }

    public function linkAcceptedEvidenceState(DerivedClaimProposal $proposal, EvidenceStateId $stateId): void
    {
        $updated = DB::table('derived_claim_proposal_decisions')
            ->where('proposal_id', $proposal->id)
            ->where('status', DerivedClaimReviewStatus::Accepted->value)
            ->whereNull('evidence_state_id')
            ->update(['evidence_state_id' => $stateId->value]);

        if ($updated !== 1) {
            throw new LogicException('Accepted proposal EvidenceState could not be linked atomically.');
        }
    }

    public function linkAutomaticAcceptances(SourceId $sourceId, EvidenceStateId $stateId): void
    {
        $decisionIds = DB::table('derived_claim_proposal_decisions as d')
            ->join('derived_claim_proposals as p', 'p.id', '=', 'd.proposal_id')
            ->where('p.source_id', $sourceId->value)
            ->where('d.status', DerivedClaimReviewStatus::Accepted->value)
            ->where('d.actor', 'like', 'system:rule-policy:%')
            ->whereNull('d.evidence_state_id')
            ->pluck('d.id');

        if ($decisionIds->isNotEmpty()) {
            DB::table('derived_claim_proposal_decisions')
                ->whereIn('id', $decisionIds)
                ->update(['evidence_state_id' => $stateId->value]);
        }
    }

    public function history(SourceId $sourceId, string $proposalId): array
    {
        if ($this->find($sourceId, $proposalId) === null) {
            return [];
        }

        $rows = DB::table('derived_claim_proposal_decisions')
            ->where('proposal_id', $proposalId)
            ->orderBy('sequence')->get();
        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'status' => (string) $row->status,
                'actor' => (string) $row->actor,
                'evidence_state_id' => $row->evidence_state_id === null
                    ? null : (string) $row->evidence_state_id,
                'accepted_claim_id' => $row->accepted_claim_id === null
                    ? null : (string) $row->accepted_claim_id,
                'recorded_at' => (string) $row->recorded_at,
            ];
        }

        return $result;
    }

    private function map(DerivedClaimProposalRecord $record): DerivedClaimProposal
    {
        $status = DerivedClaimReviewStatus::tryFrom((string) $record->status)
            ?? throw new UnexpectedValueException('Unknown stored Derived Claim proposal status.');

        $proposal = new DerivedClaimProposal(
            id: (string) $record->id,
            sourceId: new SourceId((string) $record->source_id),
            subjectMentionId: new MentionId((string) $record->subject_mention_id),
            predicate: PredicateVocabulary::get(
                (string) $record->predicate_key,
                (int) $record->predicate_schema_version,
            ),
            derivation: ClaimDerivation::deserialize((string) $record->derivation_payload),
            status: $status,
            objectMentionId: $record->object_mention_id === null
                ? null : new MentionId((string) $record->object_mention_id),
            value: $record->value_payload === null
                ? null : ClaimValueSerializer::deserialize((string) $record->value_payload),
            qualifiers: ClaimQualifiers::deserialize((string) $record->qualifiers_payload),
            acceptedClaimId: $record->accepted_claim_id === null
                ? null : new ClaimId((string) $record->accepted_claim_id),
        );

        if ($proposal->fingerprint !== (string) $record->fingerprint
            || $proposal->derivation->ruleId !== (string) $record->rule_id
            || $proposal->derivation->outputKey !== (string) $record->output_key) {
            throw new UnexpectedValueException('Stored Derived Claim proposal fingerprint or identity mismatch.');
        }

        return $proposal;
    }

    private function appendDecision(
        string $proposalId,
        string $status,
        string $actor,
        ?EvidenceStateId $evidenceStateId = null,
        ?ClaimId $acceptedClaimId = null,
    ): void {
        $sequence = (int) DB::table('derived_claim_proposal_decisions')
            ->where('proposal_id', $proposalId)
            ->max('sequence') + 1;

        DB::table('derived_claim_proposal_decisions')->insert([
            'id' => (string) Str::uuid(),
            'proposal_id' => $proposalId,
            'sequence' => $sequence,
            'status' => $status,
            'actor' => $actor,
            'evidence_state_id' => $evidenceStateId?->value,
            'accepted_claim_id' => $acceptedClaimId?->value,
            'recorded_at' => now(),
        ]);
    }
}
