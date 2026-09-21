<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

final readonly class SupportedAcquisitionEditInput
{
    /** @var list<SupportedAcquisitionMentionInput> */
    public array $mentions;

    /** @var list<SupportedAcquisitionClaimInput> */
    public array $fields;

    /**
     * @param  list<SupportedAcquisitionMentionInput>  $mentions
     * @param  list<SupportedAcquisitionClaimInput>  $fields
     */
    public function __construct(
        array $mentions = [],
        array $fields = [],
    ) {
        $this->mentions = $mentions;
        $this->fields = $fields;
    }
}
