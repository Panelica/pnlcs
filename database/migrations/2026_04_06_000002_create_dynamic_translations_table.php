<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('dynamic_translations')) {
            // Table already exists, add missing columns and index
            Schema::table('dynamic_translations', function (Blueprint $table) {
                if (!Schema::hasColumn('dynamic_translations', 'is_auto_translated')) {
                    $table->boolean('is_auto_translated')->default(false)->after('value');
                }
                if (!Schema::hasColumn('dynamic_translations', 'is_reviewed')) {
                    $table->boolean('is_reviewed')->default(false)->after('is_auto_translated');
                }
            });

            // Add unique index if not exists
            $indexes = collect(
                \Illuminate\Support\Facades\DB::select("SHOW INDEX FROM dynamic_translations WHERE Key_name = 'idx_lang_group_key'")
            );
            if ($indexes->isEmpty()) {
                Schema::table('dynamic_translations', function (Blueprint $table) {
                    $table->unique(['language', 'group', 'key'], 'idx_lang_group_key');
                });
            }
        } else {
            Schema::create('dynamic_translations', function (Blueprint $table) {
                $table->id();
                $table->string('language', 10)->index();
                $table->string('group', 50)->index();
                $table->string('key', 255);
                $table->text('value')->nullable();
                $table->boolean('is_auto_translated')->default(false);
                $table->boolean('is_reviewed')->default(false);
                $table->timestamps();

                $table->unique(['language', 'group', 'key'], 'idx_lang_group_key');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dynamic_translations');
    }
};
