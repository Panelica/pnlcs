<?php

namespace App\Observers;

use App\Models\EmailTemplate;
use App\Models\Language;
use App\Translation\ShippedEmailTemplates;

/**
 * When a new language is added, seed it with copies of every English template
 * so the email-templates screen shows a full, editable set rather than an
 * empty page: the translation PNLCS ships for that language where there is
 * one (ShippedEmailTemplates), otherwise the English text, flagged
 * "Translate".
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

        $shipped = ShippedEmailTemplates::for($language->code);

        foreach ($english as $en) {
            EmailTemplate::updateOrCreate(
                ['name' => $en->name, 'language' => $language->code],
                [
                    'type' => $en->type,
                    'subject' => $shipped[$en->name]['subject'] ?? $en->subject,
                    'message' => $shipped[$en->name]['message'] ?? $en->message,
                    'custom' => false,
                    'disabled' => false,
                    'plaintext' => $en->plaintext ?? false,
                ],
            );
        }
    }
}
