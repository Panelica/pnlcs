<?php

namespace App\Listeners;

use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Log;

/**
 * The EmailPreSend hook, as WHMCS has it: an addon sees every outgoing mail
 * just before it leaves, and can stop it by returning ['abortsend' => true]
 * (a copy kept elsewhere, a recipient on a block list, a mail the customer
 * opted out of). Runs last, on the message as it will be sent.
 */
class RunEmailPreSendHook
{
    /** Null to let the mail go, false to cancel it (MessageSending halts on the first non-null). */
    public function handle(MessageSending $event): ?bool
    {
        $answers = run_hook('EmailPreSend', [
            'to' => array_keys($event->message->getTo() ?? []),
            'subject' => (string) $event->message->getSubject(),
            'message' => $event->message,
        ]);

        foreach ($answers as $answer) {
            if (is_array($answer) && ! empty($answer['abortsend'])) {
                Log::info('Outgoing mail stopped by the EmailPreSend hook.', ['subject' => $event->message->getSubject()]);

                return false;
            }
        }

        return null;
    }
}
