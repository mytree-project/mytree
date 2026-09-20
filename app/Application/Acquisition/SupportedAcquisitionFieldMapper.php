<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\Claim;
use App\Domain\Acquisition\ClaimCertainty;
use App\Domain\Acquisition\ClaimId;
use App\Domain\Acquisition\ClaimOrigin;
use App\Domain\Acquisition\ClaimQualifiers;
use App\Domain\Acquisition\ClaimValue;
use App\Domain\Acquisition\Mention;
use App\Domain\Acquisition\PredicateVocabulary;
use App\Domain\Acquisition\SourceId;
use InvalidArgumentException;

final readonly class SupportedAcquisitionFieldMapper
{
    public function __construct(
        private SupportedAcquisitionFieldCatalog $catalog,
    ) {}

    public function directClaim(
        string $fieldKey,
        ClaimId $claimId,
        SourceId $sourceId,
        Mention $subject,
        ?ClaimValue $value = null,
        ?Mention $object = null,
        ?ClaimQualifiers $qualifiers = null,
        ?string $rawText = null,
        ?ClaimOrigin $origin = null,
        ?ClaimCertainty $transcriptionCertainty = null,
        ?ClaimCertainty $interpretationCertainty = null,
    ): Claim {
        $descriptor = $this->catalog->get($fieldKey);
        if (! $descriptor->isDirectClaim() || $descriptor->predicateKey === null) {
            throw new InvalidArgumentException(sprintf(
                'Supported acquisition field "%s" is not a direct Claim field.',
                $fieldKey,
            ));
        }

        $this->assertSourceLocal($sourceId, $subject, 'subject');
        $predicate = PredicateVocabulary::get($descriptor->predicateKey);
        $predicate->assertSubjectKind($subject->kind);

        if ($descriptor->literalValueType !== null) {
            if ($value === null || $object !== null) {
                throw new InvalidArgumentException(sprintf(
                    'Supported acquisition field "%s" requires a literal value and forbids an object Mention.',
                    $fieldKey,
                ));
            }

            $predicate->assertLiteralValue($value);
        } else {
            if ($object === null || $value !== null) {
                throw new InvalidArgumentException(sprintf(
                    'Supported acquisition field "%s" requires an object Mention and forbids a literal value.',
                    $fieldKey,
                ));
            }

            $this->assertSourceLocal($sourceId, $object, 'object');
            $predicate->assertObjectKind($object->kind);
        }

        return new Claim(
            id: $claimId,
            sourceId: $sourceId,
            subjectMentionId: $subject->id,
            predicate: $predicate,
            objectMentionId: $object?->id,
            value: $value,
            qualifiers: $qualifiers,
            rawText: $rawText,
            origin: $origin,
            transcriptionCertainty: $transcriptionCertainty,
            interpretationCertainty: $interpretationCertainty,
        );
    }

    private function assertSourceLocal(SourceId $sourceId, Mention $mention, string $role): void
    {
        if ($mention->sourceId->value !== $sourceId->value) {
            throw new InvalidArgumentException(sprintf(
                'Supported acquisition field %s Mention must belong to the edited Source.',
                $role,
            ));
        }
    }
}
