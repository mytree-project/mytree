<?php

declare(strict_types=1);

namespace App\Application\Search;

use App\Domain\Acquisition\SourceId;

final readonly class SearchSourceResult implements SearchResultDetails
{
    /** @var list<SearchMatchReason> */
    public array $matchReasons;

    /** @param list<SearchMatchReason> $matchReasons */
    public function __construct(
        public SourceId $sourceId,
        public ?string $sourceName,
        public string $sourceTypeKey,
        public int $score,
        public bool $stale,
        array $matchReasons,
        public ?int $sourceDateOrdinal = null,
        public string $sourceCreatedAt = '',
    ) {
        $this->matchReasons = $matchReasons;
    }

    public function category(): SearchResultCategory
    {
        return SearchResultCategory::Source;
    }

    public function resultId(): string
    {
        return $this->sourceId->value;
    }

    public function label(): ?string
    {
        return $this->sourceName;
    }

    public function ranking(): SearchResultScore
    {
        return new SearchResultScore('source_retrieval_v1', $this->score);
    }
}
