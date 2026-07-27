<?php

use App\Models\Service;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Server modules stored their remote identifiers (cpanel_username,
 * panelica_user_id, …) as JSON inside services.notes — the same field the
 * order flow and the customer use for human notes. The first module action
 * therefore replaced the customer's note with JSON, and a human note made the
 * module data unreadable. Module data gets its own column.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('services', 'module_data')) {
            Schema::table('services', function (Blueprint $table) {
                $table->json('module_data')->nullable()->after('notes');
            });
        }

        // Move any legacy JSON payload out of notes.
        Service::whereNotNull('notes')->chunkById(200, function ($services) {
            foreach ($services as $service) {
                $decoded = json_decode((string) $service->getRawOriginal('notes'), true);
                if (is_array($decoded)) {
                    $service->forceFill(['module_data' => $decoded, 'notes' => null])->saveQuietly();
                }
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('services', 'module_data')) {
            Schema::table('services', function (Blueprint $table) {
                $table->dropColumn('module_data');
            });
        }
    }
};
