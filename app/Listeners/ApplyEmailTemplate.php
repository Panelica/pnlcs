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
    /** Mailables that must never be copied to a second address. */
    private const NEVER_COPIED = [
        'PasswordResetMail',
        // Carries an app's generated passwords in the clear, on the account
        // owner's own request - for their eyes only.
        'ContainerAccessDetailsMail',
    ];

    /**
     * r137-never-off: mailables a template switch must not be able to stop.
     *
     * The reset link is the only way back into an account. The two password
     * templates are also named the wrong way round - the one called "Password
     * Reset Confirmation" carries the link, while "Password Reset Validation"
     * holds the after-the-fact note that nothing sends - so an operator
     * switching off what sounds like a courtesy email switched off the way
     * customers recover their accounts, and nothing said why.
     *
     * The rest of the template still applies: subject, from address, body.
     */
    private const NEVER_SUPPRESSED = [
        'PasswordResetMail',
    ];

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
            if (in_array(class_basename($mailable), self::NEVER_SUPPRESSED, true)) {
                Log::warning('A switched-off template cannot stop this email; it is the only way back into an account.', [
                    'template' => $template->name,
                    'mailable' => class_basename($mailable),
                ]);
            } else {
                Log::info('Outgoing mail suppressed: the template is switched off.', [
                    'template' => $template->name,
                    'subject' => $event->message->getSubject(),
                ]);

                return false;
            }
        }

        $vars = $this->templates->varsFor($event->data);

        if (filled($template->subject)) {
            $subject = $this->templates->merge($template->subject, $vars);

            // A merge field this email carries nothing for would go out with
            // the braces showing. The mailable's own subject is a complete
            // sentence, so keep that instead and say why in the log.
            if (preg_match('/\{[A-Za-z_][A-Za-z0-9_]*\}/', $subject, $unresolved)) {
                Log::warning('Template subject left as the mailable wrote it: this email carries nothing for that merge field.', [
                    'template' => $template->name,
                    'field' => $unresolved[0],
                ]);
            } else {
                $event->message->subject($subject);
            }
        }

        if (filled($template->from_email)) {
            $event->message->from(new Address(
                $template->from_email,
                (string) ($template->from_name ?: '')
            ));
        }

        // Anything carrying a credential or a sign-in link goes to the person
        // who asked for it and nobody else. Every contact on an account is
        // flagged for general email, and a password reset is a general
        // template, so copying these would hand the reset link to colleagues.
        if (! in_array(class_basename($mailable), self::NEVER_COPIED, true)) {
            foreach ($this->copyTo($template->copy_to) as $address) {
                $event->message->addBcc($address);
            }

            foreach ($this->contactRecipients($event, $template) as $address) {
                $event->message->addCc($address);
            }
        }

        // Only once the operator has made it theirs: replacing the body of an
        // untouched template would swap every email's design for the seeded
        // plain text.
        if ($template->custom && filled($template->message)) {
            $body = $this->templates->merge((string) $template->message, $vars);

            // The same question the subject is asked twenty lines up. A body
            // still holding a merge field would go out with the braces showing,
            // so keep the one the mailable built and say which field was empty.
            if (preg_match('/\{[A-Za-z_][A-Za-z0-9_]*\}/', $body, $unfed)) {
                Log::warning('Template body left as the mailable wrote it: this email carries nothing for that merge field.', [
                    'template' => $template->name,
                    'field' => $unfed[0],
                ]);

                return null;
            }

            // A template marked plaintext goes out as text only. The column has
            // been on the table all along and nothing read it, so the setting
            // meant nothing and the mail went out as HTML regardless; the html
            // part the mailable built has to be cleared, not just skipped.
            if ($template->plaintext) {
                $event->message->html(null);
            } else {
                $event->message->html(nl2br(e($body), false));
            }

            $event->message->text($body);
        }

        return null;
    }

    /**
     * The client's contacts who asked for this kind of email.
     *
     * @return array<int, string>
     */
    private function contactRecipients($event, $template): array
    {
        $client = $this->templates->clientFrom($event->data);

        if (! $client) {
            return [];
        }

        // r124-already: the addresses, not the positions. This read the keys of
        // the recipient list, which are 0, 1, 2 - so nobody was ever recognised
        // as being on the message already, and a customer who had added their
        // own address as a billing contact received every invoice twice: once
        // to them, once copied to them.
        $already = array_map(
            fn ($address) => strtolower($address->getAddress()),
            array_merge($event->message->getTo() ?: [], $event->message->getCc() ?: [])
        );
        $addresses = [];

        try {
            $contacts = $client->contacts()->get();
        } catch (\Throwable) {
            return [];
        }

        foreach ($contacts as $contact) {
            $address = strtolower(trim((string) $contact->email));

            if ($address === '' || in_array($address, $already, true)) {
                continue;
            }

            if ($contact->wantsEmailsFor($template->type)) {
                $addresses[] = $address;
            }
        }

        return array_values(array_unique($addresses));
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
