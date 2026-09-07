<?php

declare(strict_types=1);

namespace App\Domain\Acquisition;

use InvalidArgumentException;

final readonly class ClaimOrigin
{
    public const SCHEMA_ID = 'mytree.claim-origin.v1';

    public const SCHEMA_VERSION = 1;

    /** @var array<string, mixed> */
    public array $requestContext;

    /** @var array<string, mixed> */
    public array $metadata;

    /**
     * @param  array<string, mixed>  $requestContext
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public ClaimOriginKind $kind,
        public ?string $observationId = null,
        public ?string $providerKey = null,
        public ?string $providerRecordId = null,
        public ?string $sourceUrl = null,
        array $requestContext = [],
        public ?string $parserVersion = null,
        public ?string $providerVersion = null,
        array $metadata = [],
        public int $schemaVersion = self::SCHEMA_VERSION,
    ) {
        if ($schemaVersion !== self::SCHEMA_VERSION) {
            throw new InvalidArgumentException('Unsupported Claim origin schema version.');
        }

        foreach ([
            'observation id' => $observationId,
            'provider key' => $providerKey,
            'provider record id' => $providerRecordId,
            'source URL' => $sourceUrl,
            'parser version' => $parserVersion,
            'provider version' => $providerVersion,
        ] as $field => $value) {
            if ($value !== null && trim($value) === '') {
                throw new InvalidArgumentException(sprintf('Claim origin %s must not be empty when provided.', $field));
            }
        }

        if ($kind === ClaimOriginKind::ProviderObservation && $providerKey === null) {
            throw new InvalidArgumentException('Provider-observation Claim origin requires a provider key.');
        }

        self::assertJsonObject($requestContext, 'request context');
        self::assertJsonObject($metadata, 'metadata');

        $this->requestContext = $requestContext;
        $this->metadata = $metadata;
    }

    public static function manualDirectSource(): self
    {
        return new self(ClaimOriginKind::ManualDirectSource);
    }

    public function serialize(): string
    {
        return CanonicalJson::encode($this->toArray());
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'kind' => $this->kind->value,
            'metadata' => $this->metadata,
            'observation_id' => $this->observationId,
            'parser_version' => $this->parserVersion,
            'provider_key' => $this->providerKey,
            'provider_record_id' => $this->providerRecordId,
            'provider_version' => $this->providerVersion,
            'request_context' => $this->requestContext,
            'schema' => self::SCHEMA_ID,
            'schema_version' => $this->schemaVersion,
            'source_url' => $this->sourceUrl,
        ];
    }

    public static function deserialize(string $payload): self
    {
        $decoded = CanonicalJson::decodeObject($payload);

        if (CanonicalJson::encode($decoded) !== $payload) {
            throw new InvalidArgumentException('Stored Claim origin payload is not in canonical form.');
        }

        if (($decoded['schema'] ?? null) !== self::SCHEMA_ID || ($decoded['schema_version'] ?? null) !== self::SCHEMA_VERSION) {
            throw new InvalidArgumentException('Unsupported Claim origin schema.');
        }

        $kind = is_string($decoded['kind'] ?? null) ? ClaimOriginKind::tryFrom($decoded['kind']) : null;

        if ($kind === null) {
            throw new InvalidArgumentException('Unsupported Claim origin kind.');
        }

        return new self(
            kind: $kind,
            observationId: self::nullableString($decoded['observation_id'] ?? null, 'observation_id'),
            providerKey: self::nullableString($decoded['provider_key'] ?? null, 'provider_key'),
            providerRecordId: self::nullableString($decoded['provider_record_id'] ?? null, 'provider_record_id'),
            sourceUrl: self::nullableString($decoded['source_url'] ?? null, 'source_url'),
            requestContext: self::object($decoded['request_context'] ?? null, 'request_context'),
            parserVersion: self::nullableString($decoded['parser_version'] ?? null, 'parser_version'),
            providerVersion: self::nullableString($decoded['provider_version'] ?? null, 'provider_version'),
            metadata: self::object($decoded['metadata'] ?? null, 'metadata'),
        );
    }

    /** @param array<string, mixed> $values */
    private static function assertJsonObject(array $values, string $field): void
    {
        foreach ($values as $key => $value) {
            if ($key === '') {
                throw new InvalidArgumentException(sprintf('Claim origin %s keys must not be empty.', $field));
            }

            self::assertJsonCompatible($value, $field);
        }
    }

    private static function assertJsonCompatible(mixed $value, string $field): void
    {
        if ($value === null || is_string($value) || is_int($value) || is_float($value) || is_bool($value)) {
            return;
        }

        if (is_array($value)) {
            foreach ($value as $nestedValue) {
                self::assertJsonCompatible($nestedValue, $field);
            }

            return;
        }

        throw new InvalidArgumentException(sprintf('Claim origin %s values must be JSON-compatible.', $field));
    }

    /** @return array<string, mixed> */
    private static function object(mixed $value, string $field): array
    {
        if (! is_array($value) || array_is_list($value)) {
            throw new InvalidArgumentException(sprintf('Claim origin %s must be an object.', $field));
        }

        $result = [];
        foreach ($value as $key => $nestedValue) {
            if (! is_string($key)) {
                throw new InvalidArgumentException(sprintf('Claim origin %s keys must be strings.', $field));
            }
            $result[$key] = $nestedValue;
        }

        return $result;
    }

    private static function nullableString(mixed $value, string $field): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            throw new InvalidArgumentException(sprintf('Claim origin %s must be a string.', $field));
        }

        return $value;
    }
}
