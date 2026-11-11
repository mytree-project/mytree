<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\AgeAtEventBirthInterval;
use App\Domain\Acquisition\AgeClaimValue;
use App\Domain\Acquisition\Claim;
use App\Domain\Acquisition\ClaimDerivation;
use App\Domain\Acquisition\ClaimOriginKind;
use App\Domain\Acquisition\ClaimRevision;
use App\Domain\Acquisition\DateClaimDayBounds;
use App\Domain\Acquisition\DateClaimValue;
use App\Domain\Acquisition\MentionId;
use App\Domain\Acquisition\PredicateKey;
use App\Domain\Acquisition\PredicateVocabulary;
use App\Domain\Acquisition\SourceId;

/**
 * Automatically accepts only unambiguous source-local age-at-event results.
 *
 * #238 owns general incremental rule migration and background reconciliation;
 * this v1 rule reconciles one Source before its EvidenceState is captured.
 */
final readonly class ReconcileAgeAtEventBirthClaims
{
    public const RULE_ID = 'person.birth_date.from_age_at_event';

    public const RULE_VERSION = '1';

    public const IMPLEMENTATION_SIGNATURE = 'proleptic-gregorian-completed-years-v1';

    /** @var list<PredicateKey> */
    private const PARTICIPANT_ROLES = [
        PredicateKey::EventParticipant,
        PredicateKey::EventChild,
        PredicateKey::EventParent,
        PredicateKey::EventSpouse,
        PredicateKey::EventWitness,
        PredicateKey::EventDeclarant,
        PredicateKey::EventOfficiant,
    ];

    public function __construct(
        private ClaimRepository $claims,
        private ClaimRevisionRepository $claimRevisions,
        private MentionRevisionRepository $mentionRevisions,
        private AgeAtEventBirthInterval $calculator,
        private UpsertDerivedClaim $upsertDerivedClaim,
        private AcquisitionTransaction $transaction,
    ) {}

    public function reconcile(SourceId $sourceId): void
    {
        $this->transaction->run(function () use ($sourceId): void {
            $directRevisions = [];
            $materialized = [];

            foreach ($this->claims->forSource($sourceId) as $claim) {
                if ($claim->origin->kind === ClaimOriginKind::Derived) {
                    $derivation = $claim->derivation;
                    if ($derivation !== null && $derivation->ruleId === self::RULE_ID) {
                        $materialized[$derivation->outputKey] = $claim;
                    }

                    continue;
                }

                $revision = $this->claimRevisions->latestForClaim($sourceId, $claim->id)
                    ?? throw EvidenceStateCaptureIncomplete::missingClaimRevision($claim->id);
                $directRevisions[] = $revision;
            }

            /** @var array<string, list<ClaimRevision>> $agesByPerson */
            $agesByPerson = [];
            /** @var array<string, list<ClaimRevision>> $birthsByPerson */
            $birthsByPerson = [];
            /** @var array<string, list<ClaimRevision>> $datesByEvent */
            $datesByEvent = [];
            /** @var array<string, array<string, list<ClaimRevision>>> $rolesByPerson */
            $rolesByPerson = [];

            foreach ($directRevisions as $revision) {
                $claim = $revision->reconstruct()->claim;
                $subjectId = $claim->subjectMentionId->value;

                if ($claim->predicate->key === PredicateKey::PersonAge && $claim->value instanceof AgeClaimValue) {
                    $agesByPerson[$subjectId][] = $revision;
                } elseif ($claim->predicate->key === PredicateKey::PersonBirthDate) {
                    $birthsByPerson[$subjectId][] = $revision;
                } elseif ($claim->predicate->key === PredicateKey::EventDate && $claim->value instanceof DateClaimValue) {
                    $datesByEvent[$subjectId][] = $revision;
                } elseif (in_array($claim->predicate->key, self::PARTICIPANT_ROLES, true)
                    && $claim->objectMentionId !== null) {
                    $rolesByPerson[$claim->objectMentionId->value][$subjectId][] = $revision;
                }
            }

            ksort($agesByPerson, SORT_STRING);
            $keep = [];

            foreach ($agesByPerson as $personId => $ages) {
                foreach ($ages as $ageRevision) {
                    $ageClaim = $ageRevision->reconstruct()->claim;
                    $age = $ageClaim->value;
                    if (! $age instanceof AgeClaimValue) {
                        continue;
                    }

                    // A bare age with several independent mentions of age or
                    // several linked events has no unique effective event.
                    if ($ageClaim->qualifiers->effectiveTime === null
                        && (count($ages) !== 1 || count($rolesByPerson[$personId] ?? []) !== 1)) {
                        continue;
                    }

                    $contexts = [];
                    foreach ($rolesByPerson[$personId] ?? [] as $eventId => $roles) {
                        $eventDates = $datesByEvent[$eventId] ?? [];
                        if (count($eventDates) !== 1) {
                            continue;
                        }

                        $dateClaim = $eventDates[0]->reconstruct()->claim;
                        $eventDate = $dateClaim->value;
                        if (! $eventDate instanceof DateClaimValue) {
                            continue;
                        }

                        $effectiveTime = $ageClaim->qualifiers->effectiveTime;
                        if ($effectiveTime !== null
                            && DateClaimDayBounds::areDisjoint($effectiveTime, $eventDate) !== false) {
                            continue;
                        }

                        $contexts[$eventId] = ['date' => $eventDates[0], 'roles' => $roles];
                    }

                    if (count($contexts) !== 1) {
                        continue;
                    }

                    $eventId = array_key_first($contexts);
                    if ($eventId === null) {
                        continue;
                    }

                    $context = $contexts[$eventId];
                    $dateRevision = $context['date'];
                    $dateClaim = $dateRevision->reconstruct()->claim;
                    $eventDate = $dateClaim->value;
                    if (! $eventDate instanceof DateClaimValue) {
                        continue;
                    }

                    $birthWindow = $this->calculator->derive($age, $eventDate);
                    if ($birthWindow === null) {
                        continue;
                    }

                    // Suppress whenever even one comparable Direct birth Claim
                    // is compatible or cannot be safely classified. Only
                    // unambiguously disjoint Direct Claims retain a derived
                    // contradiction, without rewriting either Source assertion.
                    $directConflicts = [];
                    $suppress = false;
                    foreach ($birthsByPerson[$personId] ?? [] as $birthRevision) {
                        $birthClaim = $birthRevision->reconstruct()->claim;
                        $directBirth = $birthClaim->value;
                        if (! $directBirth instanceof DateClaimValue
                            || $birthClaim->qualifiers->effectiveTime !== null
                            || DateClaimDayBounds::areDisjoint($directBirth, $birthWindow) !== true) {
                            $suppress = true;

                            break;
                        }
                        $directConflicts[] = $birthRevision->id->value;
                    }

                    if ($suppress) {
                        continue;
                    }

                    $outputKey = 'birth:'.$personId.':age:'.$ageClaim->id->value;
                    $roots = [$ageRevision->id, $dateRevision->id];
                    foreach ($context['roles'] as $roleRevision) {
                        $roots[] = $roleRevision->id;
                    }

                    $personMentionRevision = $this->mentionRevisions->latestForMention(
                        $sourceId,
                        $ageClaim->subjectMentionId,
                    );
                    $eventMentionRevision = $this->mentionRevisions->latestForMention(
                        $sourceId,
                        $dateClaim->subjectMentionId,
                    );
                    if ($personMentionRevision === null || $eventMentionRevision === null) {
                        continue;
                    }

                    sort($directConflicts, SORT_STRING);
                    $derivation = new ClaimDerivation(
                        ruleId: self::RULE_ID,
                        ruleVersion: self::RULE_VERSION,
                        implementationSignature: self::IMPLEMENTATION_SIGNATURE,
                        outputKey: $outputKey,
                        inputClaimRevisionIds: $roots,
                        inputMentionRevisionIds: [$personMentionRevision->id, $eventMentionRevision->id],
                        assumptions: [
                            'age_expression_kind' => $age->kind->value,
                            'age_unit_semantics' => 'completed_years',
                            'birth_value_semantics' => 'conditional_single_birth_instant',
                            'calendar' => 'proleptic_gregorian',
                            'direct_birth_discrepancy_revision_ids' => $directConflicts,
                            'event_date_expression_kind' => $eventDate->kind->value,
                            'event_date_precision' => $eventDate->from->precision()->value,
                            'hard_identity_exclusion_allowed' => false,
                            'soft_evidence' => true,
                        ],
                    );

                    $this->upsertDerivedClaim->handle(
                        sourceId: $sourceId,
                        subjectMentionId: new MentionId($personId),
                        predicate: PredicateVocabulary::get(PredicateKey::PersonBirthDate),
                        value: $birthWindow,
                        derivation: $derivation,
                    );

                    $keep[$outputKey] = true;
                }
            }

            // Retire materialized results whose inputs became ambiguous,
            // whose age disappeared, or which Direct birth evidence suppresses.
            // ClaimRevisions and historical EvidenceStates remain immutable.
            foreach ($materialized as $outputKey => $claim) {
                if (! isset($keep[$outputKey])) {
                    $this->claims->remove($sourceId, $claim->id);
                }
            }
        });
    }
}
