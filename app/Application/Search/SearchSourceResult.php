<?php

declare(strict_types=1);

namespace App\Application\Search;

use App\Domain\Acquisition\SourceId;

final readonly class SearchSourceResult
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
    ) {
        $this->matchReasons = $matchReasons;
    }

    public function category(): SearchResultCategory
    {
        return SearchResultCategory::Source;
    }
}
