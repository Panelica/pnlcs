<?php

namespace App\Listeners;

use App\Models\Setting;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Log;

/**
 * Honours the "Mail Enabled" switch on the general settings page.
 *
 * The setting was written to the database but nothing ever read it, so an
 * operator who turned mail off — during a migration, while testing, or to stop
 * a runaway notification — kept mailing customers anyway.
 *
 * Returning false from a MessageSending listener cancels the send, which covers
 * both direct and queued mail because every mailable passes through here.
 * Mail is treated as enabled unless the setting explicitly says otherwise, so a
 * fresh install and any install that never touched the switch behave as before.
 */
class SuppressMailWhenDisabled
{
    /**
     * Returns null to allow, false to cancel.
     *
     * MessageSending is dispatched with halting: the chain stops at the first
     * listener that returns anything other than null. Returning true here to
     * mean "allowed" silently cancelled every listener registered after this
     * one, so any other mail hook the panel adds would never run.
     */
    public function handle(MessageSending $event): ?bool
    {
        if ($this->mailEnabled()) {
            return null;
        }

        Log::info('Outgoing mail suppressed: mail is disabled in the panel settings.', [
            'to' => implode(', ', array_keys($event->message->getTo() ?? [])),
            'subject' => $event->message->getSubject(),
        ]);

        return false;
    }

    private function mailEnabled(): bool
    {
        try {
            $value = Setting::get('MailEnabled');
        } catch (\Throwable) {
            // Settings unreadable (installer, broken database) — never swallow mail.
            return true;
        }

        if ($value === null || $value === '') {
            return true;
        }

        return ! in_array(strtolower((string) $value), ['0', 'false', 'off', 'no'], true);
    }
}
