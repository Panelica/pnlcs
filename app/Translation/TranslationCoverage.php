<?php

namespace App\Translation;

use App\Models\DynamicTranslation;

/**
 * Which interface texts a language says in its own words.
 *
 * One answer for the progress under Setup > Languages and for "translate
 * missing". A text counts when the language has one - its database override
 * if there is one, else its file - and it is not simply the English text.
 * Reported on a live install: a German install whose texts were almost all
 * English showed "74% translated", and "translate missing" found nearly
 * nothing to do, because any non-empty text counted, English or not. The
 * shipped stubs (lang/fr and others return the English file) were counted
 * the same way.
 *
 * A text identical to English still counts when:
 *  - the English text has no words to translate (SSL, :count, 1:1);
 *  - machine translation gave the English text back (is_auto_translated),
 *    so it is not asked about again and again;
 *  - the shipped translation writes it that way on purpose
 *    (database/data/same_as_english.php: Stripe, Status, Logo).
 * A database text the operator imported or saved that is the English text
 * does not: that is how an English export ends up looking translated.
 */
class TranslationCoverage
{
    public function __construct(private OfficialTranslationRepository $official) {}

    /** @return array<string, string> "group.key" => English, every key English has text for */
    public function english(): array
    {
        $english = [];
        foreach ($this->official->forLocale('en') as $group => $keys) {
            foreach ($keys as $key => $value) {
                if (trim((string) $value) !== '') {
                    $english[$group.'.'.$key] = (string) $value;
                }
            }
        }

        return $english;
    }

    /** @return array<string, true> the "group.key"s of english() the language has in its own words */
    public function translated(string $locale): array
    {
        $english = $this->english();
        if ($locale === 'en') {
            return array_fill_keys(array_keys($english), true);
        }

        $text = [];
        foreach ($this->official->forLocale($locale) as $group => $keys) {
            foreach ($keys as $key => $value) {
                $text[$group.'.'.$key] = (string) $value;
            }
        }

        $confirmed = [];
        DynamicTranslation::where('language', $locale)
            ->whereNotNull('value')->where('value', '!=', '')
            ->get(['group', 'key', 'value', 'is_auto_translated'])
            ->each(function ($row) use (&$text, &$confirmed) {
                $fullKey = $row->group.'.'.$row->key;
                $text[$fullKey] = (string) $row->value;
                if ($row->is_auto_translated) {
                    $confirmed[$fullKey] = true;
                }
            });

        $sameOnPurpose = array_fill_keys((require database_path('data/same_as_english.php'))[$locale] ?? [], true);

        $translated = [];
        foreach ($english as $fullKey => $source) {
            $value = $text[$fullKey] ?? '';
            if (trim($value) === '') {
                continue;
            }
            if ($value !== $source
                || self::hasNoWords($source)
                || isset($confirmed[$fullKey])
                || isset($sameOnPurpose[$fullKey])) {
                $translated[$fullKey] = true;
            }
        }

        return $translated;
    }

    /** Progress in percent, one decimal; 100 only when nothing is missing, never by rounding up. */
    public function percent(string $locale): float
    {
        $total = count($this->english());
        if ($total === 0) {
            return 0.0;
        }

        $done = count($this->translated($locale));

        return $done >= $total ? 100.0 : min(round($done / $total * 100, 1), 99.9);
    }

    /** Nothing to translate: no lowercase letter once placeholders and markup are gone (SSL, :count). */
    public static function hasNoWords(string $text): bool
    {
        $text = strip_tags((string) preg_replace('/:[A-Za-z_][A-Za-z0-9_]*/', '', $text));

        return ! preg_match('/\p{Ll}/u', $text);
    }
}
