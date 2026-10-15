<?php

use App\Support\FirstRunEnvironment;

/**
 * The release package unpacked on a web host, no command run: there is no
 * .env, so no application key, and the install wizard's first page answered
 * 500 instead of opening. The first web request now writes the .env the
 * wizard goes on to fill in; nothing else is ever touched.
 */
function firstRunDir(?string $env = null, bool $installed = false): string
{
    $dir = storage_path('framework/testing/first-run-'.bin2hex(random_bytes(6)));
    mkdir($dir.'/storage', 0777, true);
    file_put_contents($dir.'/.env.example', "APP_NAME=PNLCS\nAPP_KEY=\nDB_CONNECTION=mysql\nDB_PASSWORD=\n");
    if ($env !== null) {
        file_put_contents($dir.'/.env', $env);
    }
    if ($installed) {
        file_put_contents($dir.'/storage/installed.lock', date('c'));
    }

    return $dir;
}

/**
 * Runs $test as in a web request whose environment carries no APP_KEY: the
 * test process itself has one (the application under test is booted).
 */
function withoutEnvironmentKey(Closure $test): void
{
    $before = [getenv('APP_KEY'), $_ENV['APP_KEY'] ?? null, $_SERVER['APP_KEY'] ?? null];
    putenv('APP_KEY');
    unset($_ENV['APP_KEY'], $_SERVER['APP_KEY']);

    try {
        $test();
    } finally {
        putenv($before[0] === false ? 'APP_KEY' : 'APP_KEY='.$before[0]);
        if ($before[1] !== null) {
            $_ENV['APP_KEY'] = $before[1];
        }
        if ($before[2] !== null) {
            $_SERVER['APP_KEY'] = $before[2];
        }
    }
}

afterEach(function () {
    foreach (glob(storage_path('framework/testing/first-run-*')) ?: [] as $dir) {
        @chmod($dir, 0777);
        foreach (['/.env', '/.env.example', '/storage/installed.lock', '/bootstrap/cache/config.php'] as $file) {
            @unlink($dir.$file);
        }
        @rmdir($dir.'/storage');
        @rmdir($dir.'/bootstrap/cache');
        @rmdir($dir.'/bootstrap');
        @rmdir($dir);
    }
});

test('an uploaded copy gets a .env with a key on its first web request', function () {
    $dir = firstRunDir();

    withoutEnvironmentKey(fn () => FirstRunEnvironment::prepare($dir, 'fpm-fcgi'));

    $env = (string) file_get_contents($dir.'/.env');
    expect($env)->toMatch('/^APP_KEY=base64:[A-Za-z0-9+\/]{43}=$/m')
        ->and($env)->toContain("APP_NAME=PNLCS\n")
        ->and($env)->toContain("DB_CONNECTION=mysql\n")
        ->and(substr(sprintf('%o', fileperms($dir.'/.env')), -4))->toBe('0600');
});

test('a .env copied by hand without a key gets one and keeps everything else', function () {
    foreach (["APP_KEY=\n", "APP_KEY=\"\"\n", "APP_KEY=''\n", "# no key line\n"] as $keyLine) {
        $dir = firstRunDir("APP_NAME=\"My Host\"\n{$keyLine}DB_PASSWORD=\"s3cret#1\"\n");

        withoutEnvironmentKey(fn () => FirstRunEnvironment::prepare($dir, 'apache2handler'));

        $env = (string) file_get_contents($dir.'/.env');
        expect($env)->toMatch('/^APP_KEY=base64:[A-Za-z0-9+\/]{43}=$/m')
            ->and($env)->toContain("APP_NAME=\"My Host\"\n")
            ->and($env)->toContain("DB_PASSWORD=\"s3cret#1\"\n")
            ->and(substr_count($env, 'APP_KEY='))->toBe(1);
    }
});

test('a .env that has a key is never changed', function () {
    $env = 'APP_KEY=base64:'.base64_encode(str_repeat('k', 32))."\nDB_PASSWORD=x\n";
    $dir = firstRunDir($env);

    withoutEnvironmentKey(fn () => FirstRunEnvironment::prepare($dir, 'fpm-fcgi'));

    expect(file_get_contents($dir.'/.env'))->toBe($env);
});

test('an installed copy is never changed, even with no key', function () {
    $dir = firstRunDir("APP_KEY=\n", installed: true);

    withoutEnvironmentKey(fn () => FirstRunEnvironment::prepare($dir, 'fpm-fcgi'));

    expect(file_get_contents($dir.'/.env'))->toBe("APP_KEY=\n");

    $bare = firstRunDir(installed: true);
    withoutEnvironmentKey(fn () => FirstRunEnvironment::prepare($bare, 'fpm-fcgi'));
    expect(file_exists($bare.'/.env'))->toBeFalse();
});

test('a key in the real environment means the copy already runs: nothing is written', function () {
    // An installation without the lock (an old one, a restored database)
    // whose key comes from the server's environment. A second key written to
    // .env would take over the day that variable is gone, and nothing
    // encrypted with the first could be read again.
    $dir = firstRunDir("APP_KEY=\n");
    $bare = firstRunDir();
    $before = [getenv('APP_KEY'), $_SERVER['APP_KEY'] ?? null];
    putenv('APP_KEY=base64:'.base64_encode(str_repeat('e', 32)));

    try {
        FirstRunEnvironment::prepare($dir, 'fpm-fcgi');
        FirstRunEnvironment::prepare($bare, 'fpm-fcgi');
    } finally {
        putenv($before[0] === false ? 'APP_KEY' : 'APP_KEY='.$before[0]);
    }

    expect(file_get_contents($dir.'/.env'))->toBe("APP_KEY=\n")
        ->and(file_exists($bare.'/.env'))->toBeFalse();

    withoutEnvironmentKey(function () use ($dir) {
        $_SERVER['APP_KEY'] = 'base64:'.base64_encode(str_repeat('s', 32));
        FirstRunEnvironment::prepare($dir, 'fpm-fcgi');
    });
    expect(file_get_contents($dir.'/.env'))->toBe("APP_KEY=\n");
});

test('a cached configuration means the copy already runs: nothing is written', function () {
    // The key is in bootstrap/cache/config.php; a new one in .env would take
    // over at the next config:clear. Release packages ship without that file.
    $dir = firstRunDir("APP_KEY=\n");
    $bare = firstRunDir();
    foreach ([$dir, $bare] as $copy) {
        mkdir($copy.'/bootstrap/cache', 0777, true);
        file_put_contents($copy.'/bootstrap/cache/config.php', "<?php return [];\n");
    }

    withoutEnvironmentKey(function () use ($dir, $bare) {
        FirstRunEnvironment::prepare($dir, 'fpm-fcgi');
        FirstRunEnvironment::prepare($bare, 'fpm-fcgi');
    });

    expect(file_get_contents($dir.'/.env'))->toBe("APP_KEY=\n")
        ->and(file_exists($bare.'/.env'))->toBeFalse();
});

test('the command line is left alone', function () {
    $dir = firstRunDir();

    withoutEnvironmentKey(fn () => FirstRunEnvironment::prepare($dir, 'cli'));

    expect(file_exists($dir.'/.env'))->toBeFalse();
});

test('a folder it cannot write to gets a key for this request only', function () {
    if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
        $this->markTestSkipped('root writes to a read-only folder anyway');
    }
    $dir = firstRunDir();
    chmod($dir, 0555);
    $before = [getenv('APP_KEY'), $_ENV['APP_KEY'] ?? null, $_SERVER['APP_KEY'] ?? null];

    try {
        putenv('APP_KEY');
        unset($_ENV['APP_KEY'], $_SERVER['APP_KEY']);
        FirstRunEnvironment::prepare($dir, 'fpm-fcgi');

        expect(file_exists($dir.'/.env'))->toBeFalse()
            ->and((string) getenv('APP_KEY'))->toStartWith('base64:')
            ->and((string) ($_ENV['APP_KEY'] ?? ''))->toStartWith('base64:');
    } finally {
        putenv($before[0] === false ? 'APP_KEY' : 'APP_KEY='.$before[0]);
        if ($before[1] === null) {
            unset($_ENV['APP_KEY']);
        } else {
            $_ENV['APP_KEY'] = $before[1];
        }
        if ($before[2] === null) {
            unset($_SERVER['APP_KEY']);
        } else {
            $_SERVER['APP_KEY'] = $before[2];
        }
    }
});
