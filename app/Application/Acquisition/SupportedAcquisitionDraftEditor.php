<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\Claim;
use App\Domain\Acquisition\ClaimCertainty;
use App\Domain\Acquisition\ClaimId;
use App\Domain\Acquisition\ClaimQualifiers;
use App\Domain\Acquisition\Mention;
use App\Domain\Acquisition\MentionId;
use App\Domain\Acquisition\MentionKind;
use App\Domain\Acquisition\MentionRawData;
use InvalidArgumentException;

final readonly class SupportedAcquisitionDraftEditor
{
    public function __construct(
        private SupportedAcquisitionFieldCatalog $catalog,
        private SupportedAcquisitionFieldMapper $fieldMapper,
        private SupportedAcquisitionFieldValueFactory $valueFactory,
        private SourceIdentifierGenerator $identifierGenerator,
    ) {}

    public function changes(SourceDraft $draft, SupportedAcquisitionEditInput $input): SourceDraftChanges
    {
        [$addMentions, $updateMentions, $removeMentionIds, $mentionsByLocalKey, $newEventIds] = $this->mentionChanges($draft, $input);
        [$addClaims, $updateClaims, $removeClaimIds, $claimsBySubject] = $this->claimChanges(
            $draft,
            $input,
            $mentionsByLocalKey,
        );

        foreach ($newEventIds as $eventId) {
            $event = $this->findMentionById($addMentions, $eventId);
            if ($event !== null) {
                $this->fieldMapper->reifiedEventContext($event, $claimsBySubject[$eventId] ?? []);
            }
        }

        return new SourceDraftChanges(
            addMentions: $addMentions,
            updateMentions: $updateMentions,
            removeMentionIds: $removeMentionIds,
            addClaims: $addClaims,
            updateClaims: $updateClaims,
            removeClaimIds: $removeClaimIds,
        );
    }

    /**
     * @return array{0: list<Mention>, 1: list<Mention>, 2: list<MentionId>, 3: array<string, Mention>, 4: list<string>}
     */
    private function mentionChanges(SourceDraft $draft, SupportedAcquisitionEditInput $input): array
    {
        $currentById = [];
        foreach ($draft->current->mentions as $mention) {
            $currentById[$mention->id->value] = $mention;
        }

        $mentionInputs = $input->mentions;
        foreach ($input->eventContexts as $eventContext) {
            $mentionInputs[] = $eventContext->event;
        }

        $add = [];
        $update = [];
        $seenIds = [];
        $mentionsByLocalKey = [];
        $newEventIds = [];

        foreach ($mentionInputs as $mentionInput) {
            $existing = null;
            if ($mentionInput->id !== null) {
                $id = $mentionInput->id->value;
                $existing = $currentById[$id] ?? null;
                if ($existing === null || isset($seenIds[$id])) {
                    throw new InvalidArgumentException('Mention identity is invalid for this SourceDraft.');
                }
                $seenIds[$id] = true;
            }

            $mention = new Mention(
                id: $existing->id ?? $this->identifierGenerator->mentionId(),
                sourceId: $draft->current->source->id,
                kind: $mentionInput->kind,
                localKey: $mentionInput->localKey,
                role: $mentionInput->role,
                displayLabel: $mentionInput->displayLabel,
                rawData: $mentionInput->rawData ?? MentionRawData::empty(),
            );

            if (isset($mentionsByLocalKey[$mention->localKey])) {
                throw new InvalidArgumentException(sprintf(
                    'Mention local key "%s" is duplicated within the Source.',
                    $mention->localKey,
                ));
            }
            $mentionsByLocalKey[$mention->localKey] = $mention;

            if ($existing === null) {
                $add[] = $mention;
                if ($mention->kind->key === MentionKind::EVENT) {
                    $newEventIds[] = $mention->id->value;
                }
            } else {
                $update[] = $mention;
            }
        }

        $remove = [];
        foreach ($currentById as $id => $mention) {
            if (! isset($seenIds[$id])) {
                $remove[] = $mention->id;
            }
        }

        return [$add, $update, $remove, $mentionsByLocalKey, $newEventIds];
    }

    /**
     * @param  array<string, Mention>  $mentionsByLocalKey
     * @return array{0: list<Claim>, 1: list<Claim>, 2: list<ClaimId>, 3: array<string, list<Claim>>}
     */
    private function claimChanges(
        SourceDraft $draft,
        SupportedAcquisitionEditInput $input,
        array $mentionsByLocalKey,
    ): array {
        $currentById = [];
        foreach ($draft->current->claims as $claim) {
            $currentById[$claim->id->value] = $claim;
        }

        $claimInputs = $input->fields;
        foreach ($input->eventContexts as $eventContext) {
            foreach ($eventContext->claims as $claimInput) {
                if ($claimInput->subjectLocalKey !== $eventContext->event->localKey) {
                    throw new InvalidArgumentException('Event context Claim subject must be the context event Mention.');
                }
                $claimInputs[] = $claimInput;
            }
        }

        $add = [];
        $update = [];
        $seenIds = [];
        $claimsBySubject = [];

        foreach ($claimInputs as $claimInput) {
            $descriptor = $this->catalog->get($claimInput->fieldKey);
            if (! $descriptor->isDirectClaim()) {
                throw new InvalidArgumentException(sprintf(
                    'Supported acquisition field "%s" is not a direct Claim field.',
                    $claimInput->fieldKey,
                ));
            }

            $subject = $mentionsByLocalKey[$claimInput->subjectLocalKey] ?? null;
            if ($subject === null) {
                throw new InvalidArgumentException(sprintf(
                    'Subject Mention local key "%s" does not exist in this Source.',
                    $claimInput->subjectLocalKey,
                ));
            }

            $existing = null;
            if ($claimInput->id !== null) {
                $id = $claimInput->id->value;
                $existing = $currentById[$id] ?? null;
                if ($existing === null || isset($seenIds[$id])) {
                    throw new InvalidArgumentException('Claim identity is invalid for this SourceDraft.');
                }
                $seenIds[$id] = true;
            }

            $object = null;
            $value = null;
            if ($descriptor->editorKind === SupportedAcquisitionFieldEditorKind::MentionReference) {
                if ($claimInput->objectLocalKey === null || $claimInput->objectLocalKey === '') {
                    throw new InvalidArgumentException(sprintf(
                        'Supported acquisition field "%s" requires an object Mention local key.',
                        $claimInput->fieldKey,
                    ));
                }
                $object = $mentionsByLocalKey[$claimInput->objectLocalKey] ?? null;
                if ($object === null) {
                    throw new InvalidArgumentException(sprintf(
                        'Object Mention local key "%s" does not exist in this Source.',
                        $claimInput->objectLocalKey,
                    ));
                }
            } else {
                if ($claimInput->value === null) {
                    throw new InvalidArgumentException(sprintf(
                        'Supported acquisition field "%s" requires a typed literal value.',
                        $claimInput->fieldKey,
                    ));
                }
                $value = $this->valueFactory->make($descriptor, $claimInput->value);
            }

            $qualifiers = $claimInput->effectiveTime === null
                ? ClaimQualifiers::empty()
                : new ClaimQualifiers(effectiveTime: $this->valueFactory->effectiveTime($claimInput->effectiveTime));

            $claim = $this->fieldMapper->directClaim(
                fieldKey: $claimInput->fieldKey,
                claimId: $existing->id ?? $this->identifierGenerator->claimId(),
                sourceId: $draft->current->source->id,
                subject: $subject,
                value: $value,
                object: $object,
                qualifiers: $qualifiers,
                rawText: $claimInput->rawText,
                origin: $existing?->origin,
                transcriptionCertainty: new ClaimCertainty($claimInput->transcriptionCertainty),
                interpretationCertainty: new ClaimCertainty($claimInput->interpretationCertainty),
            );

            $claimsBySubject[$claim->subjectMentionId->value] ??= [];
            $claimsBySubject[$claim->subjectMentionId->value][] = $claim;

            if ($existing === null) {
                $add[] = $claim;
            } else {
                $update[] = $claim;
            }
        }

        $remove = [];
        foreach ($currentById as $id => $claim) {
            if (! $this->catalog->has($claim->predicate->key->value)) {
                continue;
            }
            if (! isset($seenIds[$id])) {
                $remove[] = $claim->id;
            }
        }

        return [$add, $update, $remove, $claimsBySubject];
    }

    /** @param  list<Mention>  $mentions */
    private function findMentionById(array $mentions, string $id): ?Mention
    {
        foreach ($mentions as $mention) {
            if ($mention->id->value === $id) {
                return $mention;
            }
        }

        return null;
    }
}
