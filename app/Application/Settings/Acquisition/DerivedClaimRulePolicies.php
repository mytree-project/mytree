<?php

declare(strict_types=1);

namespace App\Application\Settings\Acquisition;

use App\Application\Acquisition\AcquisitionTransaction;
use App\Application\Settings\SettingsStore;
use UnexpectedValueException;

/**
 * All consumers read the same versioned SettingsStore used by the admin page.
 * An unregistered rule cannot silently acquire execution privileges.
 */
final readonly class DerivedClaimRulePolicies
{
    public function __construct(
        private DerivedClaimRuleRegistry $rules,
        private AcquisitionSettingsSection $section,
        private SettingsStore $store,
        private AcquisitionTransaction $transaction,
    ) {}

    public function current(string $ruleId): DerivedClaimRulePolicy
    {
        $rule = $this->rules->byId($ruleId);
        $enabled = $this->store->read($this->section->enabledFor($ruleId));
        $automatic = $this->store->read($this->section->autoApproveFor($ruleId));
        if (! is_bool($enabled->value) || ! is_bool($automatic->value)) {
            throw new UnexpectedValueException('Derived Claim rule settings must be booleans.');
        }

        return new DerivedClaimRulePolicy(
            rule: $rule,
            enabled: $enabled->value,
            autoApprove: $automatic->value,
            enabledRevision: $enabled->revision,
            autoApproveRevision: $automatic->revision,
        );
    }

    public function update(
        string $ruleId,
        bool $enabled,
        bool $autoApprove,
        string $changedBy,
    ): DerivedClaimRulePolicy {
        if (trim($changedBy) === '') {
            throw new \InvalidArgumentException('A rule policy change requires an attributed administrator.');
        }

        return $this->transaction->run(function () use (
            $ruleId, $enabled, $autoApprove, $changedBy,
        ): DerivedClaimRulePolicy {
            $current = $this->current($ruleId);

            // Avoid fake revision increments from a no-op settings save.
            if ($current->enabled !== $enabled) {
                $this->store->write($this->section->enabledFor($ruleId), $enabled, $changedBy);
            }

            if ($current->autoApprove !== $autoApprove) {
                $this->store->write($this->section->autoApproveFor($ruleId), $autoApprove, $changedBy);
            }

            return $this->current($ruleId);
        });
    }
}
