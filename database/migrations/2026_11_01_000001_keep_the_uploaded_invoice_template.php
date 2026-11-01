<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * An invoice template uploaded before #361 keeps printing.
 *
 * Until #361 there was one uploaded template, storage/app/invoice-templates/
 * custom.html, switched on by the InvoiceTemplate setting. The renderer now
 * looks for <LANG>_invoice_template_v<VER>.html instead and does not know
 * custom.html, so an installation that had uploaded one would have gone back
 * to the built-in layout at the next update, silently, on documents that are
 * already in customers' hands.
 *
 * The file is copied, not moved: the installation that rolls the update back
 * finds custom.html where it left it.
 *
 * The path is written out rather than read from InvoicePdfService: a migration
 * has to keep saying what it said on the day it ran.
 */
return new class extends Migration
{
    public function up(): void
    {
        $dir = storage_path('app/invoice-templates');
        $custom = $dir.'/custom.html';

        if (! is_file($custom)) {
            return;
        }

        // Only one that was actually in use. A file left behind by a template
        // the operator switched off is not reinstated.
        if ((string) DB::table('settings')->where('setting', 'InvoiceTemplate')->value('value') !== 'custom') {
            return;
        }

        // Nothing to rescue once language templates are in use.
        if (glob($dir.'/*_invoice_template_v*.html')) {
            return;
        }

        $language = strtoupper(substr((string) (DB::table('settings')->where('setting', 'DefaultLanguage')->value('value') ?: 'en'), 0, 2));
        if (! preg_match('/^[A-Z]{2}$/', $language)) {
            $language = 'EN';
        }

        copy($custom, $dir.'/'.$language.'_invoice_template_v1.00.html');
    }

    public function down(): void
    {
        $dir = storage_path('app/invoice-templates');
        $custom = $dir.'/custom.html';

        if (! is_file($custom)) {
            return;
        }

        // Only the copy this migration made: one the operator has since
        // uploaded or edited stays.
        foreach (glob($dir.'/??_invoice_template_v1.00.html') ?: [] as $file) {
            if (md5_file($file) === md5_file($custom)) {
                unlink($file);
            }
        }
    }
};
