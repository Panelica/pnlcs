<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Record what the customer agreed to when they placed the order.
 *
 * The checkout has always validated "terms => accepted" and then thrown the
 * answer away. That matters more than it looks: Turkish consumer law lets a
 * seller exclude domain registrations from the 14-day right of withdrawal
 * ONLY if the buyer was told so before ordering. Our supplier refunds nothing
 * on any of its 720 extensions, so that exclusion is the only thing standing
 * between us and paying for every cancelled domain out of our own pocket —
 * and in front of a Tüketici Hakem Heyeti it is worth exactly as much as the
 * evidence behind it.
 *
 * So: when, from where, and which revision of the documents.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('terms_accepted_at')->nullable()->after('ip_address');
            // The revision date of the documents in force at that moment, not
            // a link to them: the customer is entitled to see the text they
            // agreed to, and a link would follow later edits.
            $table->string('terms_version', 32)->nullable()->after('terms_accepted_at');
            $table->string('terms_ip', 45)->nullable()->after('terms_version');
            $table->json('terms_documents')->nullable()->after('terms_ip');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['terms_accepted_at', 'terms_version', 'terms_ip', 'terms_documents']);
        });
    }
};
