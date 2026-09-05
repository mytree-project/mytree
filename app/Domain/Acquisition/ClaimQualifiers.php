<?php

declare(strict_types=1);

namespace App\Domain\Acquisition;

use InvalidArgumentException;

final readonly class ClaimQualifiers
{
    public const SCHEMA_ID = 'mytree.claim-qualifiers.v1';

    public const SCHEMA_VERSION = 1;

    public function __construct(
        public ?DateClaimValue $effectiveTime = null,
        public ?MentionId $placeMentionId = null,
        public ?MentionId $workplaceMentionId = null,
        public int $schemaVersion = self::SCHEMA_VERSION,
    ) {
        if ($schemaVersion !== self::SCHEMA_VERSION) {
            throw new InvalidArgumentException('Unsupported Claim qualifiers schema version.');
        }
    }

    public static function empty(): self
    {
        return new self;
    }

    public function isEmpty(): bool
    {
        return $this->effectiveTime === null
            && $this->placeMentionId === null
            && $this->workplaceMentionId === null;
    }

    public function serialize(): string
    {
        return CanonicalJson::encode($this->toArray());
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'effective_time' => $this->effectiveTime === null
                ? null
                : ClaimValueSerializer::toArray($this->effectiveTime),
            'place_mention_id' => $this->placeMentionId?->value,
            'schema' => self::SCHEMA_ID,
            'schema_version' => $this->schemaVersion,
            'workplace_mention_id' => $this->workplaceMentionId?->value,
        ];
    }

    public static function deserialize(string $payload): self
    {
        $decoded = CanonicalJson::decodeObject($payload);

        if (CanonicalJson::encode($decoded) !== $payload) {
            throw new InvalidArgumentException('Stored Claim qualifiers payload is not in canonical form.');
        }

        if (($decoded['schema'] ?? null) !== self::SCHEMA_ID) {
            throw new InvalidArgumentException('Unsupported Claim qualifiers schema identifier.');
        }

        if (($decoded['schema_version'] ?? null) !== self::SCHEMA_VERSION) {
            throw new InvalidArgumentException('Unsupported Claim qualifiers schema version.');
        }

        $effectiveTime = self::effectiveTime($decoded['effective_time'] ?? null);

        return new self(
            effectiveTime: $effectiveTime,
            placeMentionId: self::mentionId($decoded['place_mention_id'] ?? null, 'place_mention_id'),
            workplaceMentionId: self::mentionId($decoded['workplace_mention_id'] ?? null, 'workplace_mention_id'),
            schemaVersion: self::SCHEMA_VERSION,
        );
    }

    private static function effectiveTime(mixed $value): ?DateClaimValue
    {
        if ($value === null) {
            return null;
        }

        if (! is_array($value) || array_is_list($value)) {
            throw new InvalidArgumentException('Claim qualifiers effective_time must be an object.');
        }

        foreach (array_keys($value) as $key) {
            if (! is_string($key)) {
                throw new InvalidArgumentException('Claim qualifiers effective_time object keys must be strings.');
            }
        }

        /** @var array<string, mixed> $value */
        $claimValue = ClaimValueSerializer::fromArray($value);

        if (! $claimValue instanceof DateClaimValue) {
            throw new InvalidArgumentException('Claim qualifiers effective_time must be a Date Claim value.');
        }

        return $claimValue;
    }

    private static function mentionId(mixed $value, string $field): ?MentionId
    {
        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            throw new InvalidArgumentException(sprintf(
                'Claim qualifiers %s must be a Mention UUID string.',
                $field,
            ));
        }

        return new MentionId($value);
    }
}
