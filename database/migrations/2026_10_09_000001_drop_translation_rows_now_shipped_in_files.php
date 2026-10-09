<?php

use App\Translation\FrozenTranslationCleaner;
use Illuminate\Database\Migrations\Migration;

/**
 * The client hosting pages, the mail setup and SSL guides and the apps section
 * of the home page used to be delivered as database rows by the migrations
 * that added them (English and Turkish, a few German). Those texts now ship in
 * lang/en, lang/tr, lang/de, lang/pl and lang/zh, so every row that says
 * exactly what the file says is only a copy - and a copy in the database hides
 * every later correction made to the file.
 *
 * The same clean-up as 2026_10_05_130000: a row is removed only when it is
 * identical to the shipped text. A row the operator wrote differently stays,
 * and what the pages show does not change.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(FrozenTranslationCleaner::class)->run();
    }

    public function down(): void
    {
        // Nothing to put back: the files say the same.
    }
};
