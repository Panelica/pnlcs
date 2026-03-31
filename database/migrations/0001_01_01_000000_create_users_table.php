<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("users", function (Blueprint $table) {
            $table->id();
            $table->string("first_name");
            $table->string("last_name");
            $table->string("email")->unique();
            $table->timestamp("email_verified_at")->nullable();
            $table->string("password");
            $table->string("second_factor_type")->nullable(); // totp, webauthn, sms
            $table->text("second_factor_secret")->nullable();
            $table->text("backup_codes")->nullable(); // JSON encrypted
            $table->string("language", 10)->default("en");
            $table->timestamp("last_login")->nullable();
            $table->string("last_login_ip", 45)->nullable();
            $table->boolean("is_active")->default(true);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create("password_reset_tokens", function (Blueprint $table) {
            $table->string("email")->primary();
            $table->string("token");
            $table->timestamp("created_at")->nullable();
        });

        Schema::create("sessions", function (Blueprint $table) {
            $table->string("id")->primary();
            $table->foreignId("user_id")->nullable()->index();
            $table->string("ip_address", 45)->nullable();
            $table->text("user_agent")->nullable();
            $table->longText("payload");
            $table->integer("last_activity")->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("sessions");
        Schema::dropIfExists("password_reset_tokens");
        Schema::dropIfExists("users");
    }
};
