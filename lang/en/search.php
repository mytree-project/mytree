<?php

declare(strict_types=1);

return [
    'navigation_label' => 'Search',
    'title' => 'Search',
    'query_label' => 'Search sources',
    'query_placeholder' => 'e.g. Peter Wiśniewski',
    'query_hint' => 'Plain terms use AND semantics within the same Source. Different terms may match different Mentions.',
    'start_prompt' => 'Enter one or more search terms to search the current Source projection.',
    'candidate_window_truncated' => 'The candidate window was truncated. Refine the query to reduce the result set.',
    'result_count' => '{0} No results|{1} :count result|[2,*] :count results',
    'result_count_unknown' => 'Result count unavailable',
    'page' => 'Page :page',
    'untitled_source' => 'Untitled Source',
    'stale' => 'Stale projection',
    'source_type' => 'Type: :type',
    'score' => 'Score: :score',
    'match_reasons' => 'Why this matched',
    'query_term' => 'Query term: :term',
    'mention_id' => 'Mention: :id',
    'claim_id' => 'Claim: :id',
    'representation_relation' => 'Representation: :relation',
    'no_results' => 'No matching Sources were found.',
    'previous' => 'Previous',
    'next' => 'Next',
    'match_types' => [
        'direct_source_metadata' => 'Direct Source metadata',
        'direct_claim_value' => 'Direct Claim value',
        'source_representation' => 'Source representation',
    ],
    'fields' => [
        'source_name' => 'Source name',
        'source_title' => 'Source title',
        'person_given_name' => 'Given name',
        'person_surname' => 'Surname',
        'place_name' => 'Place name',
    ],
];
