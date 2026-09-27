<?php

declare(strict_types=1);

namespace App\Application\Search;

final readonly class DirectSearchCandidateBatch
{
    /** @var list<DirectSearchCandidate> */
    public array $candidates;

    /** @param list<DirectSearchCandidate> $candidates */
    public function __construct(
        array $candidates,
        public bool $truncated,
    ) {
        $this->candidates = $candidates;
    }
}
