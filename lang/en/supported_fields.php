<?php

declare(strict_types=1);

return [
    'picker' => [
        'open' => 'Add supported structured field',
        'field_label' => 'Supported field',
        'title' => 'Choose a supported field',
        'choose' => 'Choose from the palette',
        'description' => 'Fields come from the controlled acquisition catalog. Choose one occurrence to add; repeatable fields remain available for additional occurrences.',
        'search_label' => 'Search supported fields',
        'search_placeholder' => 'Search by label or canonical key…',
        'add' => 'Add',
        'add_field' => 'Add :label',
        'repeatable' => 'Repeatable',
        'event_context' => 'Event group',
        'canonical_key' => 'Canonical key: :key',
        'empty' => 'No supported fields match this search.',
    ],
    'groups' => [
        'person_general' => 'Person · Basic information',
        'person_relationships' => 'Person · Relationships',
        'person_places' => 'Person · Places',
        'person_status' => 'Person · Occupation, status & titles',
        'event_contexts' => 'Event contexts',
        'event_general' => 'Event · General facts',
        'event_roles' => 'Event · Participants & roles',
        'place_general' => 'Place',
        'source_general' => 'Source',
    ],
    'fields' => [
        'person' => [
            'given_name' => ['label' => 'Given name'],
            'surname' => ['label' => 'Surname'],
            'age' => ['label' => 'Age'],
            'birth_date' => ['label' => 'Birth date'],
            'death_date' => ['label' => 'Death date'],
            'parent' => ['label' => 'Parent'],
            'spouse' => ['label' => 'Spouse'],
            'birth_place' => ['label' => 'Birth place'],
            'residence' => ['label' => 'Residence'],
            'permanent_residence' => ['label' => 'Permanent residence'],
            'temporary_stay' => ['label' => 'Temporary stay'],
            'presence' => ['label' => 'Presence'],
            'address' => ['label' => 'Address'],
            'origin' => ['label' => 'Origin'],
            'work_place' => ['label' => 'Work place'],
            'study_place' => ['label' => 'Study place'],
            'detention_place' => ['label' => 'Detention place'],
            'exile_place' => ['label' => 'Exile place'],
            'deportation_destination' => ['label' => 'Deportation destination'],
            'occupation' => [
                'label' => 'Occupation',
                'help' => 'Work or profession actually performed; do not use for estate, office, rank, title or degree.',
            ],
            'social_status' => [
                'label' => 'Social status',
                'help' => 'Source-recorded social position that is not adequately represented as a formal estate.',
            ],
            'social_estate' => [
                'label' => 'Social estate',
                'help' => 'Explicit formal or historically specific social/legal estate.',
            ],
            'office' => [
                'label' => 'Office',
                'help' => 'Institutional office or function held by the person.',
            ],
            'rank' => [
                'label' => 'Rank',
                'help' => 'Formal military, civil-service or comparable rank.',
            ],
            'title' => [
                'label' => 'Title',
                'help' => 'Formal, honorific or noble title; not an occupation, office, rank or degree.',
            ],
            'academic_degree' => [
                'label' => 'Academic degree',
                'help' => 'Academic degree explicitly attributed by the Source.',
            ],
        ],
        'event' => [
            'context' => [
                'label' => 'Event context',
                'help' => 'Groups one source-local event Mention with atomic date, place, participant-role and reason Claims.',
            ],
            'date' => ['label' => 'Event date'],
            'place' => ['label' => 'Event place'],
            'participant' => ['label' => 'Participant'],
            'child' => ['label' => 'Child'],
            'parent' => ['label' => 'Parent participant'],
            'spouse' => ['label' => 'Spouse / party'],
            'witness' => ['label' => 'Witness'],
            'declarant' => ['label' => 'Declarant'],
            'officiant' => ['label' => 'Officiant'],
            'origin_place' => ['label' => 'Origin place'],
            'destination_place' => ['label' => 'Destination place'],
            'reason' => ['label' => 'Event reason'],
        ],
        'place' => [
            'name' => ['label' => 'Place name'],
        ],
    ],
];
