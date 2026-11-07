<?php

declare(strict_types=1);

namespace App\Domain\Acquisition;

use InvalidArgumentException;

/**
 * Immutable provenance of an accepted Source-local Derived Claim.
 *
 * It references only Direct Claim revisions; their complete values and source
 * locators can be reconstructed without depending on mutable current rows.
 */
final readonly class ClaimDerivation
{
    public const SCHEMA_ID = 'mytree.claim-derivation.v1';

    public const SCHEMA_VERSION = 1;

    /** @var list<ClaimRevisionId> */
    public array $inputClaimRevisionIds;

    /** @var list<MentionRevisionId> */
    public array $inputMentionRevisionIds;

    /** @var array<string, mixed> */
    public array $assumptions;

    /**
     * @param list<ClaimRevisionId> $inputClaimRevisionIds
     * @param list<MentionRevisionId> $inputMentionRevisionIds
     * @param array<string, mixed> $assumptions
     */
    public function __construct(
        public string $ruleId,
        public string $ruleVersion,
        public string $implementationSignature,
        public string $outputKey,
        array $inputClaimRevisionIds,
        array $inputMentionRevisionIds = [],
        array $assumptions = [],
    ) {
        foreach ([
            'rule id' => $ruleId,
            'rule version' => $ruleVersion,
            'implementation signature' => $implementationSignature,
            'output key' => $outputKey,
        ] as $name => $value) {
            if (trim($value) === '') {
                throw new InvalidArgumentException(sprintf('Claim derivation %s must not be empty.', $name));
            }
        }

        $this->inputClaimRevisionIds = self::normalizeIds(
            $inputClaimRevisionIds,
            ClaimRevisionId::class,
            'ClaimRevision',
            required: true,
        );
        $this->inputMentionRevisionIds = self::normalizeIds(
            $inputMentionRevisionIds,
            MentionRevisionId::class,
            'MentionRevision',
        );

        if ($assumptions !== [] && array_is_list($assumptions)) {
            throw new InvalidArgumentException('Claim derivation assumptions must be a JSON object.');
        }
        foreach (array_keys($assumptions) as $key) {
            if (! is_string($key) || $key === '') {
                throw new InvalidArgumentException('Claim derivation assumption keys must be nonempty strings.');
            }
        }

        // Validate every nested value for canonical JSON serialization.
        CanonicalJson::encode(['assumptions' => $assumptions]);
        $this->assumptions = $assumptions;
    }

    public function serialize(): string
    {
        return CanonicalJson::encode($this->toArray());
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'schema' => self::SCHEMA_ID,
            'schema_version' => self::SCHEMA_VERSION,
            'rule_id' => $this->ruleId,
            'rule_version' => $this->ruleVersion,
            'implementation_signature' => $this->implementationSignature,
            'output_key' => $this->outputKey,
            'input_claim_revision_ids' => array_map(
                static fn (ClaimRevisionId $id): string => $id->value,
                $this->inputClaimRevisionIds,
            ),
            'input_mention_revision_ids' => array_map(
                static fn (MentionRevisionId $id): string => $id->value,
                $this->inputMentionRevisionIds,
            ),
            'assumptions' => $this->assumptions,
        ];
    }

    public static function deserialize(string $payload): self
    {
        $decoded = CanonicalJson::decodeObject($payload);
        if (CanonicalJson::encode($decoded) !== $payload) {
            throw new InvalidArgumentException('Stored Claim derivation payload is not canonical.');
        }

        return self::fromArray($decoded);
    }

    /** @param array<string, mixed> $payload */
    public static function fromArray(array $payload): self
    {
        if (($payload['schema'] ?? null) !== self::SCHEMA_ID
            || ($payload['schema_version'] ?? null) !== self::SCHEMA_VERSION) {
            throw new InvalidArgumentException('Unsupported Claim derivation schema.');
        }

        $assumptions = $payload['assumptions'] ?? null;
        if (! is_array($assumptions) || ($assumptions !== [] && array_is_list($assumptions))) {
            throw new InvalidArgumentException('Claim derivation assumptions must be an object.');
        }

        $result = new self(
            ruleId: self::stringAt($payload, 'rule_id'),
            ruleVersion: self::stringAt($payload, 'rule_version'),
            implementationSignature: self::stringAt($payload, 'implementation_signature'),
            outputKey: self::stringAt($payload, 'output_key'),
            inputClaimRevisionIds: array_map(
                static fn (string $id): ClaimRevisionId => new ClaimRevisionId($id),
                self::stringListAt($payload, 'input_claim_revision_ids'),
            ),
            inputMentionRevisionIds: array_map(
                static fn (string $id): MentionRevisionId => new MentionRevisionId($id),
                self::stringListAt($payload, 'input_mention_revision_ids'),
            ),
            assumptions: $assumptions,
        );

        if (CanonicalJson::encode($payload) !== $result->serialize()) {
            throw new InvalidArgumentException('Claim derivation must be complete and canonically ordered.');
        }

        return $result;
    }

    public function fingerprint(): string
    {
        return hash('sha256', $this->serialize());
    }

    /**
     * @template T of ClaimRevisionId|MentionRevisionId
     * @param list<T> $ids
     * @param class-string<T> $type
     * @return list<T>
     */
    private static function normalizeIds(array $ids, string $type, string $name, bool $required = false): array
    {
        if ($required && $ids === []) {
            throw new InvalidArgumentException('Derived Claim requires at least one Direct ClaimRevision input.');
        }

        $byValue = [];
        foreach ($ids as $id) {
            if (! $id instanceof $type) {
                throw new InvalidArgumentException(sprintf('Claim derivation %s identity has an invalid type.', $name));
            }
            if (isset($byValue[$id->value])) {
                throw new InvalidArgumentException(sprintf('Claim derivation %s identities must be unique.', $name));
            }
            $byValue[$id->value] = $id;
        }

        ksort($byValue, SORT_STRING);

        return array_values($byValue);
    }

    /** @param array<string, mixed> $payload */
    private static function stringAt(array $payload, string $key): string
    {
        $value = $payload[$key] ?? null;
        if (! is_string($value)) {
            throw new InvalidArgumentException(sprintf('Claim derivation "%s" must be a string.', $key));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $payload
     * @return list<string>
     */
    private static function stringListAt(array $payload, string $key): array
    {
        $value = $payload[$key] ?? null;
        if (! is_array($value) || ! array_is_list($value)) {
            throw new InvalidArgumentException(sprintf('Claim derivation "%s" must be a list.', $key));
        }

        foreach ($value as $entry) {
            if (! is_string($entry)) {
                throw new InvalidArgumentException(sprintf('Claim derivation "%s" entries must be strings.', $key));
            }
        }

        return $value;
    }
}
