<?php

declare(strict_types=1);

return [
    'picker' => [
        'open' => 'Dodaj obsługiwane pole strukturalne',
        'title' => 'Wybierz obsługiwane pole',
        'description' => 'Pola pochodzą z kontrolowanego katalogu akwizycji. Wybór dodaje jedno wystąpienie; pola powtarzalne pozostają dostępne do ponownego dodania.',
        'search_label' => 'Szukaj obsługiwanych pól',
        'search_placeholder' => 'Szukaj po nazwie lub kanonicznym kluczu…',
        'add' => 'Dodaj',
        'add_field' => 'Dodaj: :label',
        'repeatable' => 'Powtarzalne',
        'event_context' => 'Grupa zdarzenia',
        'canonical_key' => 'Klucz kanoniczny: :key',
        'empty' => 'Brak obsługiwanych pól pasujących do wyszukiwania.',
    ],
    'groups' => [
        'person_facts' => 'Fakty o osobie',
        'event_contexts' => 'Konteksty zdarzeń',
        'event_facts' => 'Fakty o zdarzeniu',
        'place_facts' => 'Fakty o miejscu',
        'source_facts' => 'Fakty o źródle',
    ],
    'fields' => [
        'person' => [
            'given_name' => ['label' => 'Imię'],
            'surname' => ['label' => 'Nazwisko'],
            'age' => ['label' => 'Wiek'],
            'birth_date' => ['label' => 'Data urodzenia'],
            'death_date' => ['label' => 'Data śmierci'],
            'parent' => ['label' => 'Rodzic'],
            'spouse' => ['label' => 'Małżonek'],
            'birth_place' => ['label' => 'Miejsce urodzenia'],
            'residence' => ['label' => 'Miejsce zamieszkania'],
            'permanent_residence' => ['label' => 'Stałe miejsce zamieszkania'],
            'temporary_stay' => ['label' => 'Miejsce czasowego pobytu'],
            'presence' => ['label' => 'Miejsce obecności'],
            'address' => ['label' => 'Adres'],
            'origin' => ['label' => 'Pochodzenie'],
            'work_place' => ['label' => 'Miejsce pracy'],
            'study_place' => ['label' => 'Miejsce nauki'],
            'detention_place' => ['label' => 'Miejsce zatrzymania'],
            'exile_place' => ['label' => 'Miejsce zesłania'],
            'deportation_destination' => ['label' => 'Miejsce deportacji'],
            'occupation' => [
                'label' => 'Zawód',
                'help' => 'Praca lub zawód faktycznie wykonywany; nie używaj dla stanu społecznego, urzędu, rangi, tytułu ani stopnia naukowego.',
            ],
            'social_status' => [
                'label' => 'Status społeczny',
                'help' => 'Pozycja społeczna zapisana w źródle, której nie da się właściwie opisać jako formalnego stanu społeczno-prawnego.',
            ],
            'social_estate' => [
                'label' => 'Stan społeczno-prawny',
                'help' => 'Jawnie wskazany formalny lub historycznie swoisty stan społeczny albo prawny.',
            ],
            'office' => [
                'label' => 'Urząd lub funkcja',
                'help' => 'Instytucjonalny urząd lub funkcja pełniona przez osobę.',
            ],
            'rank' => [
                'label' => 'Ranga lub stopień',
                'help' => 'Formalny stopień wojskowy, służbowy albo porównywalna ranga.',
            ],
            'title' => [
                'label' => 'Tytuł',
                'help' => 'Formalny, honorowy lub szlachecki tytuł; nie zawód, urząd, ranga ani stopień naukowy.',
            ],
            'academic_degree' => [
                'label' => 'Stopień naukowy',
                'help' => 'Stopień naukowy jawnie przypisany osobie w źródle.',
            ],
        ],
        'event' => [
            'context' => [
                'label' => 'Kontekst zdarzenia',
                'help' => 'Grupuje jedną wzmiankę o zdarzeniu z atomowymi twierdzeniami o dacie, miejscu, rolach uczestników i przyczynie.',
            ],
            'date' => ['label' => 'Data zdarzenia'],
            'place' => ['label' => 'Miejsce zdarzenia'],
            'participant' => ['label' => 'Uczestnik'],
            'child' => ['label' => 'Dziecko'],
            'parent' => ['label' => 'Rodzic uczestnika'],
            'spouse' => ['label' => 'Małżonek / strona'],
            'witness' => ['label' => 'Świadek'],
            'declarant' => ['label' => 'Zgłaszający'],
            'officiant' => ['label' => 'Osoba urzędowa'],
            'origin_place' => ['label' => 'Miejsce pochodzenia'],
            'destination_place' => ['label' => 'Miejsce docelowe'],
            'reason' => ['label' => 'Przyczyna zdarzenia'],
        ],
        'place' => [
            'name' => ['label' => 'Nazwa miejsca'],
        ],
    ],
];
