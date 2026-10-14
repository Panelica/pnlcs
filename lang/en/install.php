<?php

// The install wizard.
return [
    'migrate_title' => 'Creating the database tables',
    'migrate_intro' => 'PNLCS is setting up its database. This usually takes a minute or two. Keep this page open; it moves on by itself.',
    'migrate_progress' => ':done of :total steps done',
    'migrate_failed' => 'Setting up the database stopped: :message',
    'migrate_retry' => 'Try again',
    'db_connect_failed' => 'Could not connect to the database with these settings: :message',
    'cron_title' => 'One more step: the scheduler',
    'cron_intro' => 'PNLCS sends emails, renews services and runs its background work from a scheduler that has to start every minute. Add this line as a cron job for the user the site runs as:',
    'cron_php' => 'If the php command on your server starts another PHP version, write the path of PHP 8.4 instead.',
    'cron_note' => 'Installed with Docker or install.sh? Then this is already done.',
];
