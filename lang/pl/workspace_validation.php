<?php

declare(strict_types=1);

return [
    'technical_details' => 'Szczegóły techniczne',
    'mention_json_syntax' => 'Błąd składni JSON w Mention nr :number:key_suffix.',
    'mention_json_object' => 'Dane JSON w Mention nr :number:key_suffix muszą być obiektem JSON.',
    'mention_required' => 'Mention nr :number:key_suffix wymaga typu i local key.',
    'mention_invalid' => 'Mention nr :number:key_suffix zawiera nieprawidłowe dane.',
    'mention_duplicate_local_key' => 'Local key w Mention nr :number:key_suffix musi być unikalny w obrębie źródła.',
    'claim_subject_required' => 'Claim nr :number wymaga wskazania Mention jako podmiotu.',
    'claim_subject_missing' => 'Claim nr :number odwołuje się do nieistniejącego Mention jako podmiotu.',
    'claim_object_required' => 'Claim nr :number wymaga wskazania Mention jako obiektu.',
    'claim_object_missing' => 'Claim nr :number odwołuje się do nieistniejącego Mention jako obiektu.',
    'claim_value_required' => 'Claim nr :number wymaga wartości źródłowej.',
    'claim_effective_time_incomplete' => 'Claim nr :number ma niekompletne określenie czasu obowiązywania.',
    'claim_invalid' => 'Claim nr :number zawiera nieprawidłowe dane.',
    'event_json_syntax' => 'Błąd składni JSON w Event nr :number:key_suffix.',
    'event_json_object' => 'Dane JSON w Event nr :number:key_suffix muszą być obiektem JSON.',
    'event_invalid' => 'Event nr :number:key_suffix zawiera nieprawidłowe dane.',
    'event_claim_invalid' => 'Claim nr :claim_number w Event nr :event_number zawiera nieprawidłowe dane.',
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
