<?php

declare(strict_types=1);

namespace App\Domain\Acquisition;

use InvalidArgumentException;

final readonly class Predicate
{
    /** @var list<string> */
    public array $allowedEnumKeys;

    /** @param  list<string>  $allowedEnumKeys */
    public function __construct(
        public PredicateKey $key,
        public int $schemaVersion,
        public string $subjectMentionKind,
        public ?ClaimValueType $literalValueType = null,
        public ?string $objectMentionKind = null,
        array $allowedEnumKeys = [],
    ) {
        if ($schemaVersion < 1) {
            throw new InvalidArgumentException('Predicate schema version must be at least 1.');
        }

        self::assertCanonicalMentionKind($subjectMentionKind, 'subject');

        if ($objectMentionKind !== null) {
            self::assertCanonicalMentionKind($objectMentionKind, 'object');
        }

        if (($literalValueType === null) === ($objectMentionKind === null)) {
            throw new InvalidArgumentException('Predicate must define exactly one literal or Mention-object contract.');
        }

        if ($allowedEnumKeys !== [] && $literalValueType !== ClaimValueType::Enum) {
            throw new InvalidArgumentException('Predicate enum keys require an Enum literal value type.');
        }

        $normalizedEnumKeys = [];
        foreach ($allowedEnumKeys as $enumKey) {
            if (preg_match('/^[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)*$/D', $enumKey) !== 1) {
                throw new InvalidArgumentException('Predicate enum keys must use lowercase dot-separated identifiers.');
            }

            $normalizedEnumKeys[] = $enumKey;
        }

        if (count($normalizedEnumKeys) !== count(array_unique($normalizedEnumKeys))) {
            throw new InvalidArgumentException('Predicate enum keys must be unique.');
        }

        $this->allowedEnumKeys = $normalizedEnumKeys;
    }

    public function assertSubjectKind(MentionKind $kind): void
    {
        if ($kind->key !== $this->subjectMentionKind) {
            throw new InvalidArgumentException(sprintf(
                'Predicate "%s" requires a "%s" subject Mention.',
                $this->key->value,
                $this->subjectMentionKind,
            ));
        }
    }

    public function assertLiteralValue(ClaimValue $value): void
    {
        if ($this->literalValueType === null) {
            throw new InvalidArgumentException(sprintf(
                'Predicate "%s" does not accept a literal Claim value.',
                $this->key->value,
            ));
        }

        if ($value->type() !== $this->literalValueType) {
            throw new InvalidArgumentException(sprintf(
                'Predicate "%s" requires Claim value type "%s".',
                $this->key->value,
                $this->literalValueType->value,
            ));
        }

        if ($this->allowedEnumKeys !== []) {
            if (! $value instanceof EnumClaimValue || ! in_array($value->key, $this->allowedEnumKeys, true)) {
                throw new InvalidArgumentException(sprintf(
                    'Predicate "%s" does not accept the provided controlled enum key.',
                    $this->key->value,
                ));
            }
        }
    }

    public function assertObjectKind(MentionKind $kind): void
    {
        if ($this->objectMentionKind === null) {
            throw new InvalidArgumentException(sprintf(
                'Predicate "%s" does not accept an object Mention.',
                $this->key->value,
            ));
        }

        if ($kind->key !== $this->objectMentionKind) {
            throw new InvalidArgumentException(sprintf(
                'Predicate "%s" requires a "%s" object Mention.',
                $this->key->value,
                $this->objectMentionKind,
            ));
        }
    }

    /** @return array{key: string, schema_version: int} */
    public function identity(): array
    {
        return [
            'key' => $this->key->value,
            'schema_version' => $this->schemaVersion,
        ];
    }

    private static function assertCanonicalMentionKind(string $kind, string $role): void
    {
        if (! in_array($kind, [
            MentionKind::PERSON,
            MentionKind::EVENT,
            MentionKind::PLACE,
            MentionKind::ORGANIZATION,
            MentionKind::OTHER,
        ], true)) {
            throw new InvalidArgumentException(sprintf(
                'Predicate %s Mention kind must be canonical.',
                $role,
            ));
        }
    }
}
