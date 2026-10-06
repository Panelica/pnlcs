<?php

use App\Support\SqlDump;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The database snapshot an update takes before it changes anything, and
 * restores when it has to go back. Customers write anything into a ticket or
 * a note; none of it may break a restore.
 */
function sqlDumpStatements(string $sql): array
{
    $file = storage_path('framework/testing/split-'.bin2hex(random_bytes(4)).'.sql.gz');
    @mkdir(dirname($file), 0777, true);
    file_put_contents($file, gzencode($sql));
    $gz = gzopen($file, 'rb');
    $out = iterator_to_array(SqlDump::statements($gz), false);
    gzclose($gz);
    unlink($file);

    return $out;
}

test('statements are split at semicolons outside quotes only', function () {
    $sql = "SET A=1;\nINSERT INTO `t` VALUES ('a;b','it\\'s; fine','line\\none'),('-- not a comment','x\"y;');\n-- Dump completed on 2026-10-07\n";

    expect(sqlDumpStatements($sql))->toBe([
        'SET A=1',
        "INSERT INTO `t` VALUES ('a;b','it\\'s; fine','line\\none'),('-- not a comment','x\"y;')",
    ]);
});

test('a table with awkward content comes back exactly as it was', function () {
    $table = 'sqldump_probe_'.bin2hex(random_bytes(4));
    Schema::create($table, function ($t) {
        $t->id();
        $t->text('body')->nullable();
        $t->decimal('amount', 10, 2)->nullable();
    });

    $rows = [
        ['body' => "semi; colon\nnew line -- dashes", 'amount' => '12.50'],
        ['body' => "quote ' and \" and back\\slash", 'amount' => null],
        ['body' => null, 'amount' => '0.00'],
        ['body' => "unicode ğüşİ 漢字 ✓\0nul", 'amount' => '-3.10'],
    ];
    DB::table($table)->insert($rows);
    $before = DB::table($table)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();

    $file = storage_path('framework/testing/'.$table.'.sql.gz');
    expect(SqlDump::dump($file, [$table]))->toBeTrue()
        ->and(SqlDump::isComplete($file))->toBeTrue();

    DB::table($table)->delete();
    SqlDump::restore($file);

    expect(DB::table($table)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all())->toBe($before);

    Schema::drop($table);
    unlink($file);
});

test('a dump cut off before its end is never restored', function () {
    $file = storage_path('framework/testing/cut-'.bin2hex(random_bytes(4)).'.sql.gz');
    file_put_contents($file, gzencode("SET FOREIGN_KEY_CHECKS=0;\nDROP TABLE IF EXISTS `users`;\n"));

    expect(SqlDump::isComplete($file))->toBeFalse()
        ->and(fn () => SqlDump::restore($file))->toThrow(RuntimeException::class);

    unlink($file);
});
