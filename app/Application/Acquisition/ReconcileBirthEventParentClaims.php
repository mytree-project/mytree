<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\Claim;
use App\Domain\Acquisition\ClaimDerivation;
use App\Domain\Acquisition\ClaimOriginKind;
use App\Domain\Acquisition\ClaimRevision;
use App\Domain\Acquisition\EnumClaimValue;
use App\Domain\Acquisition\EventTypeClaimValueKey;
use App\Domain\Acquisition\MentionId;
use App\Domain\Acquisition\PredicateKey;
use App\Domain\Acquisition\PredicateVocabulary;
use App\Domain\Acquisition\SourceId;

/**
 * One structural, Source-local birth event can support child -> parent.
 *
 * Birth type, child and parent are explicit Direct Claims on the same event
 * Mention. Neither baptism nor SourceType alone implies a birth event.
 * #238 owns global background rule upgrades; this runs before Source capture.
 */
final readonly class ReconcileBirthEventParentClaims
{
    public const RULE_ID = 'person.parent.from_birth_event_roles';

    public const RULE_VERSION = '1';

    public const IMPLEMENTATION_SIGNATURE = 'typed-birth-event-child-parent-v1';

    public function __construct(
        private ClaimRepository $claims,
        private ClaimRevisionRepository $claimRevisions,
        private MentionRevisionRepository $mentionRevisions,
        private UpsertDerivedClaim $upsertDerivedClaim,
        private AcquisitionTransaction $transaction,
    ) {}

    public function reconcile(SourceId $sourceId): void
    {
        $this->transaction->run(function () use ($sourceId): void {
            /** @var array<string, list<ClaimRevision>> $typesByEvent */
            $typesByEvent = [];
            /** @var array<string, array<string, list<ClaimRevision>>> $childrenByEvent */
            $childrenByEvent = [];
            /** @var array<string, array<string, list<ClaimRevision>>> $parentsByEvent */
            $parentsByEvent = [];
            /** @var array<string, array<string, bool>> $directParentPairs */
            $directParentPairs = [];
            /** @var array<string, Claim> $materialized */
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
                $subject = $claim->subjectMentionId->value;
                $object = $claim->objectMentionId?->value;

                switch ($claim->predicate->key) {
                    case PredicateKey::EventType:
                        $typesByEvent[$subject][] = $revision;

                        break;
                    case PredicateKey::EventChild:
                        if ($object !== null) {
                            $childrenByEvent[$subject][$object][] = $revision;
                        }

                        break;
                    case PredicateKey::EventParent:
                        if ($object !== null) {
                            $parentsByEvent[$subject][$object][] = $revision;
                        }

                        break;
                    case PredicateKey::PersonParent:
                        if ($object !== null) {
                            $directParentPairs[$subject][$object] = true;
                        }

                        break;
                    default:
                        break;
                }
            }

            /**
             * @var array<string, array{
             *     child: MentionId,
             *     parent: MentionId,
             *     claims: array<string, ClaimRevision>,
             *     events: array<string, true>
             * }> $candidates
             */
            $candidates = [];

            ksort($typesByEvent, SORT_STRING);
            foreach ($typesByEvent as $eventId => $types) {
                // Even a consistent pair of different type assertions needs
                // review before being used as a single, accepted event type.
                if (count($types) !== 1) {
                    continue;
                }

                $eventTypeClaim = $types[0]->reconstruct()->claim;
                if (! $eventTypeClaim->value instanceof EnumClaimValue
                    || $eventTypeClaim->value->key !== EventTypeClaimValueKey::Birth->value) {
                    continue;
                }

                $children = $childrenByEvent[$eventId] ?? [];
                $parents = $parentsByEvent[$eventId] ?? [];

                // Multiple different children or more than two different
                // parents are not an unambiguous v1 birth-event structure.
                if (count($children) !== 1 || count($parents) < 1 || count($parents) > 2) {
                    continue;
                }

                $childId = array_key_first($children);
                if (isset($parents[$childId])) {
                    continue;
                }

                ksort($parents, SORT_STRING);
                foreach ($parents as $parentId => $parentRoles) {
                    // A directly asserted identical parent edge is primary;
                    // another parent is not automatically a contradiction.
                    if (isset($directParentPairs[$childId][$parentId])) {
                        continue;
                    }

                    $key = 'child:'.$childId.':parent:'.$parentId;
                    if (! isset($candidates[$key])) {
                        $candidates[$key] = [
                            'child' => new MentionId($childId),
                            'parent' => new MentionId($parentId),
                            'claims' => [],
                            'events' => [],
                        ];
                    }

                    $roots = array_merge([$types[0]], $children[$childId], $parentRoles);
                    foreach ($roots as $root) {
                        $candidates[$key]['claims'][$root->id->value] = $root;
                    }

                    $candidates[$key]['events'][$eventId] = true;
                }
            }

            ksort($candidates, SORT_STRING);
            $keep = [];

            foreach ($candidates as $outputKey => $candidate) {
                $child = $candidate['child'];
                $parent = $candidate['parent'];

                $mentionRevisionIds = [];
                $eventIds = array_keys($candidate['events']);
                sort($eventIds, SORT_STRING);

                foreach (array_merge([$child, $parent], array_map(
                    static fn (string $id): MentionId => new MentionId($id),
                    $eventIds,
                )) as $mentionId) {
                    $revision = $this->mentionRevisions->latestForMention($sourceId, $mentionId)
                        ?? throw EvidenceStateCaptureIncomplete::missingMentionRevision($mentionId);
                    $mentionRevisionIds[] = $revision->id;
                }

                $derivation = new ClaimDerivation(
                    ruleId: self::RULE_ID,
                    ruleVersion: self::RULE_VERSION,
                    implementationSignature: self::IMPLEMENTATION_SIGNATURE,
                    outputKey: $outputKey,
                    inputClaimRevisionIds: array_map(
                        static fn (ClaimRevision $revision) => $revision->id,
                        array_values($candidate['claims']),
                    ),
                    inputMentionRevisionIds: $mentionRevisionIds,
                    assumptions: [
                        'event_type' => EventTypeClaimValueKey::Birth->value,
                        'birth_event_mention_ids' => $eventIds,
                        'relation_direction' => 'child_to_parent',
                        'structural_evidence' => true,
                    ],
                );

                $this->upsertDerivedClaim->handle(
                    sourceId: $sourceId,
                    subjectMentionId: $child,
                    predicate: PredicateVocabulary::get(PredicateKey::PersonParent),
                    objectMentionId: $parent,
                    derivation: $derivation,
                );

                $keep[$outputKey] = true;
            }

            // Historical ClaimRevisions and EvidenceStates are immutable.
            // Retire only now-ineligible current materializations of this rule.
            foreach ($materialized as $outputKey => $claim) {
                if (! isset($keep[$outputKey])) {
                    $this->claims->remove($sourceId, $claim->id);
                }
            }
        });
    }
}
