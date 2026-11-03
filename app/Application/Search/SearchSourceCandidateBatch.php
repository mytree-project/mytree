<?php

declare(strict_types=1);

namespace App\Application\Search;

final readonly class SearchSourceCandidateBatch
{
    /** @var list<SearchSourceCandidate> */
    public array $sources;

    /** @param list<SearchSourceCandidate> $sources */
    public function __construct(
        array $sources,
        public bool $truncated,
    ) {
        $this->sources = $sources;
    }
}
