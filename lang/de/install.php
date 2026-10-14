<?php

// The install wizard.
return [
    'migrate_title' => 'Die Datenbanktabellen werden angelegt',
    'migrate_intro' => 'PNLCS richtet die Datenbank ein. Das dauert meist ein bis zwei Minuten. Lassen Sie diese Seite geöffnet; sie geht von selbst weiter.',
    'migrate_progress' => ':done von :total Schritten erledigt',
    'migrate_failed' => 'Die Einrichtung der Datenbank wurde angehalten: :message',
    'migrate_retry' => 'Erneut versuchen',
    'db_connect_failed' => 'Mit diesen Angaben konnte keine Verbindung zur Datenbank hergestellt werden: :message',
    'cron_title' => 'Ein letzter Schritt: der Zeitplaner',
    'cron_intro' => 'PNLCS versendet E-Mails, verlängert Dienste und erledigt Hintergrundarbeiten über einen Zeitplaner, der jede Minute starten muss. Fügen Sie diese Zeile als Cronjob für den Benutzer hinzu, unter dem die Website läuft:',
    'cron_php' => 'Wenn der Befehl php auf Ihrem Server eine andere PHP-Version startet, geben Sie stattdessen den Pfad zu PHP 8.4 an.',
    'cron_note' => 'Mit Docker oder install.sh installiert? Dann ist das bereits erledigt.',
];
