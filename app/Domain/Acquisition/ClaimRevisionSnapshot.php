<?php

declare(strict_types=1);

namespace App\Domain\Acquisition;

use InvalidArgumentException;

final readonly class ClaimRevisionSnapshot
{
    public const SCHEMA_ID = 'mytree.claim-revision.v1';

    public const SCHEMA_VERSION = 1;

    private function __construct(
        public int $schemaVersion,
        public string $canonicalPayload,
        public string $payloadHash,
    ) {}

    /**
     * @param  list<SourceLocator>  $sourceLocators
     */
    public static function capture(
        Claim $claim,
        MentionRevisionId $subjectMentionRevisionId,
        ?MentionRevisionId $objectMentionRevisionId,
        array $sourceLocators,
    ): self {
        if (($claim->objectMentionId === null) !== ($objectMentionRevisionId === null)) {
            throw new InvalidArgumentException('ClaimRevision object Mention revision must match Claim object Mention presence.');
        }

        $locators = $sourceLocators;
        usort(
            $locators,
            static fn (SourceLocator $left, SourceLocator $right): int => strcmp($left->id->value, $right->id->value),
        );

        $state = new ClaimRevisionState(
            claim: $claim,
            subjectMentionRevisionId: $subjectMentionRevisionId,
            objectMentionRevisionId: $objectMentionRevisionId,
            sourceLocators: $locators,
        );

        $payload = [
            'schema' => self::SCHEMA_ID,
            'claim' => [
                'id' => $state->claim->id->value,
                'source_id' => $state->claim->sourceId->value,
                'schema_version' => $state->claim->schemaVersion,
                'subject' => [
                    'mention_id' => $state->claim->subjectMentionId->value,
                    'mention_revision_id' => $state->subjectMentionRevisionId->value,
                ],
                'predicate' => $state->claim->predicate->identity(),
                'object' => $state->claim->objectMentionId === null ? null : [
                    'mention_id' => $state->claim->objectMentionId->value,
                    'mention_revision_id' => $state->objectMentionRevisionId?->value,
                ],
                'value' => $state->claim->value === null ? null : ClaimValueSerializer::toArray($state->claim->value),
                'qualifiers' => $state->claim->qualifiers->toArray(),
                'raw_text' => $state->claim->rawText,
                'origin' => $state->claim->origin->toArray(),
                'transcription_certainty' => [
                    'code' => $state->claim->transcriptionCertainty->code,
                    'schema_version' => $state->claim->transcriptionCertainty->schemaVersion,
                ],
                'interpretation_certainty' => [
                    'code' => $state->claim->interpretationCertainty->code,
                    'schema_version' => $state->claim->interpretationCertainty->schemaVersion,
                ],
                'source_locators' => array_map(
                    static fn (SourceLocator $locator): array => [
                        'id' => $locator->id->value,
                        'schema_version' => $locator->schemaVersion,
                        'source_asset_id' => $locator->sourceAssetId?->value,
                        'value' => CanonicalJson::decodeObject(SourceLocatorValueSerializer::serialize($locator->value)),
                    ],
                    $state->sourceLocators,
                ),
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
                'Unsupported ClaimRevision snapshot schema version %d.',
                $schemaVersion,
            ));
        }

        $normalizedHash = strtolower($payloadHash);

        if (preg_match('/^[0-9a-f]{64}$/D', $normalizedHash) !== 1) {
            throw new InvalidArgumentException('ClaimRevision payload hash must be a SHA-256 hexadecimal value.');
        }

        if (! hash_equals($normalizedHash, hash('sha256', $canonicalPayload))) {
            throw new InvalidArgumentException('ClaimRevision payload hash does not match the stored payload.');
        }

        $payload = CanonicalJson::decodeObject($canonicalPayload);

        if (($payload['schema'] ?? null) !== self::SCHEMA_ID) {
            throw new InvalidArgumentException('Unsupported ClaimRevision snapshot schema identifier.');
        }

        if (CanonicalJson::encode($payload) !== $canonicalPayload) {
            throw new InvalidArgumentException('Stored ClaimRevision payload is not in canonical form.');
        }

        $snapshot = new self(
            schemaVersion: $schemaVersion,
            canonicalPayload: $canonicalPayload,
            payloadHash: $normalizedHash,
        );
        $snapshot->reconstruct();

        return $snapshot;
    }

    public function reconstruct(): ClaimRevisionState
    {
        $payload = CanonicalJson::decodeObject($this->canonicalPayload);

        if (($payload['schema'] ?? null) !== self::SCHEMA_ID) {
            throw new InvalidArgumentException('Unsupported ClaimRevision snapshot schema identifier.');
        }

        $claimPayload = self::objectAt($payload, 'claim');
        $subjectPayload = self::objectAt($claimPayload, 'subject');
        $predicatePayload = self::objectAt($claimPayload, 'predicate');
        $objectPayload = self::nullableObjectAt($claimPayload, 'object');
        $valuePayload = self::nullableObjectAt($claimPayload, 'value');
        $qualifiersPayload = self::objectAt($claimPayload, 'qualifiers');
        $originPayload = self::objectAt($claimPayload, 'origin');
        $transcriptionCertaintyPayload = self::objectAt($claimPayload, 'transcription_certainty');
        $interpretationCertaintyPayload = self::objectAt($claimPayload, 'interpretation_certainty');

        $claim = new Claim(
            id: new ClaimId(self::stringAt($claimPayload, 'id')),
            sourceId: new SourceId(self::stringAt($claimPayload, 'source_id')),
            subjectMentionId: new MentionId(self::stringAt($subjectPayload, 'mention_id')),
            predicate: PredicateVocabulary::get(
                self::stringAt($predicatePayload, 'key'),
                self::intAt($predicatePayload, 'schema_version'),
            ),
            objectMentionId: $objectPayload === null ? null : new MentionId(self::stringAt($objectPayload, 'mention_id')),
            value: $valuePayload === null ? null : ClaimValueSerializer::fromArray($valuePayload),
            qualifiers: ClaimQualifiers::deserialize(CanonicalJson::encode($qualifiersPayload)),
            rawText: self::nullableStringAt($claimPayload, 'raw_text'),
            origin: ClaimOrigin::deserialize(CanonicalJson::encode($originPayload)),
            transcriptionCertainty: new ClaimCertainty(
                self::stringAt($transcriptionCertaintyPayload, 'code'),
                self::intAt($transcriptionCertaintyPayload, 'schema_version'),
            ),
            interpretationCertainty: new ClaimCertainty(
                self::stringAt($interpretationCertaintyPayload, 'code'),
                self::intAt($interpretationCertaintyPayload, 'schema_version'),
            ),
            schemaVersion: self::intAt($claimPayload, 'schema_version'),
        );

        $sourceLocators = [];
        foreach (self::objectListAt($claimPayload, 'source_locators') as $locatorPayload) {
            $locatorValuePayload = self::objectAt($locatorPayload, 'value');
            $sourceAssetId = self::nullableStringAt($locatorPayload, 'source_asset_id');

            $sourceLocators[] = new SourceLocator(
                id: new SourceLocatorId(self::stringAt($locatorPayload, 'id')),
                sourceId: $claim->sourceId,
                claimId: $claim->id,
                value: SourceLocatorValueSerializer::deserialize(CanonicalJson::encode($locatorValuePayload)),
                sourceAssetId: $sourceAssetId === null ? null : new SourceAssetId($sourceAssetId),
                schemaVersion: self::intAt($locatorPayload, 'schema_version'),
            );
        }

        return new ClaimRevisionState(
            claim: $claim,
            subjectMentionRevisionId: new MentionRevisionId(self::stringAt($subjectPayload, 'mention_revision_id')),
            objectMentionRevisionId: $objectPayload === null
                ? null
                : new MentionRevisionId(self::stringAt($objectPayload, 'mention_revision_id')),
            sourceLocators: $sourceLocators,
        );
    }

    /**
     * @param  array<string, mixed>  $object
     * @return array<string, mixed>
     */
    private static function objectAt(array $object, string $key): array
    {
        if (! array_key_exists($key, $object)) {
            throw new InvalidArgumentException(sprintf('Stored ClaimRevision payload is missing "%s".', $key));
        }

        $value = $object[$key];

        if (! is_array($value) || ($value !== [] && array_is_list($value))) {
            throw new InvalidArgumentException(sprintf('Stored ClaimRevision "%s" must be a JSON object.', $key));
        }

        return self::stringKeyedObject($value, $key);
    }

    /**
     * @param  array<string, mixed>  $object
     * @return array<string, mixed>|null
     */
    private static function nullableObjectAt(array $object, string $key): ?array
    {
        if (! array_key_exists($key, $object)) {
            throw new InvalidArgumentException(sprintf('Stored ClaimRevision payload is missing "%s".', $key));
        }

        if ($object[$key] === null) {
            return null;
        }

        return self::objectAt($object, $key);
    }

    /**
     * @param  array<string, mixed>  $object
     * @return list<array<string, mixed>>
     */
    private static function objectListAt(array $object, string $key): array
    {
        if (! array_key_exists($key, $object) || ! is_array($object[$key]) || ! array_is_list($object[$key])) {
            throw new InvalidArgumentException(sprintf('Stored ClaimRevision "%s" must be a JSON array.', $key));
        }

        $result = [];
        foreach ($object[$key] as $index => $value) {
            if (! is_array($value) || ($value !== [] && array_is_list($value))) {
                throw new InvalidArgumentException(sprintf('Stored ClaimRevision "%s.%d" must be a JSON object.', $key, $index));
            }

            $result[] = self::stringKeyedObject($value, sprintf('%s.%d', $key, $index));
        }

        return $result;
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @return array<string, mixed>
     */
    private static function stringKeyedObject(array $value, string $path): array
    {
        $result = [];

        foreach ($value as $nestedKey => $nestedValue) {
            if (! is_string($nestedKey)) {
                throw new InvalidArgumentException(sprintf('Stored ClaimRevision "%s" must use string keys.', $path));
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
            throw new InvalidArgumentException(sprintf('Stored ClaimRevision "%s" must be a string.', $key));
        }

        return $value;
    }

    /** @param array<string, mixed> $object */
    private static function nullableStringAt(array $object, string $key): ?string
    {
        if (! array_key_exists($key, $object)) {
            throw new InvalidArgumentException(sprintf('Stored ClaimRevision payload is missing "%s".', $key));
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
            throw new InvalidArgumentException(sprintf('Stored ClaimRevision "%s" must be an integer.', $key));
        }

        return $value;
    }
}
