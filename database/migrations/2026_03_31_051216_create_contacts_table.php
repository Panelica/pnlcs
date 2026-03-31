<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("contacts", function (Blueprint $table) {
            $table->id();
            $table->foreignId("client_id")->constrained()->cascadeOnDelete();
            $table->string("first_name");
            $table->string("last_name");
            $table->string("email");
            $table->string("company_name")->nullable();
            $table->string("address1")->nullable();
            $table->string("address2")->nullable();
            $table->string("city")->nullable();
            $table->string("state")->nullable();
            $table->string("postcode", 20)->nullable();
            $table->string("country", 2)->default("US");
            $table->string("phone_number", 30)->nullable();
            $table->boolean("is_sub_account")->default(false);
            $table->string("password")->nullable();
            $table->json("permissions")->nullable();
            $table->boolean("general_emails")->default(true);
            $table->boolean("product_emails")->default(true);
            $table->boolean("domain_emails")->default(true);
            $table->boolean("invoice_emails")->default(true);
            $table->boolean("support_emails")->default(true);
            $table->timestamps();

            $table->index("client_id");
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("contacts");
    }
};
