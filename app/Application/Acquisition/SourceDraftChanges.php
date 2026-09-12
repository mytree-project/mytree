<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\Claim;
use App\Domain\Acquisition\ClaimId;
use App\Domain\Acquisition\Mention;
use App\Domain\Acquisition\MentionId;
use App\Domain\Acquisition\SourceAssetId;
use App\Domain\Acquisition\SourceLocator;
use App\Domain\Acquisition\SourceLocatorId;

final readonly class SourceDraftChanges
{
    /**
     * @param list<SourceAssetId> $attachAssetIds
     * @param list<SourceAssetId> $detachAssetIds
     * @param list<Mention> $addMentions
     * @param list<Mention> $updateMentions
     * @param list<MentionId> $removeMentionIds
     * @param list<Claim> $addClaims
     * @param list<Claim> $updateClaims
     * @param list<ClaimId> $removeClaimIds
     * @param list<SourceLocator> $addLocators
     * @param list<SourceLocator> $updateLocators
     * @param list<SourceLocatorId> $removeLocatorIds
     */
    public function __construct(
        public ?SourceDraftSourceChanges $source = null,
        public array $attachAssetIds = [],
        public array $detachAssetIds = [],
        public array $addMentions = [],
        public array $updateMentions = [],
        public array $removeMentionIds = [],
        public array $addClaims = [],
        public array $updateClaims = [],
        public array $removeClaimIds = [],
        public array $addLocators = [],
        public array $updateLocators = [],
        public array $removeLocatorIds = [],
    ) {}

    public static function none(): self
    {
        return new self;
    }
}
