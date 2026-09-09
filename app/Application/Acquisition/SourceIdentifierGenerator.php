<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\ClaimId;
use App\Domain\Acquisition\MentionId;
use App\Domain\Acquisition\MentionRevisionId;
use App\Domain\Acquisition\SourceAssetId;
use App\Domain\Acquisition\SourceId;
use App\Domain\Acquisition\SourceLocatorId;
use App\Domain\Acquisition\SourceTextId;

interface SourceIdentifierGenerator
{
    public function sourceId(): SourceId;

    public function sourceTextId(): SourceTextId;

    public function sourceAssetId(): SourceAssetId;

    public function mentionId(): MentionId;

    public function mentionRevisionId(): MentionRevisionId;

    public function claimId(): ClaimId;

    public function sourceLocatorId(): SourceLocatorId;
}
