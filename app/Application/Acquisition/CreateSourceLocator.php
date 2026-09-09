<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\ClaimId;
use App\Domain\Acquisition\SourceAssetId;
use App\Domain\Acquisition\SourceId;
use App\Domain\Acquisition\SourceLocator;
use App\Domain\Acquisition\SourceLocatorValue;

final readonly class CreateSourceLocator
{
    public function __construct(
        private SourceLocatorRepository $locators,
        private SourceIdentifierGenerator $identifiers,
        private SourceLocatorReferenceValidator $references,
        private ClaimRepository $claims,
        private ClaimRevisionRecorder $revisions,
        private AcquisitionTransaction $transaction,
    ) {}

    public function handle(
        SourceId $sourceId,
        ClaimId $claimId,
        SourceLocatorValue $value,
        ?SourceAssetId $sourceAssetId = null,
        ?string $changeNote = null,
        ?string $changedBy = null,
    ): SourceLocator {
        $locator = new SourceLocator(
            id: $this->identifiers->sourceLocatorId(),
            sourceId: $sourceId,
            claimId: $claimId,
            value: $value,
            sourceAssetId: $sourceAssetId,
        );

        $this->references->validate($locator);
        $claim = $this->claims->find($sourceId, $claimId)
            ?? throw ClaimNotFound::forSourceAndId($sourceId, $claimId);

        return $this->transaction->run(function () use ($locator, $claim, $changeNote, $changedBy): SourceLocator {
            $this->locators->add($locator);
            $this->revisions->record($claim, $changeNote, $changedBy);

            return $locator;
        });
    }
}
