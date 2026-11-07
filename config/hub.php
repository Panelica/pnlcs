<?php

/*
 * hub.pnlcs.com: the once-a-day ping and the signed notices
 * (App\Services\Hub). The address is fixed here and nothing a request or a
 * setting carries can change it.
 */
return [
    'ping_url' => 'https://hub.pnlcs.com/v1/ping',

    // The page that lists every field the ping sends; linked from the install
    // wizard and from the one-time box on existing installs.
    // Set to "script" in .env by install.sh, so the ping can tell the
    // one-command install from a package unpacked by hand.
    'installed_by' => env('PNLCS_INSTALLED_BY'),

    'what_is_sent_url' => 'https://docs.pnlcs.com/hub/what-is-sent/',

    /*
     * The public half of the PNLCS notice key (ECDSA P-256, PEM). A separate
     * key from the release key in config/updates.php. Notices are signed on
     * the PNLCS team's own machine; the hub only passes them on, so a hub that
     * is taken over cannot put a notice of its own on an admin panel.
     *
     * Empty: no notice is shown at all, whatever the hub sends.
     */
    'notice_public_key' => null,
];
