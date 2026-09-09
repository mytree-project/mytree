<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Acquisition;

use App\Application\Acquisition\SourceLocatorNotFound;
use App\Application\Acquisition\SourceLocatorRepository;
use App\Domain\Acquisition\ClaimId;
use App\Domain\Acquisition\SourceAssetId;
use App\Domain\Acquisition\SourceId;
use App\Domain\Acquisition\SourceLocator;
use App\Domain\Acquisition\SourceLocatorId;
use App\Domain\Acquisition\SourceLocatorValueSerializer;
use App\Infrastructure\Persistence\Eloquent\Acquisition\Models\SourceLocatorRecord;
use UnexpectedValueException;

final class EloquentSourceLocatorRepository implements SourceLocatorRepository
{
    public function add(SourceLocator $locator): void
    {
        SourceLocatorRecord::query()->create($this->attributes($locator, includeOwnership: true));
    }

    public function update(SourceLocator $locator): void
    {
        SourceLocatorRecord::query()
            ->where('source_id', $locator->sourceId->value)
            ->where('claim_id', $locator->claimId->value)
            ->where('id', $locator->id->value)
            ->update($this->attributes($locator, includeOwnership: false));
    }

    public function find(SourceId $sourceId, ClaimId $claimId, SourceLocatorId $locatorId): ?SourceLocator
    {
        $record = SourceLocatorRecord::query()
            ->where('source_id', $sourceId->value)
            ->where('claim_id', $claimId->value)
            ->where('id', $locatorId->value)
            ->first();

        return $record === null ? null : $this->map($record);
    }

    public function forClaim(SourceId $sourceId, ClaimId $claimId): array
    {
        $locators = [];
        $records = SourceLocatorRecord::query()
            ->where('source_id', $sourceId->value)
            ->where('claim_id', $claimId->value)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        foreach ($records as $record) {
            $locators[] = $this->map($record);
        }

        return $locators;
    }

    public function remove(SourceId $sourceId, ClaimId $claimId, SourceLocatorId $locatorId): void
    {
        $deleted = SourceLocatorRecord::query()
            ->where('source_id', $sourceId->value)
            ->where('claim_id', $claimId->value)
            ->where('id', $locatorId->value)
            ->delete();

        if ($deleted !== 1) {
            throw SourceLocatorNotFound::forClaimAndId($sourceId, $claimId, $locatorId);
        }
    }

    private function map(SourceLocatorRecord $record): SourceLocator
    {
        $value = SourceLocatorValueSerializer::deserialize((string) $record->value_payload);

        if ((string) $record->locator_type !== $value->type()->value) {
            throw new UnexpectedValueException('Stored Source locator type does not match its payload.');
        }

        return new SourceLocator(
            id: new SourceLocatorId((string) $record->id),
            sourceId: new SourceId((string) $record->source_id),
            claimId: new ClaimId((string) $record->claim_id),
            value: $value,
            sourceAssetId: $record->source_asset_id === null ? null : new SourceAssetId((string) $record->source_asset_id),
            schemaVersion: (int) $record->schema_version,
        );
    }

    /** @return array<string, mixed> */
    private function attributes(SourceLocator $locator, bool $includeOwnership): array
    {
        $attributes = [
            'source_asset_id' => $locator->sourceAssetId?->value,
            'schema_version' => $locator->schemaVersion,
            'locator_type' => $locator->value->type()->value,
            'value_payload' => SourceLocatorValueSerializer::serialize($locator->value),
        ];

        if ($includeOwnership) {
            $attributes['id'] = $locator->id->value;
            $attributes['source_id'] = $locator->sourceId->value;
            $attributes['claim_id'] = $locator->claimId->value;
        }

        return $attributes;
    }
}
