<?php

declare(strict_types=1);

namespace App\Application\Search;

use App\Domain\Acquisition\EvidenceStateId;
use App\Domain\Acquisition\SourceId;
use App\Domain\Acquisition\SourceRevisionId;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class SearchDocument
{
    /** @var list<SearchDocumentEntry> */
    public array $entries;

    /** @param list<SearchDocumentEntry> $entries */
    public function __construct(
        public SourceId $sourceId,
        public EvidenceStateId $evidenceStateId,
        public SourceRevisionId $sourceRevisionId,
        public ?string $sourceName,
        public string $sourceTypeKey,
        public string $inputFingerprint,
        public string $indexSignature,
        array $entries,
        public bool $stale,
        public DateTimeImmutable $builtAt,
    ) {
        if ($sourceTypeKey === '' || $inputFingerprint === '' || $indexSignature === '') {
            throw new InvalidArgumentException('SearchDocument identity metadata must not be empty.');
        }

        $this->entries = $entries;
    }
}
