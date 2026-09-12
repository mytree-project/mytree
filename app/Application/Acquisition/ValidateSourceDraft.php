<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\Claim;
use App\Domain\Acquisition\Mention;
use InvalidArgumentException;

final readonly class ValidateSourceDraft
{
    public function __construct(private BuildSourceDraftState $builder) {}

    public function handle(SourceDraft $draft): SourceDraftValidationResult
    {
        try {
            $state = $this->builder->project($draft);
        } catch (SourceDraftOperationInvalid $exception) {
            return new SourceDraftValidationResult([
                new SourceDraftValidationIssue(
                    code: 'draft.operation.invalid',
                    severity: SourceDraftValidationIssue::ERROR,
                    path: $exception->path,
                    message: $exception->getMessage(),
                ),
            ]);
        }

        return $this->validateState($state);
    }

    public function validateState(SourceDraftState $state): SourceDraftValidationResult
    {
        $issues = [];
        $sourceId = $state->source->id->value;
        /** @var array<string, Mention> $mentions */
        $mentions = [];
        /** @var array<string, true> $localKeys */
        $localKeys = [];
        /** @var array<string, true> $assets */
        $assets = [];
        /** @var array<string, Claim> $claims */
        $claims = [];

        foreach ($state->assets as $index => $asset) {
            $assets[$asset->id->value] = true;
            if ($asset->sourceId === null || $asset->sourceId->value !== $sourceId) {
                $issues[] = $this->error(
                    'draft.asset.cross_source',
                    "state.assets.$index.sourceId",
                    'SourceAsset must be attached to the edited Source.',
                );
            }
        }

        foreach ($state->mentions as $index => $mention) {
            $mentions[$mention->id->value] = $mention;
            if ($mention->sourceId->value !== $sourceId) {
                $issues[] = $this->error(
                    'draft.mention.cross_source',
                    "state.mentions.$index.sourceId",
                    'Mention must belong to the edited Source.',
                );
            }

            if (isset($localKeys[$mention->localKey])) {
                $issues[] = $this->error(
                    'draft.mention.local_key_duplicate',
                    "state.mentions.$index.localKey",
                    'Mention local keys must be unique within a Source.',
                );
            }
            $localKeys[$mention->localKey] = true;
        }

        foreach ($state->claims as $index => $claim) {
            $claims[$claim->id->value] = $claim;
            $path = "state.claims.$index";

            if ($claim->sourceId->value !== $sourceId) {
                $issues[] = $this->error('draft.claim.cross_source', "$path.sourceId", 'Claim must belong to the edited Source.');
                continue;
            }

            $subject = $mentions[$claim->subjectMentionId->value] ?? null;
            if (! $subject instanceof Mention) {
                $issues[] = $this->error(
                    'draft.claim.subject_missing',
                    "$path.subjectMentionId",
                    'Claim subject Mention must exist in the resulting Source graph.',
                );
            } else {
                $this->validateSubjectKind($claim, $subject, $path, $issues);
            }

            if ($claim->objectMentionId !== null) {
                $object = $mentions[$claim->objectMentionId->value] ?? null;
                if (! $object instanceof Mention) {
                    $issues[] = $this->error(
                        'draft.claim.object_missing',
                        "$path.objectMentionId",
                        'Claim object Mention must exist in the resulting Source graph.',
                    );
                } else {
                    $this->validateObjectKind($claim, $object, $path, $issues);
                }
            }
        }

        foreach ($state->locators as $index => $locator) {
            $path = "state.locators.$index";
            if ($locator->sourceId->value !== $sourceId) {
                $issues[] = $this->error(
                    'draft.locator.cross_source',
                    "$path.sourceId",
                    'SourceLocator must belong to the edited Source.',
                );
            }

            if (! isset($claims[$locator->claimId->value])) {
                $issues[] = $this->error(
                    'draft.locator.claim_missing',
                    "$path.claimId",
                    'SourceLocator must reference a Claim retained in the resulting graph.',
                );
            }

            if ($locator->sourceAssetId !== null && ! isset($assets[$locator->sourceAssetId->value])) {
                $issues[] = $this->error(
                    'draft.locator.asset_missing',
                    "$path.sourceAssetId",
                    'SourceLocator asset must remain attached to the edited Source.',
                );
            }
        }

        return new SourceDraftValidationResult($issues);
    }

    /** @param  list<SourceDraftValidationIssue>  $issues */
    private function validateSubjectKind(Claim $claim, Mention $subject, string $path, array &$issues): void
    {
        try {
            $claim->predicate->assertSubjectKind($subject->kind);
        } catch (InvalidArgumentException $exception) {
            $issues[] = $this->error('draft.claim.subject_kind_invalid', "$path.subjectMentionId", $exception->getMessage());
        }
    }

    /** @param  list<SourceDraftValidationIssue>  $issues */
    private function validateObjectKind(Claim $claim, Mention $object, string $path, array &$issues): void
    {
        try {
            $claim->predicate->assertObjectKind($object->kind);
        } catch (InvalidArgumentException $exception) {
            $issues[] = $this->error('draft.claim.object_kind_invalid', "$path.objectMentionId", $exception->getMessage());
        }
    }

    private function error(string $code, string $path, string $message): SourceDraftValidationIssue
    {
        return new SourceDraftValidationIssue(
            code: $code,
            severity: SourceDraftValidationIssue::ERROR,
            path: $path,
            message: $message,
        );
    }
}
