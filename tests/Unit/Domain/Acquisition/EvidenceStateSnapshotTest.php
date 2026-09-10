<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Acquisition;

use App\Domain\Acquisition\CanonicalJson;
use App\Domain\Acquisition\ClaimRevisionId;
use App\Domain\Acquisition\EvidenceStateSnapshot;
use App\Domain\Acquisition\MentionRevisionId;
use App\Domain\Acquisition\SourceRevisionId;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class EvidenceStateSnapshotTest extends TestCase
{
    public function test_equivalent_revision_sets_have_identical_payload_and_hash_regardless_of_input_order(): void
    {
        $sourceA = new SourceRevisionId('11111111-1111-4111-8111-111111111111');
        $sourceB = new SourceRevisionId('22222222-2222-4222-8222-222222222222');
        $mentionA = new MentionRevisionId('33333333-3333-4333-8333-333333333333');
        $mentionB = new MentionRevisionId('44444444-4444-4444-8444-444444444444');
        $claimA = new ClaimRevisionId('55555555-5555-4555-8555-555555555555');
        $claimB = new ClaimRevisionId('66666666-6666-4666-8666-666666666666');

        $left = EvidenceStateSnapshot::capture(
            [$sourceA, $sourceB],
            [$mentionA, $mentionB],
            [$claimA, $claimB],
        );
        $right = EvidenceStateSnapshot::capture(
            [$sourceB, $sourceA],
            [$mentionB, $mentionA],
            [$claimB, $claimA],
        );

        self::assertSame($left->canonicalPayload, $right->canonicalPayload);
        self::assertSame($left->payloadHash, $right->payloadHash);
    }

    public function test_snapshot_exposes_exact_typed_revision_identities_directly(): void
    {
        $sourceRevisionId = new SourceRevisionId('11111111-1111-4111-8111-111111111111');
        $mentionRevisionId = new MentionRevisionId('33333333-3333-4333-8333-333333333333');
        $claimRevisionId = new ClaimRevisionId('55555555-5555-4555-8555-555555555555');
        $snapshot = EvidenceStateSnapshot::capture(
            [$sourceRevisionId],
            [$mentionRevisionId],
            [$claimRevisionId],
        );

        self::assertSame($sourceRevisionId->value, $snapshot->sourceRevisionIds[0]->value);
        self::assertSame($mentionRevisionId->value, $snapshot->mentionRevisionIds[0]->value);
        self::assertSame($claimRevisionId->value, $snapshot->claimRevisionIds[0]->value);
        self::assertSame(EvidenceStateSnapshot::SCHEMA_VERSION, $snapshot->schemaVersion);
    }

    public function test_snapshot_rejects_duplicate_revision_identity(): void
    {
        $sourceRevisionId = new SourceRevisionId('11111111-1111-4111-8111-111111111111');

        $this->expectException(InvalidArgumentException::class);

        EvidenceStateSnapshot::capture(
            [$sourceRevisionId, $sourceRevisionId],
            [],
            [],
        );
    }

    public function test_rehydrate_rejects_hash_mismatch(): void
    {
        $snapshot = EvidenceStateSnapshot::capture(
            [new SourceRevisionId('11111111-1111-4111-8111-111111111111')],
            [],
            [],
        );

        $this->expectException(InvalidArgumentException::class);

        EvidenceStateSnapshot::rehydrate(
            $snapshot->schemaVersion,
            $snapshot->canonicalPayload,
            str_repeat('0', 64),
        );
    }

    public function test_legacy_v1_snapshot_keeps_original_payload_and_hash_while_exposing_backfilled_source_revision_id(): void
    {
        $payload = CanonicalJson::encode([
            'schema' => EvidenceStateSnapshot::LEGACY_SCHEMA_ID,
            'source_revisions' => [[
                'source_id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
                'revision_number' => 7,
            ]],
            'mention_revision_ids' => [],
            'claim_revision_ids' => [],
        ]);
        $hash = hash('sha256', $payload);
        $sourceRevisionId = new SourceRevisionId('11111111-1111-4111-8111-111111111111');

        $snapshot = EvidenceStateSnapshot::rehydrate(
            EvidenceStateSnapshot::LEGACY_SCHEMA_VERSION,
            $payload,
            $hash,
            [$sourceRevisionId],
        );

        self::assertSame(EvidenceStateSnapshot::LEGACY_SCHEMA_VERSION, $snapshot->schemaVersion);
        self::assertSame($payload, $snapshot->canonicalPayload);
        self::assertSame($hash, $snapshot->payloadHash);
        self::assertSame($sourceRevisionId->value, $snapshot->sourceRevisionIds[0]->value);
        self::assertSame('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', $snapshot->legacySourceReferences()[0]['sourceId']->value);
        self::assertSame(7, $snapshot->legacySourceReferences()[0]['revisionNumber']);
    }
}
