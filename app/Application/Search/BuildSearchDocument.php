<?php

declare(strict_types=1);

namespace App\Application\Search;

use App\Application\Acquisition\ResolvedEvidenceState;
use App\Domain\Acquisition\ClaimRevision;
use App\Domain\Acquisition\SourceLinguisticRepresentation;
use App\Domain\Acquisition\TextClaimValue;
use DateTimeImmutable;
use LogicException;

final readonly class BuildSearchDocument
{
    public function __construct(
        private SearchIndexProfile $profile,
    ) {}

    public function build(ResolvedEvidenceState $evidence, DateTimeImmutable $builtAt): SearchDocument
    {
        if (count($evidence->sourceRevisions) !== 1) {
            throw new LogicException('M5 SearchDocument rebuild expects exactly one SourceRevision per Source scope.');
        }

        $sourceRevision = $evidence->sourceRevisions[0];
        $sourceRevisionState = $sourceRevision->reconstruct();
        $source = $sourceRevisionState->source;
        $entries = [];

        if ($source->name !== null) {
            $entries[] = new SearchDocumentEntry(
                field: 'source.name',
                value: $source->name,
                origin: SearchValueOrigin::SourceMetadata,
            );
        }

        foreach ($evidence->claimRevisions as $revision) {
            $this->appendClaimEntries($entries, $revision);
        }

        return new SearchDocument(
            sourceId: $source->id,
            evidenceStateId: $evidence->evidenceState->id,
            sourceRevisionId: $sourceRevision->id,
            sourceName: $source->name,
            sourceTypeKey: $source->type->key,
            inputFingerprint: $evidence->evidenceState->snapshot->payloadHash,
            indexSignature: $this->profile->signature(),
            entries: $entries,
            stale: false,
            builtAt: $builtAt,
        );
    }

    /** @param list<SearchDocumentEntry> $entries */
    private function appendClaimEntries(array &$entries, ClaimRevision $revision): void
    {
        $state = $revision->reconstruct();
        $claim = $state->claim;

        if (! $this->profile->contains($claim->predicate->key)) {
            return;
        }

        if (! $claim->value instanceof TextClaimValue) {
            throw new LogicException('M5 searchable lexical predicates must use Text Claim values.');
        }

        $entries[] = new SearchDocumentEntry(
            field: $claim->predicate->key->value,
            value: $claim->value->value,
            origin: SearchValueOrigin::ClaimValue,
            mentionId: $claim->subjectMentionId->value,
            claimId: $claim->id->value,
            claimRevisionId: $revision->id->value,
        );

        foreach ($claim->sourceLinguisticRepresentations as $representation) {
            $entries[] = $this->sourceRepresentationEntry($revision, $representation);
        }
    }

    private function sourceRepresentationEntry(
        ClaimRevision $revision,
        SourceLinguisticRepresentation $representation,
    ): SearchDocumentEntry {
        $claim = $revision->reconstruct()->claim;

        return new SearchDocumentEntry(
            field: $claim->predicate->key->value,
            value: $representation->value,
            origin: SearchValueOrigin::SourceRepresentation,
            mentionId: $claim->subjectMentionId->value,
            claimId: $claim->id->value,
            claimRevisionId: $revision->id->value,
            language: $representation->language,
            script: $representation->script,
            representationRelation: $representation->relation->value,
        );
    }
}
