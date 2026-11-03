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

    /** @var list<SearchDocumentDate> */
    public array $dates;

    /**
     * @param  list<SearchDocumentEntry>  $entries
     * @param  list<SearchDocumentDate>  $dates
     */
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
        public ?int $sourceDateOrdinal = null,
        array $dates = [],
    ) {
        if ($sourceTypeKey === '' || $inputFingerprint === '' || $indexSignature === '') {
            throw new InvalidArgumentException('SearchDocument identity metadata must not be empty.');
        }

        $this->entries = $entries;
        $this->dates = $dates;
    }
}
