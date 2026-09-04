<?php

namespace App\Observers;

use App\Models\EmailTemplate;
use App\Models\Language;

/**
 * Keeps the non-English template sets in step with English.
 *
 * A template written in English is the canonical copy. The moment one is
 * created, an untranslated (custom=false) copy carrying the English wording is
 * added to every other language so the email-templates screen can show it
 * flagged "Translate" instead of it simply never appearing.
 */
class EmailTemplateObserver
{
    public function created(EmailTemplate $template): void
    {
        // The column defaults to 'en' in the schema, but a freshly inserted
        // model does not read that default back, so treat a missing value as
        // English here without an extra UPDATE round-trip.
        $language = $template->language;

        if ($language === null || $language === '') {
            $language = 'en';
        }

        if ($language === 'en') {
            $this->propagate($template);
        }
    }

    private function propagate(EmailTemplate $en): void
    {
        foreach ($this->otherLanguages() as $code) {
            EmailTemplate::updateOrCreate(
                ['name' => $en->name, 'language' => $code],
                [
                    'type' => $en->type,
                    'subject' => $en->subject,
                    'message' => $en->message,
                    'custom' => false,
                    'disabled' => false,
                    'plaintext' => $en->plaintext ?? false,
                ],
            );
        }
    }

    /**
     * @return array<int, string>
     */
    private function otherLanguages(): array
    {
        try {
            return Language::where('code', '<>', 'en')->pluck('code')->all();
        } catch (\Throwable) {
            return [];
        }
    }
}
