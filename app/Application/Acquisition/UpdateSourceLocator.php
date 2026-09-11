<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\ClaimId;
use App\Domain\Acquisition\SourceAssetId;
use App\Domain\Acquisition\SourceId;
use App\Domain\Acquisition\SourceLocator;
use App\Domain\Acquisition\SourceLocatorId;
use App\Domain\Acquisition\SourceLocatorValue;
use App\Domain\Acquisition\SourceLocatorValueSerializer;

final readonly class UpdateSourceLocator
{
    public function __construct(
        private SourceLocatorRepository $locators,
        private SourceLocatorReferenceValidator $references,
        private ClaimRepository $claims,
        private ClaimRevisionRecorder $revisions,
        private AcquisitionTransaction $transaction,
    ) {}

    public function handle(
        SourceId $sourceId,
        ClaimId $claimId,
        SourceLocatorId $locatorId,
        SourceLocatorValue $value,
        ?SourceAssetId $sourceAssetId = null,
        ?string $changeNote = null,
        ?string $changedBy = null,
    ): SourceLocator {
        $current = $this->locators->find($sourceId, $claimId, $locatorId)
            ?? throw SourceLocatorNotFound::forClaimAndId($sourceId, $claimId, $locatorId);

        $locator = new SourceLocator(
            id: $current->id,
            sourceId: $current->sourceId,
            claimId: $current->claimId,
            value: $value,
            sourceAssetId: $sourceAssetId,
            schemaVersion: $current->schemaVersion,
        );

        $this->references->validate($locator);

        if ($this->sameSemanticState($current, $locator)) {
            return $current;
        }

        $claim = $this->claims->find($sourceId, $claimId)
            ?? throw ClaimNotFound::forSourceAndId($sourceId, $claimId);

        return $this->transaction->run(function () use ($locator, $claim, $changeNote, $changedBy): SourceLocator {
            $this->locators->update($locator);
            $this->revisions->record($claim, $changeNote, $changedBy);

            return $locator;
        });
    }

    private function sameSemanticState(SourceLocator $left, SourceLocator $right): bool
    {
        return $left->sourceAssetId?->value === $right->sourceAssetId?->value
            && SourceLocatorValueSerializer::serialize($left->value) === SourceLocatorValueSerializer::serialize($right->value);
    }
}
