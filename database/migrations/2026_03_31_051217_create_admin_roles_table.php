<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("admin_roles", function (Blueprint $table) {
            $table->id();
            $table->string("name");
            $table->string("description")->nullable();
            $table->boolean("is_full_admin")->default(false);
            $table->json("widgets")->nullable();
            $table->json("permissions")->nullable();
            $table->boolean("system_emails")->default(false);
            $table->boolean("account_emails")->default(false);
            $table->boolean("support_emails")->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("admin_roles");
    }
};
