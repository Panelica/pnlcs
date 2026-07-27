<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Ticket statuses were written in mixed case ('open' by the client ticket
     * form vs the 'Open'/'Closed' convention used everywhere else), so exact
     * status filters silently missed the lowercase rows. Normalize the data;
     * the lowercase writer has been fixed alongside this migration.
     */
    public function up(): void
    {
        DB::statement("UPDATE tickets SET status = 'Open' WHERE BINARY status = 'open'");
        DB::statement("UPDATE tickets SET status = 'Closed' WHERE BINARY status = 'closed'");
    }

    public function down(): void
    {
        // One-way data normalization — nothing sensible to restore.
    }
};
