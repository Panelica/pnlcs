<?php

namespace App\Providers;

use App\Support\MailTransport;
use Illuminate\Support\ServiceProvider;

class MailConfigProvider extends ServiceProvider
{
    public function boot(): void
    {
        // One resolver, shared with the test-email button: the two used to
        // disagree about PHP mail, so a successful test proved nothing about
        // what carried the real thing.
        MailTransport::configure();
    }
}
