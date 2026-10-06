<?php

namespace App\Services\Updates;

use App\Support\SqlDump;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * The database as it was right before an update changed it. Taken with the
 * site in maintenance and the scheduler stopped, so restoring it loses no
 * order, payment or ticket.
 */
class DatabaseSnapshot
{
    public function take(string $file): void
    {
        $tables = SqlDump::tables();

        if (! SqlDump::dump($file, $tables) || ! SqlDump::isComplete($file)) {
            throw new RuntimeException('The database snapshot could not be written.');
        }

        file_put_contents("{$file}.tables.json", json_encode($tables));
    }

    /**
     * Puts the database back: tables a migration created since are dropped
     * (or the next update would find them in the way), then every table of
     * the snapshot is rebuilt with its rows.
     */
    public function restore(string $file): void
    {
        $tables = json_decode((string) @file_get_contents("{$file}.tables.json"), true);
        if (! is_array($tables) || ! SqlDump::isComplete($file)) {
            throw new RuntimeException("{$file} is not a complete snapshot.");
        }

        Schema::disableForeignKeyConstraints();
        try {
            foreach (array_diff(SqlDump::tables(), $tables) as $added) {
                DB::statement('DROP TABLE `'.str_replace('`', '``', $added).'`');
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        SqlDump::restore($file);
    }
}
