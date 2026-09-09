<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Acquisition;

use App\Domain\Acquisition\ClaimRevisionId;
use App\Domain\Acquisition\EvidenceStateSnapshot;
use App\Domain\Acquisition\EvidenceStateSourceRevision;
use App\Domain\Acquisition\MentionRevisionId;
use App\Domain\Acquisition\SourceId;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class EvidenceStateSnapshotTest extends TestCase
{
    public function test_equivalent_revision_sets_have_identical_payload_and_hash_regardless_of_input_order(): void
    {
        $sourceA = new EvidenceStateSourceRevision(
            new SourceId('11111111-1111-4111-8111-111111111111'),
            3,
        );
        $sourceB = new EvidenceStateSourceRevision(
            new SourceId('22222222-2222-4222-8222-222222222222'),
            2,
        );
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

    public function test_snapshot_reconstructs_exact_revision_manifest(): void
    {
        $sourceId = new SourceId('11111111-1111-4111-8111-111111111111');
        $mentionRevisionId = new MentionRevisionId('33333333-3333-4333-8333-333333333333');
        $claimRevisionId = new ClaimRevisionId('55555555-5555-4555-8555-555555555555');
        $snapshot = EvidenceStateSnapshot::capture(
            [new EvidenceStateSourceRevision($sourceId, 7)],
            [$mentionRevisionId],
            [$claimRevisionId],
        );

        $manifest = $snapshot->reconstruct();

        self::assertCount(1, $manifest->sourceRevisions);
        self::assertSame($sourceId->value, $manifest->sourceRevisions[0]->sourceId->value);
        self::assertSame(7, $manifest->sourceRevisions[0]->revisionNumber);
        self::assertSame($mentionRevisionId->value, $manifest->mentionRevisionIds[0]->value);
        self::assertSame($claimRevisionId->value, $manifest->claimRevisionIds[0]->value);
    }

    public function test_snapshot_rejects_duplicate_source_identity(): void
    {
        $sourceId = new SourceId('11111111-1111-4111-8111-111111111111');

        $this->expectException(InvalidArgumentException::class);

        EvidenceStateSnapshot::capture(
            [
                new EvidenceStateSourceRevision($sourceId, 1),
                new EvidenceStateSourceRevision($sourceId, 2),
            ],
            [],
            [],
        );
    }

    public function test_rehydrate_rejects_hash_mismatch(): void
    {
        $snapshot = EvidenceStateSnapshot::capture(
            [new EvidenceStateSourceRevision(
                new SourceId('11111111-1111-4111-8111-111111111111'),
                1,
            )],
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
}
