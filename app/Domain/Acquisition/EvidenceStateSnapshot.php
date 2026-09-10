<?php

declare(strict_types=1);

namespace App\Domain\Acquisition;

use InvalidArgumentException;

final readonly class EvidenceStateSnapshot
{
    public const SCHEMA_ID = 'mytree.evidence-state.v2';

    public const SCHEMA_VERSION = 2;

    public const LEGACY_SCHEMA_ID = 'mytree.evidence-state.v1';

    public const LEGACY_SCHEMA_VERSION = 1;

    /**
     * @param  list<SourceRevisionId>  $sourceRevisionIds
     * @param  list<MentionRevisionId>  $mentionRevisionIds
     * @param  list<ClaimRevisionId>  $claimRevisionIds
     */
    private function __construct(
        public int $schemaVersion,
        public array $sourceRevisionIds,
        public array $mentionRevisionIds,
        public array $claimRevisionIds,
        public string $canonicalPayload,
        public string $payloadHash,
    ) {}

    /**
     * @param  list<SourceRevisionId>  $sourceRevisionIds
     * @param  list<MentionRevisionId>  $mentionRevisionIds
     * @param  list<ClaimRevisionId>  $claimRevisionIds
     */
    public static function capture(
        array $sourceRevisionIds,
        array $mentionRevisionIds,
        array $claimRevisionIds,
    ): self {
        $sourceIds = self::normalizedIdValues($sourceRevisionIds, SourceRevisionId::class, 'SourceRevision');
        $mentionIds = self::normalizedIdValues($mentionRevisionIds, MentionRevisionId::class, 'MentionRevision');
        $claimIds = self::normalizedIdValues($claimRevisionIds, ClaimRevisionId::class, 'ClaimRevision');

        $payload = [
            'schema' => self::SCHEMA_ID,
            'source_revision_ids' => $sourceIds,
            'mention_revision_ids' => $mentionIds,
            'claim_revision_ids' => $claimIds,
        ];
        $canonicalPayload = CanonicalJson::encode($payload);

        return new self(
            schemaVersion: self::SCHEMA_VERSION,
            sourceRevisionIds: array_map(static fn (string $id): SourceRevisionId => new SourceRevisionId($id), $sourceIds),
            mentionRevisionIds: array_map(static fn (string $id): MentionRevisionId => new MentionRevisionId($id), $mentionIds),
            claimRevisionIds: array_map(static fn (string $id): ClaimRevisionId => new ClaimRevisionId($id), $claimIds),
            canonicalPayload: $canonicalPayload,
            payloadHash: hash('sha256', $canonicalPayload),
        );
    }

    /**
     * Rehydrate a retained snapshot without changing its original canonical payload or hash.
     *
     * Schema v1 did not contain SourceRevisionId values. Persistence resolves its legacy
     * SourceId + revisionNumber references to backfilled SourceRevisionIds and supplies
     * those identities here so callers still receive one typed snapshot model.
     *
     * @param  list<SourceRevisionId>  $legacySourceRevisionIds
     */
    public static function rehydrate(
        int $schemaVersion,
        string $canonicalPayload,
        string $payloadHash,
        array $legacySourceRevisionIds = [],
    ): self {
        if (! in_array($schemaVersion, [self::LEGACY_SCHEMA_VERSION, self::SCHEMA_VERSION], true)) {
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

        if (CanonicalJson::encode($payload) !== $canonicalPayload) {
            throw new InvalidArgumentException('Stored EvidenceState payload is not in canonical form.');
        }

        if ($schemaVersion === self::SCHEMA_VERSION) {
            return self::rehydrateCurrent($payload, $canonicalPayload, $normalizedHash);
        }

        return self::rehydrateLegacy(
            $payload,
            $canonicalPayload,
            $normalizedHash,
            $legacySourceRevisionIds,
        );
    }

    /**
     * @return list<array{sourceId: SourceId, revisionNumber: int}>
     */
    public function legacySourceReferences(): array
    {
        if ($this->schemaVersion !== self::LEGACY_SCHEMA_VERSION) {
            return [];
        }

        return self::legacySourceReferencesFromCanonicalPayload($this->canonicalPayload);
    }

    /**
     * Compatibility reader for the SourceId + revisionNumber references stored by schema v1.
     *
     * @return list<array{sourceId: SourceId, revisionNumber: int}>
     */
    public static function legacySourceReferencesFromCanonicalPayload(string $canonicalPayload): array
    {
        $payload = CanonicalJson::decodeObject($canonicalPayload);

        if (($payload['schema'] ?? null) !== self::LEGACY_SCHEMA_ID) {
            throw new InvalidArgumentException('EvidenceState payload is not a legacy schema v1 snapshot.');
        }

        return self::legacySourceReferencesFromPayload($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function rehydrateCurrent(array $payload, string $canonicalPayload, string $payloadHash): self
    {
        if (($payload['schema'] ?? null) !== self::SCHEMA_ID) {
            throw new InvalidArgumentException('Unsupported EvidenceState snapshot schema identifier.');
        }

        $sourceIds = self::validatedStoredIds($payload, 'source_revision_ids', 'SourceRevision');
        $mentionIds = self::validatedStoredIds($payload, 'mention_revision_ids', 'MentionRevision');
        $claimIds = self::validatedStoredIds($payload, 'claim_revision_ids', 'ClaimRevision');

        foreach ($sourceIds as $id) {
            new SourceRevisionId($id);
        }
        foreach ($mentionIds as $id) {
            new MentionRevisionId($id);
        }
        foreach ($claimIds as $id) {
            new ClaimRevisionId($id);
        }

        return new self(
            schemaVersion: self::SCHEMA_VERSION,
            sourceRevisionIds: array_map(static fn (string $id): SourceRevisionId => new SourceRevisionId($id), $sourceIds),
            mentionRevisionIds: array_map(static fn (string $id): MentionRevisionId => new MentionRevisionId($id), $mentionIds),
            claimRevisionIds: array_map(static fn (string $id): ClaimRevisionId => new ClaimRevisionId($id), $claimIds),
            canonicalPayload: $canonicalPayload,
            payloadHash: $payloadHash,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<SourceRevisionId>  $legacySourceRevisionIds
     */
    private static function rehydrateLegacy(
        array $payload,
        string $canonicalPayload,
        string $payloadHash,
        array $legacySourceRevisionIds,
    ): self {
        if (($payload['schema'] ?? null) !== self::LEGACY_SCHEMA_ID) {
            throw new InvalidArgumentException('Unsupported EvidenceState snapshot schema identifier.');
        }

        $legacyReferences = self::legacySourceReferencesFromPayload($payload);

        if (count($legacySourceRevisionIds) !== count($legacyReferences)) {
            throw new InvalidArgumentException('Legacy EvidenceState snapshot requires resolved SourceRevision identities.');
        }

        $sourceIds = self::normalizedIdValues($legacySourceRevisionIds, SourceRevisionId::class, 'SourceRevision');
        $mentionIds = self::validatedStoredIds($payload, 'mention_revision_ids', 'MentionRevision');
        $claimIds = self::validatedStoredIds($payload, 'claim_revision_ids', 'ClaimRevision');

        foreach ($mentionIds as $id) {
            new MentionRevisionId($id);
        }
        foreach ($claimIds as $id) {
            new ClaimRevisionId($id);
        }

        return new self(
            schemaVersion: self::LEGACY_SCHEMA_VERSION,
            sourceRevisionIds: array_map(static fn (string $id): SourceRevisionId => new SourceRevisionId($id), $sourceIds),
            mentionRevisionIds: array_map(static fn (string $id): MentionRevisionId => new MentionRevisionId($id), $mentionIds),
            claimRevisionIds: array_map(static fn (string $id): ClaimRevisionId => new ClaimRevisionId($id), $claimIds),
            canonicalPayload: $canonicalPayload,
            payloadHash: $payloadHash,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array{sourceId: SourceId, revisionNumber: int}>
     */
    private static function legacySourceReferencesFromPayload(array $payload): array
    {
        $entries = self::objectListAt($payload, 'source_revisions');
        $references = [];
        $seenSources = [];
        $sortKeys = [];

        foreach ($entries as $entry) {
            $sourceId = new SourceId(self::stringAt($entry, 'source_id'));
            $revisionNumber = self::intAt($entry, 'revision_number');

            if ($revisionNumber < 1) {
                throw new InvalidArgumentException('EvidenceState SourceRevision number must be at least 1.');
            }

            if (isset($seenSources[$sourceId->value])) {
                throw new InvalidArgumentException('EvidenceState may contain only one SourceRevision per Source.');
            }

            $seenSources[$sourceId->value] = true;
            $sortKeys[] = [$sourceId->value, $revisionNumber];
            $references[] = [
                'sourceId' => $sourceId,
                'revisionNumber' => $revisionNumber,
            ];
        }

        $sortedKeys = $sortKeys;
        sort($sortedKeys);

        if ($sortKeys !== $sortedKeys) {
            throw new InvalidArgumentException('Stored EvidenceState SourceRevision references are not canonically ordered.');
        }

        return $references;
    }

    /**
     * @template T of SourceRevisionId|MentionRevisionId|ClaimRevisionId
     *
     * @param  list<T>  $ids
     * @param  class-string<T>  $expectedClass
     * @return list<string>
     */
    private static function normalizedIdValues(array $ids, string $expectedClass, string $kind): array
    {
        $values = [];

        foreach ($ids as $id) {
            if (! $id instanceof $expectedClass) {
                throw new InvalidArgumentException(sprintf('EvidenceState %s identity has an invalid type.', $kind));
            }

            $values[] = $id->value;
        }

        sort($values, SORT_STRING);
        self::assertUniqueIds($values, $kind);

        return $values;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    private static function validatedStoredIds(array $payload, string $key, string $kind): array
    {
        $ids = self::stringListAt($payload, $key);
        self::assertUniqueIds($ids, $kind);
        $sorted = $ids;
        sort($sorted, SORT_STRING);

        if ($ids !== $sorted) {
            throw new InvalidArgumentException(sprintf('Stored EvidenceState %s identities are not canonically ordered.', $kind));
        }

        return $ids;
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
