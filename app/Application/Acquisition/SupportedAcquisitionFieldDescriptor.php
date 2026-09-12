<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\ClaimValueType;
use App\Domain\Acquisition\MentionKind;
use App\Domain\Acquisition\PredicateKey;
use InvalidArgumentException;

final readonly class SupportedAcquisitionFieldDescriptor
{
    /** @var list<PredicateKey> */
    public array $contextPredicateKeys;

    /**
     * @param  list<PredicateKey>  $contextPredicateKeys
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $group,
        public SupportedAcquisitionFieldEditorKind $editorKind,
        public bool $repeatable,
        public SupportedAcquisitionFieldMappingKind $mappingKind,
        public string $subjectMentionKind,
        public ?PredicateKey $predicateKey = null,
        public ?ClaimValueType $literalValueType = null,
        public ?string $objectMentionKind = null,
        public ?string $helpText = null,
        array $contextPredicateKeys = [],
    ) {
        if (preg_match('/^[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)+$/D', $key) !== 1) {
            throw new InvalidArgumentException('Supported acquisition field key must be a stable dotted lowercase identifier.');
        }

        if (trim($label) === '' || trim($group) === '') {
            throw new InvalidArgumentException('Supported acquisition field label and group must not be empty.');
        }

        if (! in_array($subjectMentionKind, [
            MentionKind::PERSON,
            MentionKind::EVENT,
            MentionKind::PLACE,
            MentionKind::ORGANIZATION,
            MentionKind::OTHER,
        ], true)) {
            throw new InvalidArgumentException('Supported acquisition field subject Mention kind must be canonical.');
        }

        if ($mappingKind === SupportedAcquisitionFieldMappingKind::DirectClaim) {
            if ($predicateKey === null || $contextPredicateKeys !== []) {
                throw new InvalidArgumentException('Direct Claim fields require one Predicate and cannot define context Predicates.');
            }

            if (($literalValueType === null) === ($objectMentionKind === null)) {
                throw new InvalidArgumentException('Direct Claim fields require exactly one literal or Mention-object contract.');
            }
        } else {
            if ($predicateKey !== null || $literalValueType !== null || $objectMentionKind !== null || $contextPredicateKeys === []) {
                throw new InvalidArgumentException('Reified context fields require context Predicates and no direct Predicate contract.');
            }
        }

        $this->contextPredicateKeys = array_values($contextPredicateKeys);
    }

    public function isDirectClaim(): bool
    {
        return $this->mappingKind === SupportedAcquisitionFieldMappingKind::DirectClaim;
    }
}
