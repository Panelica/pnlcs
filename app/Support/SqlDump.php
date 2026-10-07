<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PDO;
use RuntimeException;

/**
 * A dump of the database written and read back by PHP itself, over the
 * connection the application already has: schema and rows, INSERTs in
 * batches, gzip-compressed. Needs no client tools, so it works in the billing
 * container and in a hosting account alike.
 */
class SqlDump
{
    /** An INSERT is cut at about this size, well under any max_allowed_packet. */
    private const BATCH_BYTES = 1048576;

    /** The tables a dump holds (views are left out: they hold no data of their own). */
    public static function tables(): array
    {
        $pdo = DB::connection()->getPdo();

        return array_map('current', $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_NUM));
    }

    /** @param array<int, string>|null $tables only these tables (all of them when null) */
    public static function dump(string $file, ?array $tables = null): bool
    {
        $gz = gzopen($file, 'wb6');
        if ($gz === false) {
            return false;
        }

        try {
            $pdo = DB::connection()->getPdo();
            gzwrite($gz, "SET FOREIGN_KEY_CHECKS=0;\nSET NAMES utf8mb4;\n\n");

            foreach ($tables ?? self::tables() as $table) {
                $qt = '`'.str_replace('`', '``', $table).'`';
                $create = $pdo->query("SHOW CREATE TABLE {$qt}")->fetch(PDO::FETCH_NUM)[1];
                gzwrite($gz, "DROP TABLE IF EXISTS {$qt};\n{$create};\n\n");

                // Rows are streamed, not buffered: a table with millions of rows
                // would otherwise be held in memory whole. Statements are cut by
                // size, so a batch of large rows stays under the server's
                // max_allowed_packet (16 MB by default on MariaDB).
                $buffered = $pdo->getAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY);
                $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
                try {
                    $stmt = $pdo->query("SELECT * FROM {$qt}");
                    $batch = [];
                    $bytes = 0;
                    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
                        $vals = array_map(fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), $row);
                        $tuple = '('.implode(',', $vals).')';
                        $batch[] = $tuple;
                        $bytes += strlen($tuple);
                        if (count($batch) >= 500 || $bytes >= self::BATCH_BYTES) {
                            gzwrite($gz, "INSERT INTO {$qt} VALUES\n".implode(",\n", $batch).";\n");
                            $batch = [];
                            $bytes = 0;
                        }
                    }
                    $stmt->closeCursor();
                    if ($batch) {
                        gzwrite($gz, "INSERT INTO {$qt} VALUES\n".implode(",\n", $batch).";\n");
                    }
                } finally {
                    $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, $buffered);
                }
                gzwrite($gz, "\n");
            }

            // The same trailer mysqldump writes. A dump cut off by a full disk
            // or a killed process is otherwise indistinguishable from a whole
            // one, and this line is what a restore checks for first.
            gzwrite($gz, "SET FOREIGN_KEY_CHECKS=1;\n\n-- Dump completed on ".now()->format('Y-m-d H:i:s')."\n");
        } catch (\Throwable $e) {
            gzclose($gz);
            @unlink($file);
            Log::error('PHP dump failed', ['error' => $e->getMessage()]);

            return false;
        }

        gzclose($gz);

        // A whole database is never this small; a few chosen tables can be.
        return is_file($file) && ($tables !== null || filesize($file) > 512);
    }

    /**
     * Whether a dump ends with its trailer: a dump cut off by a full disk or
     * a killed process does not, and must never be restored.
     */
    public static function isComplete(string $file): bool
    {
        $gz = @gzopen($file, 'rb');
        if ($gz === false) {
            return false;
        }

        $tail = '';
        while (! gzeof($gz)) {
            $tail = substr($tail.gzread($gz, 1 << 16), -256);
        }
        gzclose($gz);

        return str_contains($tail, '-- Dump completed on ');
    }

    /** Runs every statement of a dump written by dump(). */
    public static function restore(string $file): void
    {
        if (! self::isComplete($file)) {
            throw new RuntimeException("{$file} is not a complete dump.");
        }

        $gz = gzopen($file, 'rb');
        $connection = DB::connection();

        try {
            foreach (self::statements($gz) as $statement) {
                $connection->unprepared($statement);
            }
        } finally {
            gzclose($gz);
            $connection->unprepared('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    /**
     * Splits SQL into statements at the semicolons outside quoted strings and
     * identifiers. Values written by PDO::quote() escape with backslashes, so
     * a semicolon inside a customer's ticket text does not end a statement.
     *
     * @param  resource  $gz
     * @return \Generator<int, string>
     */
    public static function statements($gz): \Generator
    {
        $buffer = '';
        $quote = null;
        $escaped = false;
        $lineStart = true;
        $comment = false;

        while (! gzeof($gz)) {
            $chunk = gzread($gz, 1 << 16);
            $length = strlen($chunk);

            for ($i = 0; $i < $length; $i++) {
                $c = $chunk[$i];

                if ($comment) {
                    if ($c === "\n") {
                        $comment = false;
                        $lineStart = true;
                    }

                    continue;
                }

                if ($quote !== null) {
                    $buffer .= $c;
                    if ($escaped) {
                        $escaped = false;
                    } elseif ($c === '\\' && $quote !== '`') {
                        $escaped = true;
                    } elseif ($c === $quote) {
                        $quote = null;
                    }

                    continue;
                }

                if ($lineStart && $c === '-' && ($chunk[$i + 1] ?? '') === '-') {
                    $comment = true;

                    continue;
                }

                $lineStart = $c === "\n";

                if ($c === "'" || $c === '"' || $c === '`') {
                    $quote = $c;
                    $buffer .= $c;

                    continue;
                }

                if ($c === ';') {
                    $statement = trim($buffer);
                    $buffer = '';
                    if ($statement !== '') {
                        yield $statement;
                    }

                    continue;
                }

                $buffer .= $c;
            }
        }

        if (trim($buffer) !== '') {
            yield trim($buffer);
        }
    }
}
