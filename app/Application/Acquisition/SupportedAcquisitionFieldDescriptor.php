<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\ClaimValueType;
use App\Domain\Acquisition\MentionKind;
use App\Domain\Acquisition\PredicateKey;
use App\Domain\Acquisition\SourceLinguisticRepresentationRelation;
use InvalidArgumentException;

final readonly class SupportedAcquisitionFieldDescriptor
{
    /** @var list<PredicateKey> */
    public array $contextPredicateKeys;

    /** @var list<string> */
    public array $allowedEnumKeys;

    /** @var list<SourceLinguisticRepresentationRelation> */
    public array $sourceLinguisticRepresentationRelations;

    /**
     * @param  list<PredicateKey>  $contextPredicateKeys
     * @param  list<string>  $allowedEnumKeys
     * @param  list<SourceLinguisticRepresentationRelation>  $sourceLinguisticRepresentationRelations
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
        array $allowedEnumKeys = [],
        array $sourceLinguisticRepresentationRelations = [],
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

            if ($allowedEnumKeys !== [] && $literalValueType !== ClaimValueType::Enum) {
                throw new InvalidArgumentException('Direct Claim enum keys require an Enum literal value type.');
            }

            if ($sourceLinguisticRepresentationRelations !== [] && $literalValueType !== ClaimValueType::Text) {
                throw new InvalidArgumentException('Source linguistic representations are supported only by Text literal fields.');
            }
        } else {
            if ($predicateKey !== null
                || $literalValueType !== null
                || $objectMentionKind !== null
                || $contextPredicateKeys === []
                || $allowedEnumKeys !== []
                || $sourceLinguisticRepresentationRelations !== []) {
                throw new InvalidArgumentException('Mention preset fields require context Predicates and no direct Predicate contract.');
            }
        }

        $seenRelations = [];
        foreach ($sourceLinguisticRepresentationRelations as $relation) {
            if (isset($seenRelations[$relation->value])) {
                throw new InvalidArgumentException('Source linguistic representation relations must be unique.');
            }
            $seenRelations[$relation->value] = true;
        }

        $this->contextPredicateKeys = $contextPredicateKeys;
        $this->allowedEnumKeys = $allowedEnumKeys;
        $this->sourceLinguisticRepresentationRelations = $sourceLinguisticRepresentationRelations;
    }

    public function isDirectClaim(): bool
    {
        return $this->mappingKind === SupportedAcquisitionFieldMappingKind::DirectClaim;
    }

    public function supportsSourceLinguisticRepresentations(): bool
    {
        return $this->sourceLinguisticRepresentationRelations !== [];
    }
}
