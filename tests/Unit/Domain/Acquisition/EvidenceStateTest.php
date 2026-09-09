<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Acquisition;

use App\Domain\Acquisition\EvidenceState;
use App\Domain\Acquisition\EvidenceStateId;
use App\Domain\Acquisition\EvidenceStateSnapshot;
use App\Domain\Acquisition\EvidenceStateSourceRevision;
use App\Domain\Acquisition\SourceId;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class EvidenceStateTest extends TestCase
{
    public function test_identity_is_distinct_from_semantic_snapshot(): void
    {
        $snapshot = EvidenceStateSnapshot::capture(
            [new EvidenceStateSourceRevision(
                new SourceId('11111111-1111-4111-8111-111111111111'),
                1,
            )],
            [],
            [],
        );
        $first = new EvidenceState(
            new EvidenceStateId('22222222-2222-4222-8222-222222222222'),
            $snapshot,
            new DateTimeImmutable('2026-09-09T10:00:00+00:00'),
        );
        $second = new EvidenceState(
            new EvidenceStateId('33333333-3333-4333-8333-333333333333'),
            $snapshot,
            new DateTimeImmutable('2026-09-09T11:00:00+00:00'),
        );

        self::assertNotSame($first->id->value, $second->id->value);
        self::assertSame($first->snapshot->canonicalPayload, $second->snapshot->canonicalPayload);
        self::assertSame($first->snapshot->payloadHash, $second->snapshot->payloadHash);
    }
}
