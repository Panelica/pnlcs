<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The catalogue row grows from "an image" into how an app is sold.
 *
 * Selling 98 apps as 98 products does not scale, so a customer picks the app
 * while ordering one product. That needs the operator to say which apps are on
 * offer, which are pushed to the front, and what each one says on the card -
 * none of which belongs on the panel, because it is commercial, not technical.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('docker_app_logos', 'docker_apps');

        Schema::table('docker_apps', function (Blueprint $table) {
            // The image is now optional: a row can exist purely to say an app
            // is featured, or to carry its selling line.
            $table->string('path', 255)->nullable()->change();

            // Offered to customers. Default true so enabling the catalogue does
            // not require touching ninety-eight rows first.
            $table->boolean('is_sellable')->default(true)->after('source');
            $table->boolean('is_featured')->default(false)->after('is_sellable');
            $table->unsignedInteger('sort_order')->default(0)->after('is_featured');
            // One selling line, shown under the name. Empty falls back to the
            // panel's own description.
            $table->string('tagline', 160)->nullable()->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('docker_apps', function (Blueprint $table) {
            $table->dropColumn(['is_sellable', 'is_featured', 'sort_order', 'tagline']);
        });
        Schema::rename('docker_apps', 'docker_app_logos');
    }
};
