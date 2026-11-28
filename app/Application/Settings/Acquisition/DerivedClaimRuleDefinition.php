<?php

declare(strict_types=1);

namespace App\Application\Settings\Acquisition;

/**
 * A registered source-local derivation algorithm; the two policy switches
 * change execution/acceptance, never the rule's Direct-input semantics.
 */
final readonly class DerivedClaimRuleDefinition
{
    public function __construct(
        public string $id,
        public string $key,
        public bool $defaultEnabled,
        public bool $defaultAutoApprove,
        public bool $implemented,
    ) {}
}
