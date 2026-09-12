<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\CanonicalJson;
use App\Domain\Acquisition\Claim;
use App\Domain\Acquisition\Mention;
use App\Domain\Acquisition\MentionRevisionSnapshot;
use App\Domain\Acquisition\SourceAsset;
use App\Domain\Acquisition\SourceLocator;
use App\Domain\Acquisition\SourceLocatorValueSerializer;

final readonly class SaveSourceDraft
{
    public function __construct(
        private LoadSourceDraft $loader,
        private BuildSourceDraftState $builder,
        private ValidateSourceDraft $validator,
        private SourceRepository $sources,
        private SourceAssetRepository $assets,
        private RecordSourceRevision $sourceRevisions,
        private MentionRepository $mentions,
        private MentionRevisionRepository $mentionRevisions,
        private MentionRevisionClock $mentionRevisionClock,
        private ClaimRepository $claims,
        private SourceLocatorRepository $locators,
        private ClaimRevisionRecorder $claimRevisions,
        private SourceIdentifierGenerator $identifiers,
        private CaptureEvidenceStateForSource $captureEvidenceState,
        private AcquisitionTransaction $transaction,
    ) {}

    public function handle(SourceDraft $draft): SourceDraftSaveResult
    {
        return $this->transaction->run(function () use ($draft): SourceDraftSaveResult {
            $working = $this->verifyAndRefresh($draft);

            try {
                $result = $this->builder->project($working);
            } catch (SourceDraftOperationInvalid $exception) {
                throw new InvalidSourceDraft(new SourceDraftValidationResult([
                    new SourceDraftValidationIssue(
                        code: 'draft.operation.invalid',
                        severity: SourceDraftValidationIssue::ERROR,
                        path: $exception->path,
                        message: $exception->getMessage(),
                    ),
                ]));
            }

            $validation = $this->validator->validateState($result);
            if (! $validation->isValid()) {
                throw new InvalidSourceDraft($validation);
            }

            if (! $working->isNew && hash_equals($working->current->semanticHash(), $result->semanticHash())) {
                return new SourceDraftSaveResult(
                    draft: new SourceDraft(
                        current: $working->current,
                        baseState: $working->baseState,
                        changes: SourceDraftChanges::none(),
                        isNew: false,
                    ),
                    evidenceStateId: $working->baseState?->evidenceStateId,
                    changed: false,
                );
            }

            $this->persistSourceState($working->current, $result, $working->isNew, $draft);
            $this->persistMentionUpserts($working->current, $result, $draft);
            $this->persistClaims($working->current, $result, $draft);
            $this->persistMentionRemovals($working->current, $result);

            $evidence = $this->captureEvidenceState->capture(
                sourceId: $result->source->id,
                changeNote: $draft->changeNote,
                changedBy: $draft->changedBy,
            );
            $sourceRevisionId = $evidence->snapshot->sourceRevisionIds[0]
                ?? throw new \LogicException('SourceDraft EvidenceState must contain the edited SourceRevision.');
            $base = SourceDraftBaseState::capture(
                sourceRevisionId: $sourceRevisionId,
                mentionRevisionIds: $evidence->snapshot->mentionRevisionIds,
                claimRevisionIds: $evidence->snapshot->claimRevisionIds,
                evidenceStateId: $evidence->id,
            );

            return new SourceDraftSaveResult(
                draft: new SourceDraft(
                    current: $result,
                    baseState: $base,
                    changes: SourceDraftChanges::none(),
                    isNew: false,
                ),
                evidenceStateId: $evidence->id,
                changed: true,
            );
        });
    }

    private function verifyAndRefresh(SourceDraft $draft): SourceDraft
    {
        $sourceId = $draft->current->source->id;

        if ($draft->isNew) {
            if ($this->sources->find($sourceId) !== null) {
                throw SourceDraftConflict::sourceAlreadyExists();
            }

            return $draft;
        }

        if ($draft->baseState === null) {
            throw SourceDraftConflict::stale();
        }

        $current = $this->loader->handle($sourceId);
        if ($current->baseState === null || ! $draft->baseState->matches($current->baseState)) {
            throw SourceDraftConflict::stale();
        }

        return new SourceDraft(
            current: $current->current,
            baseState: $current->baseState,
            changes: $draft->changes,
            isNew: false,
            changeNote: $draft->changeNote,
            changedBy: $draft->changedBy,
        );
    }

    private function persistSourceState(
        SourceDraftState $current,
        SourceDraftState $result,
        bool $isNew,
        SourceDraft $draft,
    ): void {
        if (! $isNew && hash_equals($current->sourceSemanticHash(), $result->sourceSemanticHash())) {
            return;
        }

        $this->sources->save($result->source);

        $currentAssets = $this->assetsById($current->assets);
        $resultAssets = $this->assetsById($result->assets);

        foreach ($currentAssets as $id => $asset) {
            if (! isset($resultAssets[$id])) {
                $this->assets->save($asset->detached());
            }
        }
        foreach ($resultAssets as $id => $asset) {
            if (! isset($currentAssets[$id])) {
                $this->assets->save($asset);
            }
        }

        $this->sourceRevisions->record($result->source, $draft->changeNote, $draft->changedBy);
    }

    private function persistMentionUpserts(SourceDraftState $current, SourceDraftState $result, SourceDraft $draft): void
    {
        $before = $this->mentionsById($current->mentions);

        foreach ($result->mentions as $mention) {
            $existing = $before[$mention->id->value] ?? null;
            if (! $existing instanceof Mention) {
                $this->mentions->add($mention);
                $this->recordMentionRevision($mention, $draft);
                continue;
            }

            if (! hash_equals($current->mentionSemanticHash($existing), $result->mentionSemanticHash($mention))) {
                $this->mentions->update($mention);
                $this->recordMentionRevision($mention, $draft);
            }
        }
    }

    private function persistClaims(SourceDraftState $current, SourceDraftState $result, SourceDraft $draft): void
    {
        $before = $this->claimsById($current->claims);
        $after = $this->claimsById($result->claims);

        foreach ($before as $id => $claim) {
            if (isset($after[$id])) {
                continue;
            }

            foreach ($current->locatorsForClaim($claim->id) as $locator) {
                $this->locators->remove($claim->sourceId, $claim->id, $locator->id);
            }
            $this->claims->remove($claim->sourceId, $claim->id);
        }

        foreach ($after as $id => $claim) {
            $existing = $before[$id] ?? null;
            if (! $existing instanceof Claim) {
                $this->claims->add($claim);
                $this->syncLocators([], $result->locatorsForClaim($claim->id));
                $this->claimRevisions->record($claim, $draft->changeNote, $draft->changedBy);
                continue;
            }

            if (hash_equals($current->claimSemanticHash($existing), $result->claimSemanticHash($claim))) {
                continue;
            }

            if (! hash_equals($current->claimObjectSemanticHash($existing), $result->claimObjectSemanticHash($claim))) {
                $this->claims->update($claim);
            }

            $this->syncLocators(
                $current->locatorsForClaim($existing->id),
                $result->locatorsForClaim($claim->id),
            );
            $this->claimRevisions->record($claim, $draft->changeNote, $draft->changedBy);
        }
    }

    private function persistMentionRemovals(SourceDraftState $current, SourceDraftState $result): void
    {
        $after = $this->mentionsById($result->mentions);
        foreach ($current->mentions as $mention) {
            if (! isset($after[$mention->id->value])) {
                $this->mentions->remove($mention->sourceId, $mention->id);
            }
        }
    }

    private function recordMentionRevision(Mention $mention, SourceDraft $draft): void
    {
        $this->mentionRevisions->append(
            revisionId: $this->identifiers->mentionRevisionId(),
            snapshot: MentionRevisionSnapshot::capture($mention),
            createdAt: $this->mentionRevisionClock->now(),
            changeNote: $draft->changeNote,
            changedBy: $draft->changedBy,
        );
    }

    /**
     * @param list<SourceLocator> $before
     * @param list<SourceLocator> $after
     */
    private function syncLocators(array $before, array $after): void
    {
        $beforeById = $this->locatorsById($before);
        $afterById = $this->locatorsById($after);

        foreach ($beforeById as $id => $locator) {
            if (! isset($afterById[$id])) {
                $this->locators->remove($locator->sourceId, $locator->claimId, $locator->id);
            }
        }

        foreach ($afterById as $id => $locator) {
            $existing = $beforeById[$id] ?? null;
            if (! $existing instanceof SourceLocator) {
                $this->locators->add($locator);
                continue;
            }

            if (! hash_equals($this->locatorHash($existing), $this->locatorHash($locator))) {
                $this->locators->update($locator);
            }
        }
    }

    private function locatorHash(SourceLocator $locator): string
    {
        return hash('sha256', CanonicalJson::encode([
            'id' => $locator->id->value,
            'source_id' => $locator->sourceId->value,
            'claim_id' => $locator->claimId->value,
            'source_asset_id' => $locator->sourceAssetId?->value,
            'schema_version' => $locator->schemaVersion,
            'value' => CanonicalJson::decodeObject(SourceLocatorValueSerializer::serialize($locator->value)),
        ]));
    }

    /**
     * @param list<SourceAsset> $assets
     * @return array<string, SourceAsset>
     */
    private function assetsById(array $assets): array
    {
        $result = [];
        foreach ($assets as $asset) {
            $result[$asset->id->value] = $asset;
        }

        return $result;
    }

    /**
     * @param list<Mention> $mentions
     * @return array<string, Mention>
     */
    private function mentionsById(array $mentions): array
    {
        $result = [];
        foreach ($mentions as $mention) {
            $result[$mention->id->value] = $mention;
        }

        return $result;
    }

    /**
     * @param list<Claim> $claims
     * @return array<string, Claim>
     */
    private function claimsById(array $claims): array
    {
        $result = [];
        foreach ($claims as $claim) {
            $result[$claim->id->value] = $claim;
        }

        return $result;
    }

    /**
     * @param list<SourceLocator> $locators
     * @return array<string, SourceLocator>
     */
    private function locatorsById(array $locators): array
    {
        $result = [];
        foreach ($locators as $locator) {
            $result[$locator->id->value] = $locator;
        }

        return $result;
    }
}
