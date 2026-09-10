<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Acquisition\CaptureEvidenceStateForSource;
use App\Application\Acquisition\CreateClaim;
use App\Application\Acquisition\CreateMention;
use App\Application\Acquisition\CreateSource;
use App\Application\Acquisition\GetEvidenceState;
use App\Application\Acquisition\SourceRevisionRepository;
use App\Domain\Acquisition\CanonicalJson;
use App\Domain\Acquisition\ClaimCertainty;
use App\Domain\Acquisition\ClaimOrigin;
use App\Domain\Acquisition\ClaimOriginKind;
use App\Domain\Acquisition\ClaimQualifiers;
use App\Domain\Acquisition\EvidenceStateId;
use App\Domain\Acquisition\EvidenceStateSnapshot;
use App\Domain\Acquisition\MentionKind;
use App\Domain\Acquisition\MentionRawData;
use App\Domain\Acquisition\PredicateKey;
use App\Domain\Acquisition\PredicateVocabulary;
use App\Domain\Acquisition\SourceMetadata;
use App\Domain\Acquisition\SourceType;
use App\Domain\Acquisition\TextClaimValue;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class EvidenceStateApplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_source_state_can_be_captured_and_reconstructed_from_immutable_revisions(): void
    {
        $source = app(CreateSource::class)->handle(
            new SourceType('civil.birth'),
            new SourceMetadata(['title' => 'Birth record']),
        );
        $mention = app(CreateMention::class)->handle(
            sourceId: $source->id,
            kind: new MentionKind('person'),
            localKey: 'person-1',
            rawData: new MentionRawData(['descriptor' => 'włościanin']),
        );
        app(CreateClaim::class)->handle(
            sourceId: $source->id,
            subjectMentionId: $mention->id,
            predicate: PredicateVocabulary::get(PredicateKey::PersonOccupation),
            value: new TextClaimValue('włościanin'),
            qualifiers: new ClaimQualifiers,
            rawText: 'włościanin',
            origin: new ClaimOrigin(ClaimOriginKind::ManualDirectSource),
            transcriptionCertainty: new ClaimCertainty('certain'),
            interpretationCertainty: new ClaimCertainty('certain'),
        );

        $evidenceState = app(CaptureEvidenceStateForSource::class)->capture($source->id);
        $resolved = app(GetEvidenceState::class)->get($evidenceState->id);

        self::assertSame(EvidenceStateSnapshot::SCHEMA_VERSION, $evidenceState->snapshot->schemaVersion);
        self::assertCount(1, $evidenceState->snapshot->sourceRevisionIds);
        self::assertCount(1, $resolved->sourceRevisions);
        self::assertCount(1, $resolved->mentionRevisions);
        self::assertCount(1, $resolved->claimRevisions);
        self::assertSame($evidenceState->snapshot->sourceRevisionIds[0]->value, $resolved->sourceRevisions[0]->id->value);
        self::assertSame($evidenceState->snapshot->payloadHash, $resolved->evidenceState->snapshot->payloadHash);
    }

    public function test_legacy_v1_evidence_state_remains_resolvable_without_rewriting_payload_or_hash(): void
    {
        $source = app(CreateSource::class)->handle(
            SourceType::generic(),
            new SourceMetadata(['title' => 'Legacy state source']),
        );
        $sourceRevision = app(SourceRevisionRepository::class)->latestForSource($source->id);
        self::assertNotNull($sourceRevision);

        $payload = CanonicalJson::encode([
            'schema' => EvidenceStateSnapshot::LEGACY_SCHEMA_ID,
            'source_revisions' => [[
                'source_id' => $source->id->value,
                'revision_number' => $sourceRevision->revisionNumber,
            ]],
            'mention_revision_ids' => [],
            'claim_revision_ids' => [],
        ]);
        $hash = hash('sha256', $payload);
        $evidenceStateId = new EvidenceStateId('11111111-1111-4111-8111-111111111111');

        DB::table('evidence_states')->insert([
            'id' => $evidenceStateId->value,
            'snapshot_schema_version' => EvidenceStateSnapshot::LEGACY_SCHEMA_VERSION,
            'canonical_payload' => $payload,
            'payload_hash' => $hash,
            'recorded_at' => new DateTimeImmutable('2026-09-10T12:00:00+00:00'),
            'change_note' => null,
            'changed_by' => null,
        ]);
        DB::table('evidence_state_source_revisions')->insert([
            'evidence_state_id' => $evidenceStateId->value,
            'source_id' => $source->id->value,
            'revision_number' => $sourceRevision->revisionNumber,
            'source_revision_id' => $sourceRevision->id->value,
        ]);

        $resolved = app(GetEvidenceState::class)->get($evidenceStateId);

        self::assertSame(EvidenceStateSnapshot::LEGACY_SCHEMA_VERSION, $resolved->evidenceState->snapshot->schemaVersion);
        self::assertSame($payload, $resolved->evidenceState->snapshot->canonicalPayload);
        self::assertSame($hash, $resolved->evidenceState->snapshot->payloadHash);
        self::assertSame($sourceRevision->id->value, $resolved->sourceRevisions[0]->id->value);
    }
}
