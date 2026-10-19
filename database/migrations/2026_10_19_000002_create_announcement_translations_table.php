<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Announcements in more than one language.
 *
 * An announcement held one title and one text, so a shop open in two
 * languages told every customer about a maintenance window in the same one.
 * Its own text stays the default language's; a translation row holds
 * another language's.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Can run twice without harm (RELEASING.md).
        if (Schema::hasTable('announcement_translations')) {
            return;
        }

        Schema::create('announcement_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained('announcements')->cascadeOnDelete();
            $table->string('language', 10);
            $table->string('title');
            $table->text('announcement');
            $table->timestamps();
            $table->unique(['announcement_id', 'language']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_translations');
    }
};
