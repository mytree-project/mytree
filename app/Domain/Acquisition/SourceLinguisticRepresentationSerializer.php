<?php

declare(strict_types=1);

namespace App\Domain\Acquisition;

use InvalidArgumentException;

final class SourceLinguisticRepresentationSerializer
{
    public const LIST_SCHEMA_ID = 'mytree.source-linguistic-representations.v1';

    /**
     * @param  list<SourceLinguisticRepresentation>  $representations
     * @return list<SourceLinguisticRepresentation>
     */
    public static function normalize(array $representations): array
    {
        $byKey = [];

        foreach ($representations as $representation) {
            if (! $representation instanceof SourceLinguisticRepresentation) {
                throw new InvalidArgumentException('Source linguistic representations must use the typed domain contract.');
            }

            $key = CanonicalJson::encode(self::toArray($representation));
            if (isset($byKey[$key])) {
                throw new InvalidArgumentException('Duplicate source linguistic representations are not allowed.');
            }

            $byKey[$key] = $representation;
        }

        ksort($byKey, SORT_STRING);

        return array_values($byKey);
    }

    /** @return array<string, mixed> */
    public static function toArray(SourceLinguisticRepresentation $representation): array
    {
        return [
            'schema' => SourceLinguisticRepresentation::SCHEMA_ID,
            'schema_version' => $representation->schemaVersion,
            'value' => $representation->value,
            'language' => $representation->language,
            'script' => $representation->script,
            'relation' => $representation->relation->value,
            'source_locator_ids' => array_map(
                static fn (SourceLocatorId $id): string => $id->value,
                $representation->sourceLocatorIds,
            ),
        ];
    }

    /**
     * @param  list<SourceLinguisticRepresentation>  $representations
     * @return list<array<string, mixed>>
     */
    public static function toArrayList(array $representations): array
    {
        return array_map(
            self::toArray(...),
            self::normalize($representations),
        );
    }

    /** @param array<string, mixed> $payload */
    public static function fromArray(array $payload): SourceLinguisticRepresentation
    {
        if (($payload['schema'] ?? null) !== SourceLinguisticRepresentation::SCHEMA_ID) {
            throw new InvalidArgumentException('Unsupported source linguistic representation schema identifier.');
        }

        $schemaVersion = $payload['schema_version'] ?? null;
        $value = $payload['value'] ?? null;
        $language = $payload['language'] ?? null;
        $script = $payload['script'] ?? null;
        $relation = $payload['relation'] ?? null;
        $sourceLocatorIds = $payload['source_locator_ids'] ?? null;

        if (! is_int($schemaVersion) || ! is_string($value) || ! is_string($relation)) {
            throw new InvalidArgumentException('Source linguistic representation payload has invalid scalar fields.');
        }

        if ($language !== null && ! is_string($language)) {
            throw new InvalidArgumentException('Source linguistic representation language must be a string or null.');
        }

        if ($script !== null && ! is_string($script)) {
            throw new InvalidArgumentException('Source linguistic representation script must be a string or null.');
        }

        if (! is_array($sourceLocatorIds) || ! array_is_list($sourceLocatorIds)) {
            throw new InvalidArgumentException('Source linguistic representation locator references must be a list.');
        }

        $locatorIds = [];
        foreach ($sourceLocatorIds as $sourceLocatorId) {
            if (! is_string($sourceLocatorId)) {
                throw new InvalidArgumentException('Source linguistic representation locator references must be strings.');
            }

            $locatorIds[] = new SourceLocatorId($sourceLocatorId);
        }

        return new SourceLinguisticRepresentation(
            value: $value,
            language: $language,
            script: $script,
            relation: SourceLinguisticRepresentationRelation::tryFrom($relation)
                ?? throw new InvalidArgumentException('Unsupported source linguistic representation relation.'),
            sourceLocatorIds: $locatorIds,
            schemaVersion: $schemaVersion,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $payloads
     * @return list<SourceLinguisticRepresentation>
     */
    public static function fromArrayList(array $payloads): array
    {
        $representations = [];

        foreach ($payloads as $payload) {
            $representations[] = self::fromArray($payload);
        }

        return self::normalize($representations);
    }

    /** @param list<SourceLinguisticRepresentation> $representations */
    public static function serializeList(array $representations): string
    {
        return CanonicalJson::encode([
            'schema' => self::LIST_SCHEMA_ID,
            'items' => self::toArrayList($representations),
        ]);
    }

    /** @return list<SourceLinguisticRepresentation> */
    public static function deserializeList(string $payload): array
    {
        $decoded = CanonicalJson::decodeObject($payload);

        if (($decoded['schema'] ?? null) !== self::LIST_SCHEMA_ID) {
            throw new InvalidArgumentException('Unsupported source linguistic representation list schema identifier.');
        }

        $items = $decoded['items'] ?? null;
        if (! is_array($items) || ! array_is_list($items)) {
            throw new InvalidArgumentException('Source linguistic representation list payload must contain an item list.');
        }

        $payloads = [];
        foreach ($items as $item) {
            if (! is_array($item) || ($item !== [] && array_is_list($item))) {
                throw new InvalidArgumentException('Source linguistic representation list items must be objects.');
            }

            /** @var array<string, mixed> $item */
            $payloads[] = $item;
        }

        return self::fromArrayList($payloads);
    }
}
