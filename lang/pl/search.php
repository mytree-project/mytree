<?php

declare(strict_types=1);

return [
    'navigation_label' => 'Wyszukiwanie',
    'title' => 'Wyszukiwanie',
    'query_label' => 'Szukaj źródeł',
    'query_placeholder' => 'np. Peter Wiśniewski',
    'query_hint' => 'Zwykłe termy używają semantyki AND w obrębie tego samego źródła. Różne termy mogą pasować do różnych wzmianek.',
    'start_prompt' => 'Wpisz jeden lub więcej terminów, aby przeszukać bieżącą projekcję źródeł.',
    'candidate_window_truncated' => 'Okno kandydatów zostało ograniczone. Doprecyzuj zapytanie, aby zmniejszyć zbiór wyników.',
    'result_count' => '{0} Brak wyników|{1} :count wynik|[2,4] :count wyniki|[5,*] :count wyników',
    'result_count_unknown' => 'Liczba wyników jest niedostępna',
    'page' => 'Strona :page',
    'untitled_source' => 'Źródło bez nazwy',
    'stale' => 'Nieaktualna projekcja',
    'source_type' => 'Typ: :type',
    'score' => 'Wynik: :score',
    'match_reasons' => 'Dlaczego ten wynik pasuje',
    'query_term' => 'Termin zapytania: :term',
    'mention_id' => 'Wzmianka: :id',
    'claim_id' => 'Twierdzenie: :id',
    'representation_relation' => 'Reprezentacja: :relation',
    'no_results' => 'Nie znaleziono pasujących źródeł.',
    'previous' => 'Poprzednia',
    'next' => 'Następna',
    'match_types' => [
        'direct_source_metadata' => 'Bezpośrednie metadane źródła',
        'direct_claim_value' => 'Bezpośrednia wartość twierdzenia',
        'source_representation' => 'Reprezentacja źródłowa',
    ],
    'fields' => [
        'source_name' => 'Nazwa źródła',
        'source_title' => 'Tytuł źródła',
        'person_given_name' => 'Imię',
        'person_surname' => 'Nazwisko',
        'place_name' => 'Nazwa miejsca',
    ],
];
