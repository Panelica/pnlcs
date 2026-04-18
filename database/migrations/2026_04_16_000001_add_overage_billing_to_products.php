<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table("products", function (Blueprint $table) {
            $table->boolean("overage_enabled")->default(false)->after("tax");
            $table->decimal("overage_disk_rate", 10, 4)->default(0)->after("overage_enabled")
                  ->comment("Rate per MB over disk limit");
            $table->decimal("overage_bw_rate", 10, 4)->default(0)->after("overage_disk_rate")
                  ->comment("Rate per MB over bandwidth limit");
        });
    }

    public function down(): void
    {
        Schema::table("products", function (Blueprint $table) {
            $table->dropColumn(["overage_enabled", "overage_disk_rate", "overage_bw_rate"]);
        });
    }
};
