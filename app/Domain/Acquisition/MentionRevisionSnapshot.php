<?php

declare(strict_types=1);

namespace App\Domain\Acquisition;

use InvalidArgumentException;

final readonly class MentionRevisionSnapshot
{
    public const SCHEMA_ID = 'mytree.mention-revision.v1';

    public const SCHEMA_VERSION = 1;

    private function __construct(
        public int $schemaVersion,
        public string $canonicalPayload,
        public string $payloadHash,
    ) {}

    public static function capture(Mention $mention): self
    {
        $payload = [
            'schema' => self::SCHEMA_ID,
            'mention' => [
                'id' => $mention->id->value,
                'source_id' => $mention->sourceId->value,
                'schema_version' => $mention->schemaVersion,
                'kind' => $mention->kind->key,
                'local_key' => $mention->localKey,
                'role' => $mention->role,
                'display_label' => $mention->displayLabel,
                'raw_data' => $mention->rawData->toArray(),
            ],
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
                'Unsupported MentionRevision snapshot schema version %d.',
                $schemaVersion,
            ));
        }

        $normalizedHash = strtolower($payloadHash);

        if (preg_match('/^[0-9a-f]{64}$/D', $normalizedHash) !== 1) {
            throw new InvalidArgumentException('MentionRevision payload hash must be a SHA-256 hexadecimal value.');
        }

        if (! hash_equals($normalizedHash, hash('sha256', $canonicalPayload))) {
            throw new InvalidArgumentException('MentionRevision payload hash does not match the stored payload.');
        }

        $payload = CanonicalJson::decodeObject($canonicalPayload);

        if (($payload['schema'] ?? null) !== self::SCHEMA_ID) {
            throw new InvalidArgumentException('Unsupported MentionRevision snapshot schema identifier.');
        }

        if (CanonicalJson::encode($payload) !== $canonicalPayload) {
            throw new InvalidArgumentException('Stored MentionRevision payload is not in canonical form.');
        }

        $snapshot = new self(
            schemaVersion: $schemaVersion,
            canonicalPayload: $canonicalPayload,
            payloadHash: $normalizedHash,
        );
        $snapshot->reconstruct();

        return $snapshot;
    }

    public function reconstruct(): Mention
    {
        $payload = CanonicalJson::decodeObject($this->canonicalPayload);

        if (($payload['schema'] ?? null) !== self::SCHEMA_ID) {
            throw new InvalidArgumentException('Unsupported MentionRevision snapshot schema identifier.');
        }

        $mentionPayload = self::objectAt($payload, 'mention');

        return new Mention(
            id: new MentionId(self::stringAt($mentionPayload, 'id')),
            sourceId: new SourceId(self::stringAt($mentionPayload, 'source_id')),
            kind: new MentionKind(self::stringAt($mentionPayload, 'kind')),
            localKey: self::stringAt($mentionPayload, 'local_key'),
            role: self::nullableStringAt($mentionPayload, 'role'),
            displayLabel: self::nullableStringAt($mentionPayload, 'display_label'),
            rawData: new MentionRawData(self::rawDataAt($mentionPayload)),
            schemaVersion: self::intAt($mentionPayload, 'schema_version'),
        );
    }

    /**
     * @param  array<string, mixed>  $object
     * @return array<string, mixed>
     */
    private static function objectAt(array $object, string $key): array
    {
        if (! array_key_exists($key, $object)) {
            throw new InvalidArgumentException(sprintf('Stored MentionRevision payload is missing "%s".', $key));
        }

        $value = $object[$key];

        if (! is_array($value) || ($value !== [] && array_is_list($value))) {
            throw new InvalidArgumentException(sprintf('Stored MentionRevision "%s" must be a JSON object.', $key));
        }

        $result = [];

        foreach ($value as $nestedKey => $nestedValue) {
            if (! is_string($nestedKey)) {
                throw new InvalidArgumentException(sprintf('Stored MentionRevision "%s" must use string keys.', $key));
            }

            $result[$nestedKey] = $nestedValue;
        }

        return $result;
    }

    /** @param array<string, mixed> $object */
    private static function stringAt(array $object, string $key): string
    {
        $value = $object[$key] ?? null;

        if (! is_string($value)) {
            throw new InvalidArgumentException(sprintf('Stored MentionRevision "%s" must be a string.', $key));
        }

        return $value;
    }

    /** @param array<string, mixed> $object */
    private static function nullableStringAt(array $object, string $key): ?string
    {
        if (! array_key_exists($key, $object)) {
            throw new InvalidArgumentException(sprintf('Stored MentionRevision payload is missing "%s".', $key));
        }

        if ($object[$key] === null) {
            return null;
        }

        return self::stringAt($object, $key);
    }

    /** @param array<string, mixed> $object */
    private static function intAt(array $object, string $key): int
    {
        $value = $object[$key] ?? null;

        if (! is_int($value)) {
            throw new InvalidArgumentException(sprintf('Stored MentionRevision "%s" must be an integer.', $key));
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $object
     * @return array<string, mixed>
     */
    private static function rawDataAt(array $object): array
    {
        if (! array_key_exists('raw_data', $object) || ! is_array($object['raw_data'])) {
            throw new InvalidArgumentException('Stored MentionRevision "raw_data" must be a JSON object.');
        }

        $result = [];

        foreach ($object['raw_data'] as $key => $value) {
            if (! is_string($key)) {
                throw new InvalidArgumentException('Stored MentionRevision "raw_data" must use string keys.');
            }

            $result[$key] = $value;
        }

        return $result;
    }
}
