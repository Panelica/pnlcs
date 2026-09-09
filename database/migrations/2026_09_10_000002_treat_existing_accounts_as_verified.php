<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Verification arrives switched on, and that must not lock out the customers
 * who are already here.
 *
 * Every account that existed before this release was opened under a rule that
 * never asked for proof, and none of them was ever sent a link. Applying the
 * new rule to them would stop them ordering on the day their host upgrades,
 * for something they were never given the chance to do. The rule is for
 * accounts opened from now on.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->whereNull('email_verified_at')
            ->update(['email_verified_at' => now()]);
    }

    public function down(): void
    {
        // Not reversible on purpose: nothing records which rows this stamped,
        // and clearing them all would un-verify the customers who really did
        // click their link.
    }
};
