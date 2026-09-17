<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The customer's own answer to "may we take this from the card on file?".
 *
 * An operator switch is not consent. AutoChargeEnabled says the shop collects
 * by card; it says nothing about the one person whose money it is, and the
 * worst thing this feature can do is take money from somebody who has asked it
 * to stop. This column is where they ask.
 *
 * DEFAULT TRUE, and that is the house answer rather than a convenient one.
 * services.auto_renew is a boolean defaulting to true
 * (2026_04_05_120001_add_auto_renew_to_services), domains.payment_method has to
 * be set to the string 'none' before the generator will leave a domain alone,
 * and clients.override_auto_suspend defaults to false meaning "the automation
 * applies". Every billing automation already in PNLCS is on until somebody
 * turns it off, and a switch that worked the other way round would be a
 * different kind of surprise: a customer who had asked for automatic payment,
 * in a shop that had switched it on, still being chased for a late fee.
 *
 * NOTHING CHANGES WHILE THE FEATURE IS OFF. The only reader is the charger's
 * candidate query, and the charger returns before it reads an invoice unless
 * AutoChargeEnabled is set. On an installation that never switches it on, this
 * column is a true nobody looks at.
 *
 * Per client rather than per card or per service, because the charger's unit is
 * the invoice and an invoice belongs to a client. An invoice groups renewals
 * from several services and domains at once, so a per-service switch could not
 * answer the question the charger actually asks. The per-service and
 * per-domain switches that already exist answer a different one — whether a
 * bill is raised at all — and are left exactly as they are.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->boolean('auto_charge')->default(true)->after('default_payment_method');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('auto_charge');
        });
    }
};
