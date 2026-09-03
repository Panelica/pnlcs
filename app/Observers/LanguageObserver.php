<?php

namespace App\Observers;

use App\Models\EmailTemplate;
use App\Models\Language;

/**
 * When a new language is added, seed it with untranslated copies of every
 * English template so the email-templates screen shows a full, editable set
 * flagged "Translate" rather than an empty page.
 */
class LanguageObserver
{
    public function created(Language $language): void
    {
        if ($language->code === 'en') {
            return;
        }

        try {
            $english = EmailTemplate::where('language', 'en')->get();
        } catch (\Throwable) {
            return;
        }

        foreach ($english as $en) {
            EmailTemplate::updateOrCreate(
                ['name' => $en->name, 'language' => $language->code],
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
}
