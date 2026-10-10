<?php

use App\Translation\ShippedEmailTemplates;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Email templates in the languages PNLCS ships them for (German, Polish).
 *
 * A language added to an install received copies of the English templates,
 * so a German customer's emails went out under an English subject line: the
 * subject is taken from the template row even while the operator has not
 * made the template their own. New installs and newly added languages now
 * start from the shipped translation (LanguageObserver); this gives it to the
 * rows that are still the English copy.
 *
 * Only what is still exactly the English text is replaced, field by field: a
 * subject or message the operator wrote stays, and so does every template the
 * operator made their own (custom). Running it again changes nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (ShippedEmailTemplates::locales() as $locale) {
            if ($locale === 'en') {
                continue;
            }

            foreach (ShippedEmailTemplates::for($locale) as $name => $translated) {
                $this->replace($locale, $name, fn ($row, $english) => [
                    'subject' => $row->subject === $english->subject ? $translated['subject'] : $row->subject,
                    'message' => $row->message === $english->message ? $translated['message'] : $row->message,
                ]);
            }
        }
    }

    /**
     * German goes back to the English copy where it is still the shipped
     * text. Polish stays: 2026_09_03_000001 had already shipped the same
     * Polish texts, and this cannot tell its rows from the ones changed here.
     */
    public function down(): void
    {
        foreach (ShippedEmailTemplates::for('de') as $name => $translated) {
            $this->replace('de', $name, fn ($row, $english) => [
                'subject' => $row->subject === $translated['subject'] ? $english->subject : $row->subject,
                'message' => $row->message === $translated['message'] ? $english->message : $row->message,
            ]);
        }
    }

    private function replace(string $locale, string $name, Closure $values): void
    {
        $english = DB::table('email_templates')->where('name', $name)->where('language', 'en')->first();
        $row = DB::table('email_templates')->where('name', $name)->where('language', $locale)->where('custom', false)->first();

        if (! $english || ! $row) {
            return;
        }

        $new = $values($row, $english);
        if ($new['subject'] !== $row->subject || $new['message'] !== $row->message) {
            DB::table('email_templates')->where('id', $row->id)->update($new + ['updated_at' => now()]);
        }
    }
};
