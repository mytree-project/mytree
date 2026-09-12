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
use App\Domain\Acquisition\MentionKind;
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

    /**
     * Builds an explicit SourceDraft graph fragment for one new source-local event context.
     * Claims must already have been built through the controlled direct-field mappings.
     *
     * @param  list<Claim>  $claims
     */
    public function reifiedEventContext(Mention $eventMention, array $claims): SourceDraftChanges
    {
        $descriptor = $this->catalog->get(SupportedAcquisitionFieldCatalog::EVENT_CONTEXT_KEY);

        if ($eventMention->kind->key !== MentionKind::EVENT) {
            throw new InvalidArgumentException('Reified event context requires an event Mention.');
        }

        $allowedPredicates = array_map(
            static fn ($key): string => $key->value,
            $descriptor->contextPredicateKeys,
        );

        foreach ($claims as $claim) {
            if ($claim->sourceId->value !== $eventMention->sourceId->value) {
                throw new InvalidArgumentException('Reified event context Claims must belong to the event Source.');
            }

            if ($claim->subjectMentionId->value !== $eventMention->id->value) {
                throw new InvalidArgumentException('Reified event context Claims must use the event Mention as subject.');
            }

            if (! in_array($claim->predicate->key->value, $allowedPredicates, true)) {
                throw new InvalidArgumentException(sprintf(
                    'Predicate "%s" is not supported by the reified event context field.',
                    $claim->predicate->key->value,
                ));
            }
        }

        return new SourceDraftChanges(
            addMentions: [$eventMention],
            addClaims: array_values($claims),
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
