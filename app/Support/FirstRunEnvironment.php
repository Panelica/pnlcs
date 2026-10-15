<?php

namespace App\Support;

/**
 * A copy of PNLCS that was only uploaded - the release package unpacked on a
 * web host, no command run - has no .env. Without one there is no application
 * key, and the install wizard's first page answered 500 ("No application
 * encryption key has been specified") instead of asking for the database.
 *
 * Before the framework reads its environment, a web request to a copy that is
 * not installed yet gets a .env made from .env.example with a new key, or a key
 * written into a .env that has none. Nothing is touched once the installation
 * is finished (storage/installed.lock), on the command line, or when the file
 * already has a key. When the folder is not writable the key is set for this
 * request only, so the requirements page can say what to fix.
 *
 * A copy whose key the framework finds elsewhere - in the server's environment,
 * or in a cached configuration - already runs, lock or no lock (an older
 * installation, a restored database): it is left alone. A second key written
 * into its .env would take over the day that variable or cache is gone, and
 * nothing encrypted with the first could be read again.
 */
final class FirstRunEnvironment
{
    public static function prepare(string $basePath, string $sapi = PHP_SAPI): void
    {
        if ($sapi === 'cli' || $sapi === 'phpdbg' || is_file($basePath.'/storage/installed.lock')) {
            return;
        }
        if (self::keyInEnvironment() || is_file($basePath.'/bootstrap/cache/config.php')) {
            return;
        }

        $env = $basePath.'/.env';
        $exists = is_file($env);
        $content = $exists ? (string) @file_get_contents($env) : (string) @file_get_contents($basePath.'/.env.example');

        if ($content === '' || self::hasKey($content)) {
            return;
        }

        $key = 'base64:'.base64_encode(random_bytes(32));
        $content = preg_match('/^APP_KEY=.*$/m', $content)
            ? (string) preg_replace_callback('/^APP_KEY=.*$/m', fn () => 'APP_KEY='.$key, $content, 1)
            : rtrim($content)."\nAPP_KEY=".$key."\n";

        if (self::write($env, $content, $exists)) {
            return;
        }

        $_ENV['APP_KEY'] = $_SERVER['APP_KEY'] = $key;
        putenv('APP_KEY='.$key);
    }

    private static function hasKey(string $content): bool
    {
        return (bool) preg_match('/^APP_KEY=["\']?[^\s"\'#]/m', $content);
    }

    /** Set by the web server or PHP-FPM; .env has not been read yet at this point. */
    private static function keyInEnvironment(): bool
    {
        foreach ([getenv('APP_KEY'), $_SERVER['APP_KEY'] ?? null, $_ENV['APP_KEY'] ?? null] as $value) {
            if (is_string($value) && trim($value) !== '') {
                return true;
            }
        }

        return false;
    }

    private static function write(string $env, string $content, bool $exists): bool
    {
        if ($exists) {
            return is_writable($env) && @file_put_contents($env, $content, LOCK_EX) !== false;
        }

        // 'x' creates the file only if it is not there: two first requests at
        // the same moment do not overwrite each other's key.
        $handle = @fopen($env, 'x');
        if ($handle === false) {
            return is_file($env);
        }
        fwrite($handle, $content);
        fclose($handle);
        // It is about to hold the database password.
        @chmod($env, 0600);

        return true;
    }
}
