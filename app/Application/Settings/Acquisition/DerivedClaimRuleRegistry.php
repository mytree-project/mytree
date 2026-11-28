<?php

declare(strict_types=1);

namespace App\Application\Settings\Acquisition;

use InvalidArgumentException;

final class DerivedClaimRuleRegistry
{
    public const AGE_AT_EVENT = 'person.birth_date.from_age_at_event';

    public const CHILD_SURNAME = 'person.surname.from_parent_in_birth_record';

    /** @return list<DerivedClaimRuleDefinition> */
    public function all(): array
    {
        return [
            new DerivedClaimRuleDefinition(
                id: self::AGE_AT_EVENT,
                key: 'age_at_event',
                defaultEnabled: true,
                defaultAutoApprove: true,
                implemented: true,
            ),
            new DerivedClaimRuleDefinition(
                id: self::CHILD_SURNAME,
                key: 'child_surname',
                defaultEnabled: true,
                defaultAutoApprove: false,
                implemented: false, // Generation is delivered by #237.
            ),
        ];
    }

    public function byId(string $ruleId): DerivedClaimRuleDefinition
    {
        foreach ($this->all() as $rule) {
            if ($rule->id === $ruleId) {
                return $rule;
            }
        }

        throw new InvalidArgumentException(sprintf('Unregistered Derived Claim rule: %s.', $ruleId));
    }

    public function byKey(string $key): DerivedClaimRuleDefinition
    {
        foreach ($this->all() as $rule) {
            if ($rule->key === $key) {
                return $rule;
            }
        }

        throw new InvalidArgumentException(sprintf('Unknown Derived Claim rule setting: %s.', $key));
    }
}
