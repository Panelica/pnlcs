<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable("product_bundles")) {
            Schema::create("product_bundles", function (Blueprint $table) {
                $table->id();
                $table->string("name");
                $table->text("description")->nullable();
                $table->enum("discount_type", ["percentage", "fixed"])->default("percentage");
                $table->decimal("discount_value", 15, 2)->default(0);
                $table->boolean("is_active")->default(true);
                $table->timestamps();
            });
        }

        // Ensure bundle_items has proper foreign key
        if (Schema::hasTable("bundle_items") && !Schema::hasColumn("bundle_items", "product_id")) {
            // bundle_items uses item_type + item_id polymorphic, no change needed
        }
    }

    public function down(): void
    {
        Schema::dropIfExists("product_bundles");
    }
};
