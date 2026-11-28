<?php

return [
    'mode' => 'Tryb pracy',
    'mode_test' => 'Testowy — bez księgowania',
    'mode_semi_auto' => 'Półautomatyczny — księgowanie po akceptacji',
    'mode_auto' => 'Automatyczny — tylko jednoznaczne dopasowania',
    'mode_hint' => 'W trybie automatycznym księgowane są wyłącznie jednoznaczne dopasowania: numer, pełna kwota i kontrahent. Częściowe wpłaty, nadpłaty i wiele faktur wymagają akceptacji.',
    'notification_email' => 'E-mail powiadomień o akceptacji',
    'notification_email_hint' => 'Adres, na który trafi informacja o dopasowaniach wymagających akceptacji. Puste = SystemEmailAddress.',
    'notify_on_review' => 'Wysyłaj powiadomienia o dopasowaniach do akceptacji',
    'notify_on_review_hint' => 'Jedno zbiorcze powiadomienie po synchronizacji, gdy pojawią się nowe dopasowania do ręcznej akceptacji.',
    'initial_sync_days' => 'Zakres pierwszej synchronizacji (dni)',
    'initial_sync_days_hint' => 'Pierwsze pobranie jest ograniczone do podanego zakresu. Domyślnie 7 dni.',
    'sync_limit' => 'Limit wiadomości na synchronizację',
    'sync_limit_hint' => 'Maksymalna liczba wiadomości pobieranych w jednym przebiegu (1–500).',
    'mark_seen' => 'Oznaczaj przetworzone wiadomości jako przeczytane',
    'mark_seen_hint' => 'Wiadomości nie są usuwane ze skrzynki.',
    'retention_days' => 'Retencja danych (dni)',
    'retention_days_hint' => 'Wartość przechowywana na potrzeby późniejszego czyszczenia danych.',
];
