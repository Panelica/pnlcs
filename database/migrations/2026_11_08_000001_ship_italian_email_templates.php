<?php

use App\Translation\ShippedEmailTemplates;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Italian email templates, now shipped (database/data/email_templates/it.php).
 *
 * An install that already has Italian holds English copies of the templates
 * (2026_10_10_000001 did this for German and Polish). As there: only what is
 * still exactly the English text is replaced, field by field, so a subject or
 * message the operator wrote stays, and so does every template the operator
 * made their own. Running it again changes nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (ShippedEmailTemplates::for('it') as $name => $translated) {
            $this->replace($name, fn ($row, $english) => [
                'subject' => $row->subject === $english->subject ? $translated['subject'] : $row->subject,
                'message' => $row->message === $english->message ? $translated['message'] : $row->message,
            ]);
        }
    }

    /** Italian goes back to the English copy where it is still the shipped text. */
    public function down(): void
    {
        foreach (ShippedEmailTemplates::for('it') as $name => $translated) {
            $this->replace($name, fn ($row, $english) => [
                'subject' => $row->subject === $translated['subject'] ? $english->subject : $row->subject,
                'message' => $row->message === $translated['message'] ? $english->message : $row->message,
            ]);
        }
    }

    private function replace(string $name, Closure $values): void
    {
        $english = DB::table('email_templates')->where('name', $name)->where('language', 'en')->first();
        $row = DB::table('email_templates')->where('name', $name)->where('language', 'it')->where('custom', false)->first();

        if (! $english || ! $row) {
            return;
        }

        $new = $values($row, $english);
        if ($new['subject'] !== $row->subject || $new['message'] !== $row->message) {
            DB::table('email_templates')->where('id', $row->id)->update($new + ['updated_at' => now()]);
        }
    }
};
