<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\MentionKind;
use InvalidArgumentException;

final readonly class SupportedAcquisitionEventContextInput
{
    /** @var list<SupportedAcquisitionClaimInput> */
    public array $claims;

    /** @param list<SupportedAcquisitionClaimInput> $claims */
    public function __construct(
        public SupportedAcquisitionMentionInput $event,
        array $claims = [],
    ) {
        if ($event->kind->key !== MentionKind::EVENT) {
            throw new InvalidArgumentException('Supported event context input requires an event Mention.');
        }

        $this->claims = $claims;
    }
}
