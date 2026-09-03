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
        if ($template->language === null || $template->language === '' || $template->language === 'en') {
            $template->update(['language' => $template->language ?: 'en']);

            if ($template->language === 'en') {
                $this->propagate($template);
            }

            return;
        }

        // A template created directly in another language stands on its own;
        // nothing to propagate from it.
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
