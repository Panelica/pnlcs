<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create("ticket_departments", function (Blueprint $table) {
            $table->id();
            $table->string("name");
            $table->text("description")->nullable();
            $table->string("email")->nullable();
            $table->boolean("clients_only")->default(false);
            $table->boolean("hidden")->default(false);
            $table->integer("sort_order")->default(0);
            $table->boolean("feedback_request")->default(false);
            $table->timestamps();
        });

        Schema::create("ticket_statuses", function (Blueprint $table) {
            $table->id();
            $table->string("title");
            $table->string("color", 7)->default("#3b82f6");
            $table->integer("sort_order")->default(0);
            $table->boolean("show_active")->default(true);
            $table->boolean("show_awaiting")->default(false);
            $table->integer("auto_close")->default(0);
            $table->timestamps();
        });

        Schema::create("tickets", function (Blueprint $table) {
            $table->id();
            $table->string("tid")->unique(); // ticket number
            $table->foreignId("department_id")->constrained("ticket_departments");
            $table->unsignedBigInteger("client_id")->nullable();
            $table->unsignedBigInteger("contact_id")->nullable();
            $table->string("name")->nullable();
            $table->string("email");
            $table->string("cc")->nullable();
            $table->string("title");
            $table->text("message");
            $table->string("status")->default("open");
            $table->string("priority")->default("medium"); // low, medium, high, critical
            $table->string("admin")->nullable(); // assigned admin
            $table->string("attachment")->nullable();
            $table->timestamp("last_reply")->nullable();
            $table->unsignedBigInteger("flag")->nullable(); // flagged admin id
            $table->string("service")->nullable();
            $table->unsignedBigInteger("merged_ticket_id")->nullable();
            $table->string("editor")->default("plain"); // plain, markdown
            $table->timestamps();
            $table->index("status");
            $table->index("department_id");
        });

        Schema::create("ticket_replies", function (Blueprint $table) {
            $table->id();
            $table->foreignId("ticket_id")->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger("client_id")->nullable();
            $table->unsignedBigInteger("contact_id")->nullable();
            $table->text("message");
            $table->string("admin")->nullable();
            $table->string("attachment")->nullable();
            $table->integer("rating")->nullable();
            $table->string("editor")->default("plain");
            $table->timestamps();
        });

        Schema::create("ticket_notes", function (Blueprint $table) {
            $table->id();
            $table->foreignId("ticket_id")->constrained()->cascadeOnDelete();
            $table->string("admin");
            $table->text("message");
            $table->string("attachments")->nullable();
            $table->string("editor")->default("plain");
            $table->timestamps();
        });

        Schema::create("ticket_predefined_categories", function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger("parent_id")->nullable();
            $table->string("name");
            $table->timestamps();
        });

        Schema::create("ticket_predefined_replies", function (Blueprint $table) {
            $table->id();
            $table->foreignId("category_id")->constrained("ticket_predefined_categories")->cascadeOnDelete();
            $table->string("name");
            $table->text("reply");
            $table->timestamps();
        });

        Schema::create("ticket_tags", function (Blueprint $table) {
            $table->id();
            $table->foreignId("ticket_id")->constrained()->cascadeOnDelete();
            $table->string("tag");
            $table->timestamps();
        });

        // Knowledge Base
        Schema::create("kb_categories", function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger("parent_id")->nullable();
            $table->string("name");
            $table->text("description")->nullable();
            $table->boolean("hidden")->default(false);
            $table->integer("sort_order")->default(0);
            $table->timestamps();
        });

        Schema::create("kb_articles", function (Blueprint $table) {
            $table->id();
            $table->foreignId("category_id")->constrained("kb_categories")->cascadeOnDelete();
            $table->string("title");
            $table->text("article");
            $table->integer("views")->default(0);
            $table->integer("useful")->default(0);
            $table->integer("votes")->default(0);
            $table->boolean("private")->default(false);
            $table->integer("sort_order")->default(0);
            $table->timestamps();
        });

        // Announcements
        Schema::create("announcements", function (Blueprint $table) {
            $table->id();
            $table->string("title");
            $table->text("announcement");
            $table->boolean("published")->default(true);
            $table->timestamps();
        });

        // Email Templates
        Schema::create("email_templates", function (Blueprint $table) {
            $table->id();
            $table->string("type"); // general, product, domain, invoice, support, affiliate
            $table->string("name")->unique();
            $table->string("subject");
            $table->text("message");
            $table->string("from_name")->nullable();
            $table->string("from_email")->nullable();
            $table->boolean("disabled")->default(false);
            $table->boolean("custom")->default(false);
            $table->string("language")->default("en");
            $table->string("copy_to")->nullable();
            $table->boolean("plaintext")->default(false);
            $table->timestamps();
        });

        // Activity Log
        Schema::create("activity_logs", function (Blueprint $table) {
            $table->id();
            $table->timestamp("date");
            $table->text("description");
            $table->string("user")->nullable();
            $table->string("ip_address", 45)->nullable();
            $table->timestamps();
            $table->index("date");
        });
    }
    public function down(): void {
        Schema::dropIfExists("activity_logs");
        Schema::dropIfExists("email_templates");
        Schema::dropIfExists("announcements");
        Schema::dropIfExists("kb_articles");
        Schema::dropIfExists("kb_categories");
        Schema::dropIfExists("ticket_tags");
        Schema::dropIfExists("ticket_predefined_replies");
        Schema::dropIfExists("ticket_predefined_categories");
        Schema::dropIfExists("ticket_notes");
        Schema::dropIfExists("ticket_replies");
        Schema::dropIfExists("tickets");
        Schema::dropIfExists("ticket_statuses");
        Schema::dropIfExists("ticket_departments");
    }
};
