<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table("domains", function (Blueprint $table) {
            $table->string("renewal_reminder_stage", 20)->nullable()->after("last_sync_status");
            $table->timestamp("renewal_reminder_sent_at")->nullable()->after("renewal_reminder_stage");
        });
    }
    public function down(): void {
        Schema::table("domains", function (Blueprint $table) {
            $table->dropColumn(["renewal_reminder_stage", "renewal_reminder_sent_at"]);
        });
    }
};
