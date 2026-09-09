<?php

namespace App\Listeners;

use Illuminate\Mail\Events\MessageSending;

/**
 * Every email leaves with a plain-text part beside the HTML.
 *
 * All the mailables send HTML only. SpamAssassin scores that as
 * MIME_HTML_ONLY, and Gmail and Outlook treat mail that is not multipart as a
 * sign of bulk sending - exactly where mail from a freshly registered domain is
 * already at its weakest. Rather than revisiting every template, the text part
 * is produced here from the final HTML at the moment of sending, so a mailable
 * written tomorrow comes out two-part without anyone remembering to do it.
 *
 * A mailable that defines its own text version is left alone.
 */
class AddPlainTextPart
{
    public function handle(MessageSending $event): void
    {
        $message = $event->message;

        if ($message->getTextBody() !== null) {
            return;
        }

        $html = $message->getHtmlBody();
        if (! is_string($html) || trim($html) === '') {
            return;
        }

        $message->text($this->plainText($html));
    }

    /**
     * Readable text out of HTML. Links are kept on their own: a button's label
     * leaves nothing to click on in the text version, and the address is the
     * one thing the customer actually needs from it.
     */
    private function plainText(string $html): string
    {
        $text = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', $html) ?? $html;

        // <a href="...">Label</a> -> Label ( ... )
        $text = preg_replace_callback(
            '#<a\b[^>]*href=(["\'])(.*?)\1[^>]*>(.*?)</a>#is',
            function (array $m): string {
                $address = html_entity_decode($m[2], ENT_QUOTES, 'UTF-8');
                $label = trim(html_entity_decode(strip_tags($m[3]), ENT_QUOTES, 'UTF-8'));

                if ($label === '' || $label === $address) {
                    return $address;
                }

                // Round brackets, not angle ones: strip_tags below took a
                // "<https://...>" for a tag and removed it, leaving the
                // button's label in the text version and losing its address.
                return $label.' ( '.$address.' )';
            },
            $text
        ) ?? $text;

        // Block endings become line breaks; whatever tags remain are dropped.
        $text = preg_replace('#<br\s*/?>#i', "\n", $text) ?? $text;
        $text = preg_replace('#</(p|div|tr|h[1-6]|li|table)>#i', "\n\n", $text) ?? $text;
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Indentation and piled-up blank lines go.
        $lines = array_map(fn (string $line): string => trim($line), explode("\n", $text));
        $text = implode("\n", $lines);
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }
}
