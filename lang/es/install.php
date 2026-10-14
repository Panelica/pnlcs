<?php

// The install wizard.
return [
    'migrate_title' => 'Creando las tablas de la base de datos',
    'migrate_intro' => 'PNLCS está preparando su base de datos. Suele tardar uno o dos minutos. Mantenga esta página abierta; continúa por sí sola.',
    'migrate_progress' => ':done de :total pasos completados',
    'migrate_failed' => 'La preparación de la base de datos se detuvo: :message',
    'migrate_retry' => 'Inténtelo de nuevo',
    'db_connect_failed' => 'No se pudo conectar a la base de datos con estos datos: :message',
    'cron_title' => 'Un paso más: el programador',
    'cron_intro' => 'PNLCS envía los correos, renueva los servicios y realiza su trabajo en segundo plano desde un programador que debe iniciarse cada minuto. Añada esta línea como tarea cron del usuario con el que se ejecuta el sitio:',
    'cron_php' => 'Si el comando php de su servidor inicia otra versión de PHP, escriba en su lugar la ruta de PHP 8.4.',
    'cron_note' => '¿Instalado con Docker o con install.sh? Entonces esto ya está hecho.',
];
