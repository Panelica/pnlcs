<?php

namespace App\Listeners;

use App\Services\EmailTemplateService;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mime\Address;

/**
 * Makes the email templates screen mean something.
 *
 * Every mailable passes through MessageSending, direct or queued, so the
 * template's disable switch, subject, from address and copy-to address are
 * applied here rather than in 23 separate mailables.
 *
 * The body still comes from the built-in design; only what the operator can
 * set on the envelope is honoured so far.
 */
class ApplyEmailTemplate
{
    public function __construct(private EmailTemplateService $templates) {}

    /** Returns null to allow, false to cancel — the dispatch halts on anything else. */
    public function handle(MessageSending $event): ?bool
    {
        $mailable = $event->data['__laravel_mailable'] ?? null;

        if (! is_string($mailable) && ! is_object($mailable)) {
            return null;
        }

        $template = $this->templates->forMailable($mailable);

        if (! $template) {
            return null;
        }

        if ($template->disabled) {
            Log::info('Outgoing mail suppressed: the template is switched off.', [
                'template' => $template->name,
                'subject' => $event->message->getSubject(),
            ]);

            return false;
        }

        $vars = $this->templates->varsFor($event->data);

        if (filled($template->subject)) {
            $event->message->subject($this->templates->merge($template->subject, $vars));
        }

        if (filled($template->from_email)) {
            $event->message->from(new Address(
                $template->from_email,
                (string) ($template->from_name ?: '')
            ));
        }

        foreach ($this->copyTo($template->copy_to) as $address) {
            $event->message->addBcc($address);
        }

        // Only once the operator has made it theirs: replacing the body of an
        // untouched template would swap every email's design for the seeded
        // plain text.
        if ($template->custom && filled($template->message)) {
            $body = $this->templates->merge((string) $template->message, $vars);

            $event->message->html(nl2br(e($body), false));
            $event->message->text($body);
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function copyTo(?string $raw): array
    {
        if (! filled($raw)) {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', preg_split('/[,;]/', $raw) ?: []),
            fn ($address) => filter_var($address, FILTER_VALIDATE_EMAIL) !== false
        ));
    }
}
