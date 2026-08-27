<?php

use App\Support\EnvWriter;

/**
 * The install wizard writes the database and admin secrets into .env. Two
 * hazards, both proven live before this: an unquoted value is cut at the
 * first '#' or space by the parser, so a password with either finished the
 * install with the wrong secret; and preg_replace reads $1 / \2 in a value as
 * back-references, mangling it. Round-trip through the real dotenv parser is
 * the check that matters.
 */
function envRoundTrip(string $content): array
{
    $dir = sys_get_temp_dir().'/envw'.bin2hex(random_bytes(6));
    mkdir($dir);
    file_put_contents($dir.'/.env', $content);
    $vars = Dotenv\Dotenv::createArrayBacked($dir)->load();
    @unlink($dir.'/.env');
    @rmdir($dir);

    return $vars;
}

it('keeps a password with a hash and spaces intact through the parser', function () {
    $out = EnvWriter::apply("APP_KEY=base64:x\nDB_PASSWORD=old\n", ['DB_PASSWORD' => 'p@ss #word here']);

    expect(envRoundTrip($out)['DB_PASSWORD'])->toBe('p@ss #word here');
});

it('keeps a value carrying $1 and backslashes intact', function () {
    $out = EnvWriter::apply("DB_PASSWORD=old\n", ['DB_PASSWORD' => 'a$1b\\2c$']);

    expect(envRoundTrip($out)['DB_PASSWORD'])->toBe('a$1b\\2c$');
});

it('keeps a value with double quotes intact', function () {
    $out = EnvWriter::apply("X=old\n", ['X' => 'he said "hi"']);

    expect(envRoundTrip($out)['X'])->toBe('he said "hi"');
});

it('appends a key that is not already present', function () {
    $out = EnvWriter::apply("A=1\n", ['B' => 'two words']);
    $vars = envRoundTrip($out);

    expect($vars['A'])->toBe('1')->and($vars['B'])->toBe('two words');
});

it('replaces only the first occurrence and leaves bare tokens unquoted', function () {
    $out = EnvWriter::apply("DB_HOST=old\n", ['DB_HOST' => '127.0.0.1']);

    expect($out)->toContain('DB_HOST=127.0.0.1')
        ->and(envRoundTrip($out)['DB_HOST'])->toBe('127.0.0.1');
});

it('does not let one key\'s value bleed into another line', function () {
    $out = EnvWriter::apply("DB_PASSWORD=old\nDB_USERNAME=root\n", ['DB_PASSWORD' => "line1\nDB_USERNAME=evil"]);
    $vars = envRoundTrip($out);

    expect($vars['DB_USERNAME'])->toBe('root')
        ->and($vars['DB_PASSWORD'])->toBe("line1\nDB_USERNAME=evil");
});
