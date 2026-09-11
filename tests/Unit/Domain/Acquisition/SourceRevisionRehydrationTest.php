<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Acquisition;

use App\Domain\Acquisition\CanonicalJson;
use App\Domain\Acquisition\SourceRevisionSnapshot;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class SourceRevisionRehydrationTest extends TestCase
{
    public function test_canonical_hash_valid_but_structurally_incomplete_snapshot_is_rejected(): void
    {
        $payload = CanonicalJson::encode([
            'schema' => SourceRevisionSnapshot::SCHEMA_ID,
            'source' => [],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Stored SourceRevision "id" must be a string.');

        SourceRevisionSnapshot::rehydrate(
            schemaVersion: SourceRevisionSnapshot::SCHEMA_VERSION,
            canonicalPayload: $payload,
            payloadHash: hash('sha256', $payload),
        );
    }
}
