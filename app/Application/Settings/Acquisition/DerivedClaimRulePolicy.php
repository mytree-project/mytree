<?php

declare(strict_types=1);

namespace App\Application\Settings\Acquisition;

final readonly class DerivedClaimRulePolicy
{
    public function __construct(
        public DerivedClaimRuleDefinition $rule,
        public bool $enabled,
        public bool $autoApprove,
        public int $enabledRevision,
        public int $autoApproveRevision,
    ) {}

    public function automaticActor(): string
    {
        return sprintf(
            'system:rule-policy:%s:enabled-r%d:auto-r%d',
            $this->rule->key,
            $this->enabledRevision,
            $this->autoApproveRevision,
        );
    }
}
