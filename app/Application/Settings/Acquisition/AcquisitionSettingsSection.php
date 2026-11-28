<?php

declare(strict_types=1);

namespace App\Application\Settings\Acquisition;

use App\Application\Settings\SettingDefinition;
use App\Application\Settings\SettingsSection;
use App\Application\Settings\SettingValueType;

final class AcquisitionSettingsSection implements SettingsSection
{
    public const KEY = 'acquisition';

    private readonly DerivedClaimRuleRegistry $rules;

    public function __construct(?DerivedClaimRuleRegistry $rules = null)
    {
        $this->rules = $rules ?? new DerivedClaimRuleRegistry;
    }

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return 'Acquisition';
    }

    public function enabledFor(string $ruleId): SettingDefinition
    {
        $rule = $this->rules->byId($ruleId);

        return new SettingDefinition(
            section: self::KEY,
            key: $rule->key.'_enabled',
            type: SettingValueType::Boolean,
            schemaVersion: 1,
            defaultValue: $rule->defaultEnabled,
        );
    }

    public function autoApproveFor(string $ruleId): SettingDefinition
    {
        $rule = $this->rules->byId($ruleId);

        return new SettingDefinition(
            section: self::KEY,
            key: $rule->key.'_auto_approve',
            type: SettingValueType::Boolean,
            schemaVersion: 1,
            defaultValue: $rule->defaultAutoApprove,
        );
    }

    public function definitions(): array
    {
        $definitions = [];
        foreach ($this->rules->all() as $rule) {
            $definitions[] = $this->enabledFor($rule->id);
            $definitions[] = $this->autoApproveFor($rule->id);
        }

        return $definitions;
    }
}
