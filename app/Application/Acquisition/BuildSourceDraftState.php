<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\Claim;
use App\Domain\Acquisition\ClaimId;
use App\Domain\Acquisition\Mention;
use App\Domain\Acquisition\MentionId;
use App\Domain\Acquisition\Source;
use App\Domain\Acquisition\SourceAsset;
use App\Domain\Acquisition\SourceId;
use App\Domain\Acquisition\SourceLocator;
use App\Domain\Acquisition\SourceLocatorId;
use App\Domain\Acquisition\SourceText;

final readonly class BuildSourceDraftState
{
    public function __construct(private SourceAssetRepository $assets) {}

    public function project(SourceDraft $draft): SourceDraftState
    {
        $current = $draft->current;
        $changes = $draft->changes;
        $source = $this->projectSource($current->source, $changes->source);

        $assets = $this->keyBy($current->assets, static fn (SourceAsset $asset): string => $asset->id->value);
        foreach ($changes->attachAssetIds as $index => $assetId) {
            $asset = $this->assets->find($assetId)
                ?? throw new SourceDraftOperationInvalid("changes.attachAssetIds.$index", 'Source asset does not exist.');

            if ($asset->sourceId !== null && $asset->sourceId->value !== $source->id->value) {
                throw new SourceDraftOperationInvalid(
                    "changes.attachAssetIds.$index",
                    'Source asset is already attached to another Source.',
                );
            }

            $assets[$asset->id->value] = $asset->sourceId === null ? $this->attach($asset, $source->id) : $asset;
        }
        foreach ($changes->detachAssetIds as $index => $assetId) {
            if (! isset($assets[$assetId->value])) {
                throw new SourceDraftOperationInvalid("changes.detachAssetIds.$index", 'Source asset is not attached to this Source.');
            }
            unset($assets[$assetId->value]);
        }

        $mentions = $this->keyBy($current->mentions, static fn (Mention $mention): string => $mention->id->value);
        $this->applyEntityChanges(
            $mentions,
            $changes->addMentions,
            $changes->updateMentions,
            $changes->removeMentionIds,
            static fn (Mention $mention): string => $mention->id->value,
            static fn (MentionId $id): string => $id->value,
            'Mention',
        );

        $claims = $this->keyBy($current->claims, static fn (Claim $claim): string => $claim->id->value);
        $this->applyEntityChanges(
            $claims,
            $changes->addClaims,
            $changes->updateClaims,
            $changes->removeClaimIds,
            static fn (Claim $claim): string => $claim->id->value,
            static fn (ClaimId $id): string => $id->value,
            'Claim',
        );

        $locators = $this->keyBy($current->locators, static fn (SourceLocator $locator): string => $locator->id->value);
        foreach ($changes->updateLocators as $index => $locator) {
            $existing = $locators[$locator->id->value] ?? null;
            if ($existing instanceof SourceLocator
                && ($existing->sourceId->value !== $locator->sourceId->value
                    || $existing->claimId->value !== $locator->claimId->value)) {
                throw new SourceDraftOperationInvalid(
                    "changes.updateLocators.$index",
                    'SourceLocator parent Source and Claim cannot be changed; remove and add the locator instead.',
                );
            }
        }
        $this->applyEntityChanges(
            $locators,
            $changes->addLocators,
            $changes->updateLocators,
            $changes->removeLocatorIds,
            static fn (SourceLocator $locator): string => $locator->id->value,
            static fn (SourceLocatorId $id): string => $id->value,
            'Locator',
        );

        $claimIds = array_fill_keys(array_keys($claims), true);
        foreach ($locators as $id => $locator) {
            if (! isset($claimIds[$locator->claimId->value])) {
                unset($locators[$id]);
            }
        }

        return new SourceDraftState(
            source: $source,
            assets: array_values($assets),
            mentions: array_values($mentions),
            claims: array_values($claims),
            locators: array_values($locators),
        );
    }

    private function projectSource(Source $source, ?SourceDraftSourceChanges $changes): Source
    {
        if ($changes === null) {
            return $source;
        }

        $texts = $this->keyBy($source->texts, static fn (SourceText $text): string => $text->id->value);
        foreach ($changes->addTexts as $index => $text) {
            if (isset($texts[$text->id->value])) {
                throw new SourceDraftOperationInvalid("changes.source.addTexts.$index", 'SourceText identity already exists.');
            }
            $texts[$text->id->value] = $text;
        }
        foreach ($changes->updateTexts as $index => $text) {
            if (! isset($texts[$text->id->value])) {
                throw new SourceDraftOperationInvalid("changes.source.updateTexts.$index", 'SourceText identity does not exist.');
            }
            $texts[$text->id->value] = $text;
        }
        foreach ($changes->removeTextIds as $index => $textId) {
            if (! isset($texts[$textId->value])) {
                throw new SourceDraftOperationInvalid("changes.source.removeTextIds.$index", 'SourceText identity does not exist.');
            }
            unset($texts[$textId->value]);
        }

        return new Source(
            id: $source->id,
            type: $changes->type ?? $source->type,
            metadata: $changes->metadata ?? $source->metadata,
            texts: array_values($texts),
            schemaVersion: $source->schemaVersion,
        );
    }

    private function attach(SourceAsset $asset, SourceId $sourceId): SourceAsset
    {
        return new SourceAsset(
            id: $asset->id,
            sourceId: $sourceId,
            storage: $asset->storage,
            originalFilename: $asset->originalFilename,
            mimeType: $asset->mimeType,
            byteSize: $asset->byteSize,
            sha256: $asset->sha256,
            retrievedAt: $asset->retrievedAt,
            metadata: $asset->metadata,
            provenance: $asset->provenance,
            schemaVersion: $asset->schemaVersion,
        );
    }

    /**
     * @template T of object
     *
     * @param  list<T>  $values
     * @param  callable(T): string  $key
     * @return array<string, T>
     */
    private function keyBy(array $values, callable $key): array
    {
        $result = [];
        foreach ($values as $value) {
            $result[$key($value)] = $value;
        }

        return $result;
    }

    /**
     * @template T of object
     * @template TId of object
     *
     * @param  array<string, T>  $current
     * @param  list<T>  $add
     * @param  list<T>  $update
     * @param  list<TId>  $remove
     * @param  callable(T): string  $entityId
     * @param  callable(TId): string  $removeId
     */
    private function applyEntityChanges(
        array &$current,
        array $add,
        array $update,
        array $remove,
        callable $entityId,
        callable $removeId,
        string $entityName,
    ): void {
        $collectionName = $entityName.'s';

        foreach ($add as $index => $entity) {
            $id = $entityId($entity);
            if (isset($current[$id])) {
                throw new SourceDraftOperationInvalid(
                    "changes.add{$collectionName}.$index",
                    $entityName.' identity already exists.',
                );
            }
            $current[$id] = $entity;
        }

        foreach ($update as $index => $entity) {
            $id = $entityId($entity);
            if (! isset($current[$id])) {
                throw new SourceDraftOperationInvalid(
                    "changes.update{$collectionName}.$index",
                    $entityName.' identity does not exist.',
                );
            }
            $current[$id] = $entity;
        }

        foreach ($remove as $index => $idObject) {
            $id = $removeId($idObject);
            if (! isset($current[$id])) {
                throw new SourceDraftOperationInvalid(
                    "changes.remove{$entityName}Ids.$index",
                    $entityName.' identity does not exist.',
                );
            }
            unset($current[$id]);
        }
    }
}
