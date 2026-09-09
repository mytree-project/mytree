<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\ClaimId;
use App\Domain\Acquisition\SourceId;
use App\Domain\Acquisition\SourceLocator;
use App\Domain\Acquisition\SourceLocatorId;

final readonly class RemoveSourceLocator
{
    public function __construct(
        private SourceLocatorRepository $locators,
        private ClaimRepository $claims,
        private ClaimRevisionRecorder $revisions,
        private AcquisitionTransaction $transaction,
    ) {}

    public function handle(
        SourceId $sourceId,
        ClaimId $claimId,
        SourceLocatorId $locatorId,
        ?string $changeNote = null,
        ?string $changedBy = null,
    ): SourceLocator {
        $locator = $this->locators->find($sourceId, $claimId, $locatorId)
            ?? throw SourceLocatorNotFound::forClaimAndId($sourceId, $claimId, $locatorId);
        $claim = $this->claims->find($sourceId, $claimId)
            ?? throw ClaimNotFound::forSourceAndId($sourceId, $claimId);

        return $this->transaction->run(function () use ($sourceId, $claimId, $locatorId, $locator, $claim, $changeNote, $changedBy): SourceLocator {
            $this->locators->remove($sourceId, $claimId, $locatorId);
            $this->revisions->record($claim, $changeNote, $changedBy);

            return $locator;
        });
    }
}
