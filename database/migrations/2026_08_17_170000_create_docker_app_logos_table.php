<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Logos for the app catalogue, held here rather than on the panel.
 *
 * The panel's own logo_url points at other people's servers: of 98 apps only
 * 13 had a link that still resolved, 11 were dead, and the rest had none. We
 * cannot fix somebody else's CDN, and we should not be sending every customer's
 * browser there on page load either. So the billing side keeps its own image
 * per app slug, served from our storage, and falls back to a letter tile when
 * there is none.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('docker_app_logos', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 100)->unique();
            // Path on the public disk, e.g. docker-apps/wordpress.png
            $table->string('path', 255);
            // 'upload' (operator sent a file) or 'fetch' (pulled from a URL)
            $table->string('source', 20)->default('upload');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('docker_app_logos');
    }
};
