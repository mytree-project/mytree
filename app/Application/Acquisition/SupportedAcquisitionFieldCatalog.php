<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\ClaimValueType;
use App\Domain\Acquisition\MentionKind;
use App\Domain\Acquisition\Predicate;
use App\Domain\Acquisition\PredicateKey;
use App\Domain\Acquisition\PredicateVocabulary;
use InvalidArgumentException;

final class SupportedAcquisitionFieldCatalog
{
    public const SCHEMA_VERSION = 1;

    public const EVENT_CONTEXT_KEY = 'event.context';

    /** @var array<string, SupportedAcquisitionFieldDescriptor> */
    private array $descriptors;

    public function __construct()
    {
        $descriptors = [];

        foreach (PredicateVocabulary::all() as $predicate) {
            $descriptor = $this->directDescriptor($predicate);
            $descriptors[$descriptor->key] = $descriptor;
        }

        $eventContext = new SupportedAcquisitionFieldDescriptor(
            key: self::EVENT_CONTEXT_KEY,
            label: 'Event context',
            group: 'Event contexts',
            editorKind: SupportedAcquisitionFieldEditorKind::EventContext,
            repeatable: true,
            mappingKind: SupportedAcquisitionFieldMappingKind::ReifiedContext,
            subjectMentionKind: MentionKind::EVENT,
            helpText: 'Groups one source-local event Mention with atomic date, place, participant-role and reason Claims.',
            contextPredicateKeys: [
                PredicateKey::EventDate,
                PredicateKey::EventPlace,
                PredicateKey::EventParticipant,
                PredicateKey::EventChild,
                PredicateKey::EventParent,
                PredicateKey::EventSpouse,
                PredicateKey::EventWitness,
                PredicateKey::EventDeclarant,
                PredicateKey::EventOfficiant,
                PredicateKey::EventOriginPlace,
                PredicateKey::EventDestinationPlace,
                PredicateKey::EventReason,
            ],
        );
        $descriptors[$eventContext->key] = $eventContext;

        ksort($descriptors, SORT_STRING);
        $this->descriptors = $descriptors;
    }

    /** @return list<SupportedAcquisitionFieldDescriptor> */
    public function all(): array
    {
        return array_values($this->descriptors);
    }

    public function get(string $key): SupportedAcquisitionFieldDescriptor
    {
        return $this->descriptors[$key] ?? throw new InvalidArgumentException(sprintf(
            'Unsupported acquisition field "%s".',
            $key,
        ));
    }

    public function has(string $key): bool
    {
        return isset($this->descriptors[$key]);
    }

    /** @return list<SupportedAcquisitionFieldDescriptor> */
    public function forSubjectMentionKind(string $mentionKind): array
    {
        return array_values(array_filter(
            $this->all(),
            static fn (SupportedAcquisitionFieldDescriptor $descriptor): bool => $descriptor->subjectMentionKind === $mentionKind,
        ));
    }

    private function directDescriptor(Predicate $predicate): SupportedAcquisitionFieldDescriptor
    {
        $key = $predicate->key->value;

        return new SupportedAcquisitionFieldDescriptor(
            key: $key,
            label: $this->label($predicate->key),
            group: $this->group($predicate->key),
            editorKind: $predicate->literalValueType === null
                ? SupportedAcquisitionFieldEditorKind::MentionReference
                : $this->literalEditor($predicate->literalValueType),
            repeatable: true,
            mappingKind: SupportedAcquisitionFieldMappingKind::DirectClaim,
            subjectMentionKind: $predicate->subjectMentionKind,
            predicateKey: $predicate->key,
            literalValueType: $predicate->literalValueType,
            objectMentionKind: $predicate->objectMentionKind,
            helpText: $this->helpText($predicate->key),
        );
    }

    private function literalEditor(ClaimValueType $valueType): SupportedAcquisitionFieldEditorKind
    {
        return match ($valueType) {
            ClaimValueType::Text => SupportedAcquisitionFieldEditorKind::Text,
            ClaimValueType::Integer => SupportedAcquisitionFieldEditorKind::Integer,
            ClaimValueType::Date => SupportedAcquisitionFieldEditorKind::Date,
            ClaimValueType::Age => SupportedAcquisitionFieldEditorKind::Age,
            ClaimValueType::Boolean => SupportedAcquisitionFieldEditorKind::Boolean,
            ClaimValueType::Enum => SupportedAcquisitionFieldEditorKind::Enum,
        };
    }

    private function group(PredicateKey $key): string
    {
        return match (true) {
            str_starts_with($key->value, 'person.') => 'Person facts',
            str_starts_with($key->value, 'event.') => 'Event facts',
            str_starts_with($key->value, 'place.') => 'Place facts',
            default => 'Source facts',
        };
    }

    private function label(PredicateKey $key): string
    {
        return match ($key) {
            PredicateKey::PersonGivenName => 'Given name',
            PredicateKey::PersonSurname => 'Surname',
            PredicateKey::PersonAge => 'Age',
            PredicateKey::PersonBirthDate => 'Birth date',
            PredicateKey::PersonDeathDate => 'Death date',
            PredicateKey::PersonParent => 'Parent',
            PredicateKey::PersonSpouse => 'Spouse',
            PredicateKey::PersonBirthPlace => 'Birth place',
            PredicateKey::PersonResidence => 'Residence',
            PredicateKey::PersonPermanentResidence => 'Permanent residence',
            PredicateKey::PersonTemporaryStay => 'Temporary stay',
            PredicateKey::PersonPresence => 'Presence',
            PredicateKey::PersonAddress => 'Address',
            PredicateKey::PersonOrigin => 'Origin',
            PredicateKey::PersonWorkPlace => 'Work place',
            PredicateKey::PersonStudyPlace => 'Study place',
            PredicateKey::PersonDetentionPlace => 'Detention place',
            PredicateKey::PersonExilePlace => 'Exile place',
            PredicateKey::PersonDeportationDestination => 'Deportation destination',
            PredicateKey::PersonOccupation => 'Occupation',
            PredicateKey::PersonSocialStatus => 'Social status',
            PredicateKey::PersonSocialEstate => 'Social estate',
            PredicateKey::PersonOffice => 'Office',
            PredicateKey::PersonRank => 'Rank',
            PredicateKey::PersonTitle => 'Title',
            PredicateKey::PersonAcademicDegree => 'Academic degree',
            PredicateKey::EventDate => 'Event date',
            PredicateKey::EventPlace => 'Event place',
            PredicateKey::EventParticipant => 'Participant',
            PredicateKey::EventChild => 'Child',
            PredicateKey::EventParent => 'Parent participant',
            PredicateKey::EventSpouse => 'Spouse / party',
            PredicateKey::EventWitness => 'Witness',
            PredicateKey::EventDeclarant => 'Declarant',
            PredicateKey::EventOfficiant => 'Officiant',
            PredicateKey::EventOriginPlace => 'Origin place',
            PredicateKey::EventDestinationPlace => 'Destination place',
            PredicateKey::EventReason => 'Event reason',
            PredicateKey::PlaceName => 'Place name',
        };
    }

    private function helpText(PredicateKey $key): ?string
    {
        return match ($key) {
            PredicateKey::PersonOccupation => 'Work or profession actually performed; do not use for estate, office, rank, title or degree.',
            PredicateKey::PersonSocialStatus => 'Source-recorded social position that is not adequately represented as a formal estate.',
            PredicateKey::PersonSocialEstate => 'Explicit formal or historically specific social/legal estate.',
            PredicateKey::PersonOffice => 'Institutional office or function held by the person.',
            PredicateKey::PersonRank => 'Formal military, civil-service or comparable rank.',
            PredicateKey::PersonTitle => 'Formal, honorific or noble title; not an occupation, office, rank or degree.',
            PredicateKey::PersonAcademicDegree => 'Academic degree explicitly attributed by the Source.',
            default => null,
        };
    }
}
