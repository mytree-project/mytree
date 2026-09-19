<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\Claim;
use App\Domain\Acquisition\ClaimOriginKind;
use App\Domain\Acquisition\ClaimValue;
use App\Domain\Acquisition\Mention;
use App\Domain\Acquisition\MentionKind;
use App\Domain\Acquisition\SourceLocator;

final readonly class SourceEvidenceGraphProjector
{
    public const SCHEMA_ID = 'mytree.source-evidence-graph.v1';

    /** @return array<string, mixed> */
    public function project(SourceDraftState $state, ?SourceDraftState $persistedState = null): array
    {
        $persistedMentionIds = $this->ids($persistedState->mentions ?? []);
        $persistedClaimIds = $this->ids($persistedState->claims ?? []);
        $persistedLocatorIds = $this->ids($persistedState->locators ?? []);

        $mentionsById = [];
        foreach ($state->mentions as $mention) {
            $mentionsById[$mention->id->value] = $mention;
        }

        $claimsBySubject = [];
        foreach ($state->claims as $index => $claim) {
            $claimsBySubject[$claim->subjectMentionId->value][] = [$index, $claim];
        }

        $locatorsByClaim = [];
        foreach ($state->locators as $index => $locator) {
            $locatorsByClaim[$locator->claimId->value][] = [$index, $locator];
        }

        $mentions = $state->mentions;
        usort($mentions, static fn (Mention $left, Mention $right): int => strcmp($left->localKey, $right->localKey));

        $ordinary = [];
        $events = [];

        foreach ($mentions as $mention) {
            $node = $this->mentionNode(
                mention: $mention,
                claims: $claimsBySubject[$mention->id->value] ?? [],
                locatorsByClaim: $locatorsByClaim,
                mentionsById: $mentionsById,
                persistedMentionIds: $persistedMentionIds,
                persistedClaimIds: $persistedClaimIds,
                persistedLocatorIds: $persistedLocatorIds,
            );

            if ($mention->kind->key === MentionKind::EVENT) {
                $events[] = $node;
            } else {
                $ordinary[] = $node;
            }
        }

        $projection = [
            'schema' => self::SCHEMA_ID,
        ];
        if ($persistedState !== null) {
            $projection['source_id'] = $state->source->id->value;
        }
        $projection['mentions'] = $ordinary;
        $projection['events'] = $events;

        return $projection;
    }

    /**
     * @param  list<array{0: int, 1: Claim}>  $claims
     * @param  array<string, list<array{0: int, 1: SourceLocator}>>  $locatorsByClaim
     * @param  array<string, Mention>  $mentionsById
     * @param  array<string, true>  $persistedMentionIds
     * @param  array<string, true>  $persistedClaimIds
     * @param  array<string, true>  $persistedLocatorIds
     * @return array<string, mixed>
     */
    private function mentionNode(
        Mention $mention,
        array $claims,
        array $locatorsByClaim,
        array $mentionsById,
        array $persistedMentionIds,
        array $persistedClaimIds,
        array $persistedLocatorIds,
    ): array {
        usort($claims, function (array $left, array $right) use ($mentionsById, $persistedClaimIds): int {
            $leftClaim = $left[1];
            $rightClaim = $right[1];

            foreach ([
                strcmp($leftClaim->predicate->key->value, $rightClaim->predicate->key->value),
                strcmp($this->claimSortValue($leftClaim, $mentionsById), $this->claimSortValue($rightClaim, $mentionsById)),
                strcmp($leftClaim->rawText ?? '', $rightClaim->rawText ?? ''),
            ] as $comparison) {
                if ($comparison !== 0) {
                    return $comparison;
                }
            }

            $leftPersisted = isset($persistedClaimIds[$leftClaim->id->value]);
            $rightPersisted = isset($persistedClaimIds[$rightClaim->id->value]);
            if ($leftPersisted && $rightPersisted) {
                return strcmp($leftClaim->id->value, $rightClaim->id->value);
            }
            if ($leftPersisted !== $rightPersisted) {
                return $leftPersisted ? -1 : 1;
            }

            return $left[0] <=> $right[0];
        });

        $node = [
            'local_key' => $mention->localKey,
        ];
        if (isset($persistedMentionIds[$mention->id->value])) {
            $node['id'] = $mention->id->value;
        }
        $node['kind'] = $mention->kind->key;
        if ($mention->role !== null) {
            $node['role'] = $mention->role;
        }
        if ($mention->displayLabel !== null) {
            $node['display_label'] = $mention->displayLabel;
        }

        $rawData = $mention->rawData->toArray();
        if ($rawData !== []) {
            $node['raw_data'] = $this->sortObject($rawData);
        }

        $node['claims'] = array_map(
            fn (array $entry): array => $this->claimNode(
                claim: $entry[1],
                locators: $locatorsByClaim[$entry[1]->id->value] ?? [],
                mentionsById: $mentionsById,
                persistedMentionIds: $persistedMentionIds,
                persistedClaimIds: $persistedClaimIds,
                persistedLocatorIds: $persistedLocatorIds,
            ),
            $claims,
        );

        return $node;
    }

    /**
     * @param  list<array{0: int, 1: SourceLocator}>  $locators
     * @param  array<string, Mention>  $mentionsById
     * @param  array<string, true>  $persistedMentionIds
     * @param  array<string, true>  $persistedClaimIds
     * @param  array<string, true>  $persistedLocatorIds
     * @return array<string, mixed>
     */
    private function claimNode(
        Claim $claim,
        array $locators,
        array $mentionsById,
        array $persistedMentionIds,
        array $persistedClaimIds,
        array $persistedLocatorIds,
    ): array {
        $node = [
            'predicate' => $claim->predicate->key->value,
        ];
        if (isset($persistedClaimIds[$claim->id->value])) {
            $node['id'] = $claim->id->value;
        }

        if ($claim->value !== null) {
            $node['value'] = $this->claimValue($claim->value);
        } else {
            $objectMentionId = $claim->objectMentionId;
            if ($objectMentionId === null) {
                throw new InvalidArgumentException('Mention-reference Claim is missing its object Mention identity.');
            }

            $object = $mentionsById[$objectMentionId->value] ?? null;
            if ($object === null) {
                throw new InvalidArgumentException('Mention-reference Claim points outside the projected Source graph.');
            }

            $reference = [
                'local_key' => $object->localKey,
            ];
            if (isset($persistedMentionIds[$object->id->value])) {
                $reference['id'] = $object->id->value;
            }
            $node['object'] = $reference;
        }

        if (! $claim->qualifiers->isEmpty()) {
            $node['qualifiers'] = [
                'effective_time' => $claim->qualifiers->effectiveTime === null
                    ? null
                    : $this->claimValue($claim->qualifiers->effectiveTime),
            ];
        }
        if ($claim->rawText !== null) {
            $node['raw_text'] = $claim->rawText;
        }
        if ($claim->transcriptionCertainty->code !== 'unspecified') {
            $node['transcription_certainty'] = $claim->transcriptionCertainty->code;
        }
        if ($claim->interpretationCertainty->code !== 'unspecified') {
            $node['interpretation_certainty'] = $claim->interpretationCertainty->code;
        }
        if ($claim->origin->kind !== ClaimOriginKind::ManualDirectSource || $this->originHasAdditionalData($claim)) {
            $node['origin'] = $this->compactObject($claim->origin->toArray(), [
                'schema',
                'schema_version',
            ]);
        }

        if ($locators !== []) {
            usort($locators, function (array $left, array $right) use ($persistedLocatorIds): int {
                $leftLocator = $left[1];
                $rightLocator = $right[1];
                $comparison = strcmp($this->locatorSortValue($leftLocator), $this->locatorSortValue($rightLocator));
                if ($comparison !== 0) {
                    return $comparison;
                }

                $leftPersisted = isset($persistedLocatorIds[$leftLocator->id->value]);
                $rightPersisted = isset($persistedLocatorIds[$rightLocator->id->value]);
                if ($leftPersisted && $rightPersisted) {
                    return strcmp($leftLocator->id->value, $rightLocator->id->value);
                }

                return $left[0] <=> $right[0];
            });

            $node['locators'] = array_map(
                fn (array $entry): array => $this->locatorNode($entry[1], $persistedLocatorIds),
                $locators,
            );
        }

        return $node;
    }

    /** @return array<string, mixed> */
    private function claimValue(ClaimValue $value): array
    {
        $data = $value->data();
        unset($data['raw']);

        return [
            'type' => $value->type()->value,
            'raw' => $value->raw(),
            ...$this->sortObject($data),
        ];
    }

    /**
     * @param  array<string, true>  $persistedLocatorIds
     * @return array<string, mixed>
     */
    private function locatorNode(SourceLocator $locator, array $persistedLocatorIds): array
    {
        $node = [
            'type' => $locator->value->type()->value,
            ...$this->sortObject($locator->value->data()),
        ];
        if (isset($persistedLocatorIds[$locator->id->value])) {
            $node['id'] = $locator->id->value;
        }
        if ($locator->sourceAssetId !== null) {
            $node['source_asset_id'] = $locator->sourceAssetId->value;
        }

        return $node;
    }

    /** @param array<string, Mention> $mentionsById */
    private function claimSortValue(Claim $claim, array $mentionsById): string
    {
        if ($claim->value !== null) {
            return 'value:'.$claim->value->type()->value.':'.$claim->value->raw();
        }

        $objectMentionId = $claim->objectMentionId;
        if ($objectMentionId === null) {
            return 'object:';
        }

        $object = $mentionsById[$objectMentionId->value] ?? null;

        return 'object:'.($object === null ? $objectMentionId->value : $object->localKey);
    }

    private function locatorSortValue(SourceLocator $locator): string
    {
        $sourceAssetId = $locator->sourceAssetId;

        return $locator->value->type()->value.'|'.json_encode(
            $this->sortObject($locator->value->data()),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        ).'|'.($sourceAssetId === null ? '' : $sourceAssetId->value);
    }

    private function originHasAdditionalData(Claim $claim): bool
    {
        $origin = $claim->origin;

        return $origin->observationId !== null
            || $origin->providerKey !== null
            || $origin->providerRecordId !== null
            || $origin->sourceUrl !== null
            || $origin->requestContext !== []
            || $origin->parserVersion !== null
            || $origin->providerVersion !== null
            || $origin->metadata !== [];
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  list<string>  $removeKeys
     * @return array<string, mixed>
     */
    private function compactObject(array $values, array $removeKeys = []): array
    {
        foreach ($removeKeys as $key) {
            unset($values[$key]);
        }
        foreach ($values as $key => $value) {
            if ($value === null || $value === []) {
                unset($values[$key]);

                continue;
            }
            if (is_array($value) && ! array_is_list($value)) {
                $values[$key] = $this->sortObject($value);
            }
        }

        return $this->sortObject($values);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function sortObject(array $values): array
    {
        ksort($values, SORT_STRING);
        foreach ($values as $key => $value) {
            if (is_array($value) && ! array_is_list($value)) {
                $values[$key] = $this->sortObject($value);
            }
        }

        return $values;
    }

    /**
     * @param  list<Mention|Claim|SourceLocator>  $values
     * @return array<string, true>
     */
    private function ids(array $values): array
    {
        $ids = [];
        foreach ($values as $value) {
            $ids[$value->id->value] = true;
        }

        return $ids;
    }
}
