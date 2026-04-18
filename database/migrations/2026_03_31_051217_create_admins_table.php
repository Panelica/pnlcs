<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("admins", function (Blueprint $table) {
            $table->id();
            $table->uuid("uuid")->unique();
            $table->foreignId("role_id")->constrained("admin_roles");
            $table->string("username")->unique();
            $table->string("email")->unique();
            $table->string("password");
            $table->string("first_name");
            $table->string("last_name");
            $table->text("signature")->nullable();
            $table->boolean("is_disabled")->default(false);
            $table->json("support_departments")->nullable();
            $table->boolean("ticket_notifications")->default(true);
            $table->string("second_factor_type")->nullable();
            $table->text("second_factor_secret")->nullable();
            $table->string("language", 10)->default("en");
            $table->timestamp("last_login")->nullable();
            $table->string("last_login_ip", 45)->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create("admin_logs", function (Blueprint $table) {
            $table->id();
            $table->string("admin_username");
            $table->timestamp("login_time");
            $table->timestamp("logout_time")->nullable();
            $table->string("ip_address", 45);
            $table->string("session_id")->nullable();
            $table->timestamps();

            $table->index("admin_username");
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("admin_logs");
        Schema::dropIfExists("admins");
    }
};
