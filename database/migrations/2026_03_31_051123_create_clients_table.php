<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("clients", function (Blueprint $table) {
            $table->id();
            $table->uuid("uuid")->unique();
            $table->string("first_name");
            $table->string("last_name");
            $table->string("company_name")->nullable();
            $table->string("email");
            $table->string("address1")->nullable();
            $table->string("address2")->nullable();
            $table->string("city")->nullable();
            $table->string("state")->nullable();
            $table->string("postcode", 20)->nullable();
            $table->string("country", 2)->default("US");
            $table->string("phone_number", 30)->nullable();
            $table->string("tax_id", 50)->nullable();
            $table->string("status", 20)->default("active"); // active, inactive, closed
            $table->unsignedBigInteger("group_id")->nullable();
            $table->unsignedBigInteger("currency_id")->nullable();
            $table->decimal("credit", 15, 2)->default(0);
            $table->boolean("tax_exempt")->default(false);
            $table->boolean("late_fee_overide")->default(false);
            $table->boolean("override_auto_suspend")->default(false);
            $table->string("language", 10)->default("en");
            $table->text("notes")->nullable();
            $table->string("ip_address", 45)->nullable(); // signup IP
            $table->timestamps();
            $table->softDeletes();

            $table->index("status");
            $table->index("group_id");
            $table->index("email");
        });

        // Pivot: users <-> clients (WHMCS tblusers_clients)
        Schema::create("user_client", function (Blueprint $table) {
            $table->id();
            $table->foreignId("user_id")->constrained()->cascadeOnDelete();
            $table->foreignId("client_id")->constrained()->cascadeOnDelete();
            $table->boolean("owner")->default(false);
            $table->json("permissions")->nullable();
            $table->timestamps();

            $table->unique(["user_id", "client_id"]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("user_client");
        Schema::dropIfExists("clients");
    }
};
