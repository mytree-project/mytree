<?php

declare(strict_types=1);

return [
    'technical_details' => 'Szczegóły techniczne',
    'mention_json_syntax' => 'Błąd składni JSON w Wzmianka nr :number:key_suffix.',
    'mention_json_object' => 'Dane JSON w Wzmianka nr :number:key_suffix muszą być obiektem JSON.',
    'mention_required' => 'Wzmianka nr :number:key_suffix wymaga typu i local key.',
    'mention_invalid' => 'Wzmianka nr :number:key_suffix zawiera nieprawidłowe dane.',
    'mention_duplicate_local_key' => 'Local key w Wzmianka nr :number:key_suffix musi być unikalny w obrębie źródła.',
    'claim_subject_required' => 'Twierdzenie nr :number wymaga wskazania Wzmianki jako podmiotu.',
    'claim_subject_missing' => 'Twierdzenie nr :number odwołuje się do nieistniejącej Wzmianki jako podmiotu.',
    'claim_object_required' => 'Twierdzenie nr :number wymaga wskazania Wzmianki jako obiektu.',
    'claim_object_missing' => 'Twierdzenie nr :number odwołuje się do nieistniejącej Wzmianki jako obiektu.',
    'claim_value_required' => 'Twierdzenie nr :number wymaga wartości źródłowej.',
    'claim_effective_time_incomplete' => 'Twierdzenie nr :number ma niekompletne określenie czasu obowiązywania.',
    'claim_invalid' => 'Twierdzenie nr :number zawiera nieprawidłowe dane.',
    'event_json_syntax' => 'Błąd składni JSON w Zdarzenie nr :number:key_suffix.',
    'event_json_object' => 'Dane JSON w Zdarzenie nr :number:key_suffix muszą być obiektem JSON.',
    'event_invalid' => 'Zdarzenie nr :number:key_suffix zawiera nieprawidłowe dane.',
    'event_claim_invalid' => 'Twierdzenie nr :claim_number w Zdarzenie nr :event_number zawiera nieprawidłowe dane.',
    'metadata_invalid' => 'Pole metadanych nr :number zawiera nieprawidłowe dane.',
    'metadata_integer_invalid' => 'Pole metadanych nr :number wymaga prawidłowej liczby całkowitej.',
    'metadata_number_invalid' => 'Pole metadanych nr :number wymaga prawidłowej liczby.',
    'metadata_boolean_invalid' => 'Pole metadanych nr :number wymaga wartości true albo false.',
    'metadata_duplicate' => 'Klucz pola metadanych nr :number musi być unikalny.',
    'source_text_invalid' => 'Reprezentacja tekstowa źródła nr :number zawiera nieprawidłowe dane.',
    'upload_invalid' => 'Nie można poprawnie odczytać lub zweryfikować dołączonego pliku.',
    'source_details_invalid' => 'Szczegóły źródła zawierają nieprawidłowe lub niekompletne dane.',
    'workspace_data_invalid' => 'Dane workspace zawierają błąd i nie mogą zostać zapisane.',
];
