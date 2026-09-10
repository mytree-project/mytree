<?php

declare(strict_types=1);

namespace App\Domain\Acquisition;

use InvalidArgumentException;

final readonly class EvidenceStateSnapshot
{
    public const SCHEMA_ID = 'mytree.evidence-state.v1';

    public const SCHEMA_VERSION = 1;

    private function __construct(
        public int $schemaVersion,
        public string $canonicalPayload,
        public string $payloadHash,
    ) {}

    /**
     * @param  list<EvidenceStateSourceRevision>  $sourceRevisions
     * @param  list<MentionRevisionId>  $mentionRevisionIds
     * @param  list<ClaimRevisionId>  $claimRevisionIds
     */
    public static function capture(
        array $sourceRevisions,
        array $mentionRevisionIds,
        array $claimRevisionIds,
    ): self {
        $sourceEntries = array_map(
            static fn (EvidenceStateSourceRevision $revision): array => [
                'source_id' => $revision->sourceId->value,
                'revision_number' => $revision->revisionNumber,
            ],
            $sourceRevisions,
        );
        usort(
            $sourceEntries,
            static fn (array $left, array $right): int => [$left['source_id'], $left['revision_number']]
                <=> [$right['source_id'], $right['revision_number']],
        );

        $mentionIds = array_map(
            static fn (MentionRevisionId $id): string => $id->value,
            $mentionRevisionIds,
        );
        sort($mentionIds, SORT_STRING);

        $claimIds = array_map(
            static fn (ClaimRevisionId $id): string => $id->value,
            $claimRevisionIds,
        );
        sort($claimIds, SORT_STRING);

        self::assertUniqueSourceRevisions($sourceEntries);
        self::assertUniqueIds($mentionIds, 'MentionRevision');
        self::assertUniqueIds($claimIds, 'ClaimRevision');

        $payload = [
            'schema' => self::SCHEMA_ID,
            'source_revisions' => $sourceEntries,
            'mention_revision_ids' => $mentionIds,
            'claim_revision_ids' => $claimIds,
        ];
        $canonicalPayload = CanonicalJson::encode($payload);

        return new self(
            schemaVersion: self::SCHEMA_VERSION,
            canonicalPayload: $canonicalPayload,
            payloadHash: hash('sha256', $canonicalPayload),
        );
    }

    public static function rehydrate(
        int $schemaVersion,
        string $canonicalPayload,
        string $payloadHash,
    ): self {
        if ($schemaVersion !== self::SCHEMA_VERSION) {
            throw new InvalidArgumentException(sprintf(
                'Unsupported EvidenceState snapshot schema version %d.',
                $schemaVersion,
            ));
        }

        $normalizedHash = strtolower($payloadHash);

        if (preg_match('/^[0-9a-f]{64}$/D', $normalizedHash) !== 1) {
            throw new InvalidArgumentException('EvidenceState payload hash must be a SHA-256 hexadecimal value.');
        }

        if (! hash_equals($normalizedHash, hash('sha256', $canonicalPayload))) {
            throw new InvalidArgumentException('EvidenceState payload hash does not match the stored payload.');
        }

        $payload = CanonicalJson::decodeObject($canonicalPayload);

        if (($payload['schema'] ?? null) !== self::SCHEMA_ID) {
            throw new InvalidArgumentException('Unsupported EvidenceState snapshot schema identifier.');
        }

        if (CanonicalJson::encode($payload) !== $canonicalPayload) {
            throw new InvalidArgumentException('Stored EvidenceState payload is not in canonical form.');
        }

        $snapshot = new self(
            schemaVersion: $schemaVersion,
            canonicalPayload: $canonicalPayload,
            payloadHash: $normalizedHash,
        );
        $snapshot->reconstruct();

        return $snapshot;
    }

    public function reconstruct(): EvidenceStateManifest
    {
        $payload = CanonicalJson::decodeObject($this->canonicalPayload);

        if (($payload['schema'] ?? null) !== self::SCHEMA_ID) {
            throw new InvalidArgumentException('Unsupported EvidenceState snapshot schema identifier.');
        }

        $sourceRevisions = [];
        foreach (self::objectListAt($payload, 'source_revisions') as $sourceRevision) {
            $sourceRevisions[] = new EvidenceStateSourceRevision(
                sourceId: new SourceId(self::stringAt($sourceRevision, 'source_id')),
                revisionNumber: self::intAt($sourceRevision, 'revision_number'),
            );
        }

        $mentionRevisionIds = array_map(
            static fn (string $id): MentionRevisionId => new MentionRevisionId($id),
            self::stringListAt($payload, 'mention_revision_ids'),
        );
        $claimRevisionIds = array_map(
            static fn (string $id): ClaimRevisionId => new ClaimRevisionId($id),
            self::stringListAt($payload, 'claim_revision_ids'),
        );

        return new EvidenceStateManifest(
            sourceRevisions: $sourceRevisions,
            mentionRevisionIds: $mentionRevisionIds,
            claimRevisionIds: $claimRevisionIds,
        );
    }

    /** @param list<array{source_id: string, revision_number: int}> $entries */
    private static function assertUniqueSourceRevisions(array $entries): void
    {
        $seen = [];

        foreach ($entries as $entry) {
            if (isset($seen[$entry['source_id']])) {
                throw new InvalidArgumentException('EvidenceState may contain only one SourceRevision per Source.');
            }

            $seen[$entry['source_id']] = true;
        }
    }

    /** @param list<string> $ids */
    private static function assertUniqueIds(array $ids, string $kind): void
    {
        if (count(array_unique($ids)) !== count($ids)) {
            throw new InvalidArgumentException(sprintf('EvidenceState %s identities must be unique.', $kind));
        }
    }

    /**
     * @param  array<string, mixed>  $object
     * @return list<array<string, mixed>>
     */
    private static function objectListAt(array $object, string $key): array
    {
        $value = $object[$key] ?? null;

        if (! is_array($value) || ! array_is_list($value)) {
            throw new InvalidArgumentException(sprintf('Stored EvidenceState "%s" must be a JSON array.', $key));
        }

        $result = [];
        foreach ($value as $index => $entry) {
            if (! is_array($entry) || ($entry !== [] && array_is_list($entry))) {
                throw new InvalidArgumentException(sprintf('Stored EvidenceState "%s.%d" must be a JSON object.', $key, $index));
            }

            $result[] = $entry;
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $object
     * @return list<string>
     */
    private static function stringListAt(array $object, string $key): array
    {
        $value = $object[$key] ?? null;

        if (! is_array($value) || ! array_is_list($value)) {
            throw new InvalidArgumentException(sprintf('Stored EvidenceState "%s" must be a JSON array.', $key));
        }

        $result = [];
        foreach ($value as $entry) {
            if (! is_string($entry)) {
                throw new InvalidArgumentException(sprintf('Stored EvidenceState "%s" entries must be strings.', $key));
            }

            $result[] = $entry;
        }

        return $result;
    }

    /** @param array<string, mixed> $object */
    private static function stringAt(array $object, string $key): string
    {
        $value = $object[$key] ?? null;

        if (! is_string($value)) {
            throw new InvalidArgumentException(sprintf('Stored EvidenceState "%s" must be a string.', $key));
        }

        return $value;
    }

    /** @param array<string, mixed> $object */
    private static function intAt(array $object, string $key): int
    {
        $value = $object[$key] ?? null;

        if (! is_int($value)) {
            throw new InvalidArgumentException(sprintf('Stored EvidenceState "%s" must be an integer.', $key));
        }

        return $value;
    }
}
