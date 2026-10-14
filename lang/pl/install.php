<?php

// The install wizard.
return [
    'migrate_title' => 'Tworzenie tabel bazy danych',
    'migrate_intro' => 'PNLCS przygotowuje bazę danych. Zwykle trwa to minutę lub dwie. Nie zamykaj tej strony; przejdzie dalej sama.',
    'migrate_progress' => 'Ukończono :done z :total kroków',
    'migrate_failed' => 'Konfiguracja bazy danych została przerwana: :message',
    'migrate_retry' => 'Spróbuj ponownie',
    'db_connect_failed' => 'Nie udało się połączyć z bazą danych przy tych ustawieniach: :message',
    'cron_title' => 'Ostatni krok: harmonogram',
    'cron_intro' => 'PNLCS wysyła e-maile, odnawia usługi i wykonuje pracę w tle za pomocą harmonogramu, który musi startować co minutę. Dodaj tę linię jako zadanie cron dla użytkownika, na którym działa strona:',
    'cron_php' => 'Jeśli polecenie php na Twoim serwerze uruchamia inną wersję PHP, wpisz zamiast niego ścieżkę do PHP 8.4.',
    'cron_note' => 'Instalacja przez Dockera lub install.sh? W takim razie jest to już zrobione.',
];
