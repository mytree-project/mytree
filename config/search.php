<?php

declare(strict_types=1);

return [
    'max_page_size' => 100,
    'candidate_limit' => 1000,
    'query_expansion_limit' => 32,
    'fuzzy' => [
        'enabled' => false,
        'threshold' => 0.78,
        'candidate_limit' => 200,
    ],
    'ranking' => [
        'direct_source_metadata' => 100,
        'direct_claim_value' => 100,
        'source_representation' => 90,
        'same_mention_bonus' => 5,
    ],
];
