<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Acquisition;

use App\Domain\Acquisition\Mention;
use App\Domain\Acquisition\MentionId;
use App\Domain\Acquisition\MentionKind;
use App\Domain\Acquisition\MentionRawData;
use App\Domain\Acquisition\MentionRevisionSnapshot;
use App\Domain\Acquisition\SourceId;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class MentionRevisionSnapshotTest extends TestCase
{
    public function test_semantically_equivalent_raw_data_produces_the_same_canonical_payload_and_hash(): void
    {
        $left = $this->mention(new MentionRawData([
            'surname' => 'Gajda',
            'descriptor' => [
                'status' => 'однодворец',
                'language' => 'ru',
            ],
        ]));
        $right = $this->mention(new MentionRawData([
            'descriptor' => [
                'language' => 'ru',
                'status' => 'однодворец',
            ],
            'surname' => 'Gajda',
        ]));

        $leftSnapshot = MentionRevisionSnapshot::capture($left);
        $rightSnapshot = MentionRevisionSnapshot::capture($right);

        self::assertSame($leftSnapshot->canonicalPayload, $rightSnapshot->canonicalPayload);
        self::assertSame($leftSnapshot->payloadHash, $rightSnapshot->payloadHash);
    }

    public function test_snapshot_reconstructs_the_exact_mention_without_current_persistence_state(): void
    {
        $mention = $this->mention(new MentionRawData([
            'descriptor' => 'włościanin ze wsi XYZ',
        ]));
        $snapshot = MentionRevisionSnapshot::capture($mention);
        $rehydrated = MentionRevisionSnapshot::rehydrate(
            schemaVersion: $snapshot->schemaVersion,
            canonicalPayload: $snapshot->canonicalPayload,
            payloadHash: $snapshot->payloadHash,
        );

        $restored = $rehydrated->reconstruct();

        self::assertSame($mention->id->value, $restored->id->value);
        self::assertSame($mention->sourceId->value, $restored->sourceId->value);
        self::assertSame($mention->kind->key, $restored->kind->key);
        self::assertSame($mention->localKey, $restored->localKey);
        self::assertSame($mention->role, $restored->role);
        self::assertSame($mention->displayLabel, $restored->displayLabel);
        self::assertSame($mention->rawData->toArray(), $restored->rawData->toArray());
    }

    public function test_snapshot_rejects_an_unsupported_schema_version(): void
    {
        $snapshot = MentionRevisionSnapshot::capture($this->mention(MentionRawData::empty()));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported MentionRevision snapshot schema version');

        MentionRevisionSnapshot::rehydrate(
            schemaVersion: 2,
            canonicalPayload: $snapshot->canonicalPayload,
            payloadHash: $snapshot->payloadHash,
        );
    }

    public function test_snapshot_rejects_a_payload_hash_mismatch(): void
    {
        $snapshot = MentionRevisionSnapshot::capture($this->mention(MentionRawData::empty()));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('payload hash does not match');

        MentionRevisionSnapshot::rehydrate(
            schemaVersion: $snapshot->schemaVersion,
            canonicalPayload: $snapshot->canonicalPayload,
            payloadHash: str_repeat('0', 64),
        );
    }

    private function mention(MentionRawData $rawData): Mention
    {
        return new Mention(
            id: new MentionId('11111111-1111-4111-8111-111111111111'),
            sourceId: new SourceId('22222222-2222-4222-8222-222222222222'),
            kind: MentionKind::person(),
            localKey: 'person.subject',
            role: 'subject',
            displayLabel: 'Jan Gajda',
            rawData: $rawData,
        );
    }
}
