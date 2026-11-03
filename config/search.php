<?php

declare(strict_types=1);

return [
    'max_page_size' => 100,
    'candidate_limit' => 1000,
    'query_expansion_limit' => 32,
    'variants_profile' => 'genealogy-pl-ru',
    'name_processing' => [
        'normalize_profile' => 'default',
        'transliterate_profile' => 'cyrillic-latin',
        'fold_profile' => 'latin-search',
    ],
    'fuzzy' => [
        'enabled' => (bool) env('SEARCH_FUZZY_ENABLED', false),
        'threshold' => (float) env('SEARCH_FUZZY_THRESHOLD', 0.78),
        'candidate_limit' => (int) env('SEARCH_FUZZY_CANDIDATE_LIMIT', 200),
    ],
    'ranking' => [
        'direct_source_metadata' => 100,
        'direct_claim_value' => 100,
        'source_representation' => 90,
        'technical_comparison' => 80,
        'dictionary_variant' => 65,
        'fuzzy_comparison' => 50,
        'same_mention_bonus' => 5,
    ],
];
