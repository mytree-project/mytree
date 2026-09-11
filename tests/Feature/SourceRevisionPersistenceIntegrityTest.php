<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Acquisition\CaptureEvidenceStateForSource;
use App\Application\Acquisition\CreateSource;
use App\Application\Acquisition\SourceRevisionRepository;
use App\Domain\Acquisition\SourceType;
use DateTimeImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class SourceRevisionPersistenceIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_source_revision_identity_is_required_after_backfill(): void
    {
        $source = app(CreateSource::class)->handle(SourceType::generic());
        $revision = app(SourceRevisionRepository::class)->latestForSource($source->id);

        self::assertNotNull($revision);

        $this->expectException(QueryException::class);

        DB::table('source_revisions')->insert([
            'revision_id' => null,
            'source_id' => $source->id->value,
            'revision_number' => 2,
            'snapshot_schema_version' => $revision->snapshot->schemaVersion,
            'canonical_payload' => $revision->snapshot->canonicalPayload,
            'payload_hash' => $revision->snapshot->payloadHash,
            'recorded_at' => new DateTimeImmutable('2026-09-11T08:00:00+00:00'),
            'change_note' => null,
            'changed_by' => null,
        ]);
    }

    public function test_evidence_state_source_revision_reference_is_required_after_backfill(): void
    {
        $source = app(CreateSource::class)->handle(SourceType::generic());
        $evidenceState = app(CaptureEvidenceStateForSource::class)->capture($source->id);
        $revision = app(SourceRevisionRepository::class)->latestForSource($source->id);

        self::assertNotNull($revision);

        $secondEvidenceStateId = '11111111-1111-4111-8111-111111111111';
        DB::table('evidence_states')->insert([
            'id' => $secondEvidenceStateId,
            'snapshot_schema_version' => $evidenceState->snapshot->schemaVersion,
            'canonical_payload' => $evidenceState->snapshot->canonicalPayload,
            'payload_hash' => $evidenceState->snapshot->payloadHash,
            'recorded_at' => new DateTimeImmutable('2026-09-11T08:00:00+00:00'),
            'change_note' => null,
            'changed_by' => null,
        ]);

        $this->expectException(QueryException::class);

        DB::table('evidence_state_source_revisions')->insert([
            'evidence_state_id' => $secondEvidenceStateId,
            'source_id' => $source->id->value,
            'revision_number' => $revision->revisionNumber,
            'source_revision_id' => null,
        ]);
    }
}
