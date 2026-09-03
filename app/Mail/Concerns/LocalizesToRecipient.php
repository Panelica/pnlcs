<?php

namespace App\Mail\Concerns;

use App\Models\Client;

/**
 * Makes a mailable render in the receiving client's language.
 *
 * Laravel renders a mailable's Blade view inside Mailable::send(), wrapped in
 * withLocale($mailable->locale, …). The locale must therefore be set before
 * the mail is dispatched — the constructor is the right place. Each mailable
 * hands this trait the model (or email) the message is about, and it sets
 * $mailable->locale from that client's language, defaulting to English.
 */
trait LocalizesToRecipient
{
    protected function localizeTo(mixed $source): void
    {
        $client = $this->resolveClient($source);

        $language = $client->language ?? null;
        if (is_string($language) && $language !== '' && $language !== 'en') {
            $this->locale($language);
        }
    }

    private function resolveClient(mixed $source): ?Client
    {
        if ($source instanceof Client) {
            return $source;
        }

        if (is_object($source) && method_exists($source, 'client')) {
            try {
                $client = $source->client;
            } catch (\Throwable) {
                $client = null;
            }

            if ($client instanceof Client) {
                return $client;
            }
        }

        if (is_string($source) && $source !== '') {
            try {
                return Client::where('email', $source)->first();
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }
}
