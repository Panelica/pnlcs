<?php

/*
 * The updater (Setup -> Updates, `php artisan pnlcs:update`). How releases are
 * made and what an update may and may not touch: RELEASING.md.
 */

return [

    // Where releases are listed: GitHub Releases of Panelica/pnlcs. A draft is
    // not offered; withdrawing a release is turning it back into a draft.
    'index_url' => env('PNLCS_UPDATE_INDEX_URL', 'https://api.github.com/repos/Panelica/pnlcs/releases?per_page=30'),

    // The public half of the PNLCS release key. Every release statement is
    // signed with the private half, which never leaves the maintainers; a
    // package whose statement does not verify against this key is refused.
    // PNLCS_UPDATE_PUBLIC_KEY (base64 of a PEM) replaces it for the update lab.
    'public_key' => env('PNLCS_UPDATE_PUBLIC_KEY') ? base64_decode((string) env('PNLCS_UPDATE_PUBLIC_KEY')) : <<<'PEM'
-----BEGIN PUBLIC KEY-----
MFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAEUrkakoxq0WUZp7MMCIJ+R93AtcMD
6Ft8c0cym8M6UkVSWGHZ3Rx+WOPZXrAqHiIbiuBTpcC5f+Yur4M74bY2rA==
-----END PUBLIC KEY-----
PEM,

    // The updater's own state: downloaded packages, runs and their backups,
    // history. Inside storage/, which no update ever writes to.
    'path' => storage_path('app/pnlcs-update'),

    // Finished runs keep their file backups and database snapshot this long.
    // No command rolls back a finished update; the run directory holds what is
    // needed to do it by hand (`php <run>/apply.php rollback <run>/plan.json`).
    'keep_runs_days' => 14,
];
