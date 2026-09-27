<?php

declare(strict_types=1);

namespace App\Application\Search;

use App\Application\Acquisition\GetEvidenceState;
use App\Domain\Acquisition\SourceId;
use DateTimeImmutable;
use RuntimeException;

final readonly class RebuildSourceSearchDocument
{
    public function __construct(
        private CurrentEvidenceStateLookup $currentEvidenceState,
        private GetEvidenceState $getEvidenceState,
        private BuildSearchDocument $builder,
        private SearchDocumentRepository $documents,
    ) {}

    public function handle(SourceId $sourceId): SearchDocument
    {
        $evidenceStateId = $this->currentEvidenceState->forSource($sourceId)
            ?? throw new RuntimeException(sprintf(
                'Cannot build SearchDocument for Source "%s" without an accepted EvidenceState.',
                $sourceId->value,
            ));

        $document = $this->builder->build(
            $this->getEvidenceState->get($evidenceStateId),
            new DateTimeImmutable,
        );
        $this->documents->replace($document);

        return $document;
    }
}
