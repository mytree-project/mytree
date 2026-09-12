<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

final readonly class SupportedAcquisitionEditInput
{
    /** @var list<SupportedAcquisitionMentionInput> */
    public array $mentions;

    /** @var list<SupportedAcquisitionClaimInput> */
    public array $fields;

    /** @var list<SupportedAcquisitionEventContextInput> */
    public array $eventContexts;

    /**
     * @param  list<SupportedAcquisitionMentionInput>  $mentions
     * @param  list<SupportedAcquisitionClaimInput>  $fields
     * @param  list<SupportedAcquisitionEventContextInput>  $eventContexts
     */
    public function __construct(
        array $mentions = [],
        array $fields = [],
        array $eventContexts = [],
    ) {
        $this->mentions = $mentions;
        $this->fields = $fields;
        $this->eventContexts = $eventContexts;
    }
}
