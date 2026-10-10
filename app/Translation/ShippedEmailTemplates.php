<?php

namespace App\Translation;

/**
 * The email templates PNLCS ships translated, per language:
 * database/data/email_templates/<locale>.php, template name => subject and
 * message.
 *
 * Templates are rows (Setup > Email Templates), one set per language, and a
 * new language gets a copy of the English set. Where a translation is shipped
 * the copy starts from it instead (LanguageObserver, EmailTemplateObserver);
 * installs that already hold English copies get it by migration
 * (2026_10_10_000001_ship_translated_email_templates).
 */
class ShippedEmailTemplates
{
    /** @return array<string, array{subject: string, message: string}> */
    public static function for(string $locale): array
    {
        $locale = strtolower($locale);
        if (! preg_match('/^[a-z]{2,3}(-[a-z0-9]+)?$/', $locale)) {
            return [];
        }

        $file = database_path("data/email_templates/{$locale}.php");

        return is_file($file) ? (array) require $file : [];
    }

    /** @return list<string> the languages a translation is shipped for */
    public static function locales(): array
    {
        return array_map(fn ($file) => basename($file, '.php'), glob(database_path('data/email_templates/*.php')) ?: []);
    }
}
