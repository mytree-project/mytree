<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Acquisition\CreateClaim;
use App\Application\Acquisition\CreateMention;
use App\Application\Acquisition\CreateSource;
use App\Application\Acquisition\InvalidSourceDraft;
use App\Application\Acquisition\LoadSourceDraft;
use App\Application\Acquisition\SaveSourceDraft;
use App\Application\Acquisition\SourceDraftChanges;
use App\Application\Acquisition\SourceDraftConflict;
use App\Application\Acquisition\SourceDraftSourceChanges;
use App\Application\Acquisition\SourceDraftValidationIssue;
use App\Application\Acquisition\SourceIdentifierGenerator;
use App\Application\Acquisition\UpdateSource;
use App\Application\Acquisition\ValidateSourceDraft;
use App\Domain\Acquisition\Claim;
use App\Domain\Acquisition\Mention;
use App\Domain\Acquisition\MentionKind;
use App\Domain\Acquisition\PredicateKey;
use App\Domain\Acquisition\PredicateVocabulary;
use App\Domain\Acquisition\SourceMetadata;
use App\Domain\Acquisition\SourceType;
use App\Domain\Acquisition\TextClaimValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

final class SourceDraftApplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_blank_draft_is_saved_atomically_with_initial_revision_and_evidence_state(): void
    {
        $draft = app(LoadSourceDraft::class)->blank(
            type: new SourceType('civil.birth'),
            metadata: new SourceMetadata(['act_number_raw' => '7?']),
        )->withChanges(SourceDraftChanges::none(), 'Initial acquisition', 'researcher');

        $result = app(SaveSourceDraft::class)->handle($draft);

        self::assertTrue($result->changed);
        self::assertNotNull($result->evidenceStateId);
        self::assertFalse($result->draft->isNew);
        self::assertSame('7?', $result->draft->current->source->metadata->toArray()['act_number_raw']);
        $this->assertDatabaseHas('sources', ['id' => $result->draft->current->source->id->value]);
        $this->assertDatabaseCount('source_revisions', 1);
        $this->assertDatabaseCount('evidence_states', 1);
    }

    public function test_draft_can_add_and_remove_source_local_evidence_without_rewriting_history(): void
    {
        $source = app(CreateSource::class)->handle(SourceType::generic());
        $ids = app(SourceIdentifierGenerator::class);
        $mention = new Mention(
            id: $ids->mentionId(),
            sourceId: $source->id,
            kind: MentionKind::person(),
            localKey: 'person.subject',
        );
        $claim = new Claim(
            id: $ids->claimId(),
            sourceId: $source->id,
            subjectMentionId: $mention->id,
            predicate: PredicateVocabulary::get(PredicateKey::PersonSurname),
            value: new TextClaimValue('Kowalski'),
        );

        $created = app(SaveSourceDraft::class)->handle(
            app(LoadSourceDraft::class)->handle($source->id)->withChanges(
                new SourceDraftChanges(
                    addMentions: [$mention],
                    addClaims: [$claim],
                ),
            ),
        );

        self::assertCount(1, $created->draft->current->mentions);
        self::assertCount(1, $created->draft->current->claims);
        $this->assertDatabaseCount('mention_revisions', 1);
        $this->assertDatabaseCount('claim_revisions', 1);

        $removed = app(SaveSourceDraft::class)->handle(
            $created->draft->withChanges(
                new SourceDraftChanges(
                    removeMentionIds: [$mention->id],
                    removeClaimIds: [$claim->id],
                ),
            ),
        );

        self::assertCount(0, $removed->draft->current->mentions);
        self::assertCount(0, $removed->draft->current->claims);
        $this->assertDatabaseCount('mention_revisions', 1);
        $this->assertDatabaseCount('claim_revisions', 1);
        $this->assertDatabaseCount('evidence_states', 2);
    }

    public function test_semantic_noop_does_not_create_revisions_or_evidence_state(): void
    {
        $source = app(CreateSource::class)->handle(SourceType::generic());
        $draft = app(LoadSourceDraft::class)->handle($source->id);

        $beforeRevisions = $this->tableCount('source_revisions');
        $beforeEvidenceStates = $this->tableCount('evidence_states');
        $result = app(SaveSourceDraft::class)->handle($draft);

        self::assertFalse($result->changed);
        self::assertSame($beforeRevisions, $this->tableCount('source_revisions'));
        self::assertSame($beforeEvidenceStates, $this->tableCount('evidence_states'));
    }

    public function test_source_change_appends_revision_and_captures_new_evidence_state(): void
    {
        $source = app(CreateSource::class)->handle(SourceType::generic());
        $draft = app(LoadSourceDraft::class)->handle($source->id)->withChanges(
            new SourceDraftChanges(
                source: new SourceDraftSourceChanges(
                    metadata: new SourceMetadata(['archive_reference' => 'Fond 12 / Act 7']),
                ),
            ),
            changeNote: 'Add archive reference',
            changedBy: 'researcher',
        );

        $result = app(SaveSourceDraft::class)->handle($draft);

        self::assertTrue($result->changed);
        self::assertNotNull($result->evidenceStateId);
        self::assertSame(
            'Fond 12 / Act 7',
            $result->draft->current->source->metadata->toArray()['archive_reference'],
        );
        $this->assertDatabaseCount('source_revisions', 2);
        $this->assertDatabaseCount('evidence_states', 1);
    }

    public function test_removing_referenced_mention_is_reported_before_any_write(): void
    {
        $source = app(CreateSource::class)->handle(SourceType::generic());
        $person = app(CreateMention::class)->handle($source->id, MentionKind::person(), 'person.subject');
        app(CreateClaim::class)->handle(
            sourceId: $source->id,
            subjectMentionId: $person->id,
            predicate: PredicateVocabulary::get(PredicateKey::PersonOccupation),
            value: new TextClaimValue('rolnik'),
        );

        $draft = app(LoadSourceDraft::class)->handle($source->id)->withChanges(
            new SourceDraftChanges(removeMentionIds: [$person->id]),
        );
        $validation = app(ValidateSourceDraft::class)->handle($draft);

        self::assertFalse($validation->isValid());
        self::assertContains('draft.claim.subject_missing', array_map(
            static fn (SourceDraftValidationIssue $issue): string => $issue->code,
            $validation->issues,
        ));

        $beforeEvidenceStates = $this->tableCount('evidence_states');
        try {
            app(SaveSourceDraft::class)->handle($draft);
            self::fail('Expected invalid SourceDraft to be rejected.');
        } catch (InvalidSourceDraft) {
            self::assertSame($beforeEvidenceStates, $this->tableCount('evidence_states'));
            $this->assertDatabaseHas('mentions', ['id' => $person->id->value]);
        }
    }

    public function test_stale_base_state_is_rejected_instead_of_overwriting_newer_data(): void
    {
        $source = app(CreateSource::class)->handle(SourceType::generic());
        $stale = app(LoadSourceDraft::class)->handle($source->id)->withChanges(
            new SourceDraftChanges(
                source: new SourceDraftSourceChanges(
                    metadata: new SourceMetadata(['note' => 'stale edit']),
                ),
            ),
        );

        app(UpdateSource::class)->handle(
            id: $source->id,
            type: SourceType::generic(),
            metadata: new SourceMetadata(['note' => 'newer edit']),
        );

        $this->expectException(SourceDraftConflict::class);
        app(SaveSourceDraft::class)->handle($stale);
    }

    public function test_exception_during_save_rolls_back_source_and_revision_writes(): void
    {
        $draft = app(LoadSourceDraft::class)->blank(SourceType::generic())
            ->withChanges(SourceDraftChanges::none(), changedBy: '   ');
        $sourceId = $draft->current->source->id->value;

        try {
            app(SaveSourceDraft::class)->handle($draft);
            self::fail('Expected invalid revision attribution to fail the transaction.');
        } catch (InvalidArgumentException) {
            $this->assertDatabaseMissing('sources', ['id' => $sourceId]);
            $this->assertDatabaseCount('source_revisions', 0);
            $this->assertDatabaseCount('evidence_states', 0);
        }
    }

    private function tableCount(string $table): int
    {
        return (int) DB::table($table)->count();
    }
}
