<?php

use App\Translation\FrozenTranslationCleaner;
use Illuminate\Database\Migrations\Migration;

/**
 * The same clean-up as 2026_10_05_120000_drop_frozen_copies_of_shipped_translations,
 * with a longer list of retired texts.
 *
 * The first list read each old lang/tr file one value per key, on main only.
 * The files of April wrote many keys twice - 'affiliates' => ['referral_link'
 * => 'Referral Baglanti:'] beside 'affiliates.referral_link' => 'Referral
 * Bağlantı:' - so the broken twin was never listed, and part of the early
 * Turkish lived on a branch that never reached main. On the install where the
 * problem was reported, 612 rows of that Turkish ("Yonetici Nots", "Giris
 * Yap", "Kayit Tarih") survived the first pass. database/data/retired_translations.php
 * now holds every value of every lang/tr file on every branch, nested twins
 * included.
 *
 * Installs that have not run the first migration yet get both; the second
 * finds nothing left the first one did not see.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(FrozenTranslationCleaner::class)->run();
    }

    public function down(): void
    {
        // Nothing to put back: the removed rows were copies of retired text.
    }
};
