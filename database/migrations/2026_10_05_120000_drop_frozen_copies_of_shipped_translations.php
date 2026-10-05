<?php

use App\Translation\FrozenTranslationCleaner;
use Illuminate\Database\Migrations\Migration;

/**
 * Remove database translations that are only frozen copies of shipped text.
 *
 * A row in dynamic_translations always wins over lang/<locale>. Three things
 * filled the table with copies of the files rather than with anything an
 * operator wrote, and each copy stopped that text from ever improving:
 *
 *  - The editor's Save stored all fifty rows of the page, changed or not, so
 *    one corrected word froze forty-nine file texts as they were that day.
 *    On a Turkish install that meant the first release's word-by-word output
 *    - "Geriup Kods", "Ekleress", "Sizin account devre disi birakildi" - long
 *    after lang/tr had been rewritten. Until 2026-09-19 the loader ignored
 *    dotted database keys, so these rows lay dormant; the fix that made saved
 *    texts show brought them all to the screen.
 *  - TranslationSeeder wrote an April snapshot of the English on every install;
 *    93 of those texts have changed in lang/en since.
 *  - Seed migrations copied whole language files into the table (zh: 5,540
 *    rows), each a copy of the file on the day it ran.
 *
 * Removed, and only these - nothing an operator could have meant:
 *
 *  1. Any row whose text is exactly what its language file says now. Nobody
 *     sees a difference today, and the file's next improvement shows.
 *  2. English rows still holding TranslationSeeder's snapshot text where
 *     lang/en now says something else.
 *  3. Rows holding a text their language file once shipped and has since
 *     replaced (database/data/retired_translations.php): the whole history of
 *     lang/tr, and the English, Turkish, German, Polish and Chinese labels
 *     rewritten on 2026-10-05.
 *
 * Rows that differ from the file in any other way stay: some are an operator's
 * own wording, and some are better than the file (a German seed has
 * "Anwendungen" where lang/de says "Bewerbungen").
 *
 * Not reversible: the removed rows said nothing the files did not, or said
 * what the project itself replaced.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(FrozenTranslationCleaner::class)->run();
    }

    public function down(): void
    {
        // Nothing to put back: see the class comment.
    }
};
