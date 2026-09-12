<?php

declare(strict_types=1);

namespace App\Application\Acquisition;

use App\Domain\Acquisition\Claim;
use App\Domain\Acquisition\ClaimRevisionId;
use App\Domain\Acquisition\Mention;
use App\Domain\Acquisition\MentionRevisionId;
use App\Domain\Acquisition\Source;
use App\Domain\Acquisition\SourceId;
use App\Domain\Acquisition\SourceLocator;
use App\Domain\Acquisition\SourceMetadata;
use App\Domain\Acquisition\SourceText;
use App\Domain\Acquisition\SourceType;

final readonly class LoadSourceDraft
{
    public function __construct(
        private SourceRepository $sources,
        private SourceAssetRepository $assets,
        private MentionRepository $mentions,
        private ClaimRepository $claims,
        private SourceLocatorRepository $locators,
        private SourceRevisionRepository $sourceRevisions,
        private MentionRevisionRepository $mentionRevisions,
        private ClaimRevisionRepository $claimRevisions,
        private SourceIdentifierGenerator $identifiers,
    ) {}

    public function handle(SourceId $sourceId): SourceDraft
    {
        $source = $this->sources->find($sourceId) ?? throw SourceNotFound::forId($sourceId);
        $mentions = $this->mentions->forSource($sourceId);
        $claims = $this->claims->forSource($sourceId);
        /** @var list<SourceLocator> $locators */
        $locators = [];

        foreach ($claims as $claim) {
            array_push($locators, ...$this->locators->forClaim($sourceId, $claim->id));
        }

        return new SourceDraft(
            current: new SourceDraftState(
                source: $source,
                assets: $this->assets->forSource($sourceId),
                mentions: $mentions,
                claims: $claims,
                locators: $locators,
            ),
            baseState: $this->captureBaseState($sourceId, $mentions, $claims),
            changes: SourceDraftChanges::none(),
            isNew: false,
        );
    }

    /** @param  list<SourceTextInput>  $texts */
    public function blank(
        SourceType $type,
        ?SourceMetadata $metadata = null,
        array $texts = [],
    ): SourceDraft {
        $sourceTexts = [];
        foreach ($texts as $input) {
            $sourceTexts[] = new SourceText(
                id: $input->id ?? $this->identifiers->sourceTextId(),
                kind: $input->kind,
                content: $input->content,
                language: $input->language,
            );
        }

        return new SourceDraft(
            current: new SourceDraftState(
                source: new Source(
                    id: $this->identifiers->sourceId(),
                    type: $type,
                    metadata: $metadata ?? SourceMetadata::empty(),
                    texts: $sourceTexts,
                ),
            ),
            baseState: null,
            changes: SourceDraftChanges::none(),
            isNew: true,
        );
    }

    /**
     * @param  list<Mention>  $mentions
     * @param  list<Claim>  $claims
     */
    private function captureBaseState(SourceId $sourceId, array $mentions, array $claims): SourceDraftBaseState
    {
        $sourceRevision = $this->sourceRevisions->latestForSource($sourceId)
            ?? throw EvidenceStateCaptureIncomplete::missingSourceRevision($sourceId);

        $mentionRevisionIds = array_map(
            function (Mention $mention) use ($sourceId): MentionRevisionId {
                return ($this->mentionRevisions->latestForMention($sourceId, $mention->id)
                    ?? throw EvidenceStateCaptureIncomplete::missingMentionRevision($mention->id))->id;
            },
            $mentions,
        );

        $claimRevisionIds = array_map(
            function (Claim $claim) use ($sourceId): ClaimRevisionId {
                return ($this->claimRevisions->latestForClaim($sourceId, $claim->id)
                    ?? throw EvidenceStateCaptureIncomplete::missingClaimRevision($claim->id))->id;
            },
            $claims,
        );

        return SourceDraftBaseState::capture(
            sourceRevisionId: $sourceRevision->id,
            mentionRevisionIds: $mentionRevisionIds,
            claimRevisionIds: $claimRevisionIds,
        );
    }
}
