<?php

namespace App\Translation;

use RuntimeException;

/**
 * Change values in a lang/<locale>/<group>.php file in place.
 *
 * Regenerating the file with var_export() would drop its comments and blank
 * lines and rewrite every line of it - 132 of the 170 language files do not
 * survive that unchanged. Instead the file is read with PHP's own tokenizer,
 * each string value is located by its dotted path ('2fa.backup_codes' inside
 * a nested '2fa' array, or a flat 'activity_log.admin' key), and only that
 * token is replaced. Keys the file does not have yet are added before its
 * closing bracket.
 *
 * Some languages ship a group as a stub that returns the English file
 * (`return array_map(..., require __DIR__."/../en/admin.php")`). There is no
 * array to edit in a stub, so the English file is taken as the starting point
 * - same keys, same order - and the translations are written into that.
 *
 * The result is written only after it has been loaded back and every value
 * written compares equal to what was asked for.
 */
class LangFileWriter
{
    /**
     * @param  array<string, string>  $values  dotted key => text
     */
    public function write(string $target, string $template, array $values): void
    {
        $source = is_file($target) ? (string) file_get_contents($target) : '';
        if ($this->locate($source) === null) {
            $source = (string) file_get_contents($template);
        }

        $tokens = token_get_all($source);
        $located = $this->locate($source, $tokens);
        if ($located === null) {
            throw new RuntimeException("No array literal to edit in {$target}.");
        }
        [$positions, $closing] = $located;

        $missing = [];
        foreach ($values as $key => $value) {
            if (isset($positions[$key])) {
                foreach ($positions[$key] as $at) {
                    $tokens[$at] = [T_CONSTANT_ENCAPSED_STRING, $this->quote($value), 0];
                }
            } else {
                $missing[$key] = $value;
            }
        }

        $out = '';
        foreach ($tokens as $i => $token) {
            if ($i === $closing && $missing !== []) {
                $out = $this->ensureTrailingComma($out);
                foreach ($missing as $key => $value) {
                    $out .= '    '.$this->quote($key).' => '.$this->quote($value).",\n";
                }
            }
            $out .= is_array($token) ? $token[1] : $token;
        }

        $this->verify($target, $out, $values);
    }

    /**
     * Map every string value of the returned array to its token index.
     *
     * @param  list<mixed>|null  $tokens
     * @return array{0: array<string, list<int>>, 1: int}|null positions, index of the top-level closing bracket
     */
    private function locate(string $source, ?array $tokens = null): ?array
    {
        if ($source === '') {
            return null;
        }
        $tokens ??= token_get_all($source);

        $count = count($tokens);
        $i = 0;
        // The array that follows "return".
        while ($i < $count && ! (is_array($tokens[$i]) && $tokens[$i][0] === T_RETURN)) {
            $i++;
        }
        $i = $this->nextSignificant($tokens, $i + 1);
        if ($i === null || $tokens[$i] !== '[') {
            return null;
        }

        $positions = [];
        $path = [];
        $pendingKey = null;
        $depth = 0;

        for (; $i < $count; $i++) {
            $token = $tokens[$i];
            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            if ($token === '[') {
                $depth++;
                if ($depth > 1) {
                    $path[] = (string) $pendingKey;
                }
                $pendingKey = null;

                continue;
            }

            if ($token === ']') {
                $depth--;
                if ($depth === 0) {
                    return [$positions, $i];
                }
                array_pop($path);
                $pendingKey = null;

                continue;
            }

            if (is_array($token) && in_array($token[0], [T_CONSTANT_ENCAPSED_STRING, T_LNUMBER], true)) {
                $next = $this->nextSignificant($tokens, $i + 1);
                if ($next !== null && is_array($tokens[$next]) && $tokens[$next][0] === T_DOUBLE_ARROW) {
                    $pendingKey = $this->literal($token);
                    $i = $next;

                    continue;
                }
                // Only a value that is one plain literal: 'a'.'b' or a value
                // followed by anything but the end of the entry is left alone.
                $after = $next === null ? null : $tokens[$next];
                if ($pendingKey !== null && $token[0] === T_CONSTANT_ENCAPSED_STRING && ($after === ',' || $after === ']')) {
                    // A key can be in a file both flat and nested; Laravel
                    // shows one, the editor reads the other. Every place it
                    // stands is written, so both agree.
                    $positions[implode('.', [...$path, $pendingKey])][] = $i;
                }
                $pendingKey = null;

                continue;
            }

            if ($token === ',') {
                $pendingKey = null;
            }
        }

        return null;
    }

    private function nextSignificant(array $tokens, int $from): ?int
    {
        for ($i = $from, $n = count($tokens); $i < $n; $i++) {
            $t = $tokens[$i];
            if (is_array($t) && in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            return $i;
        }

        return null;
    }

    /** The value of a string or integer literal token. */
    private function literal(array $token): string
    {
        if ($token[0] === T_LNUMBER) {
            return $token[1];
        }

        $raw = $token[1];
        $body = substr($raw, 1, -1);

        if ($raw[0] === "'") {
            return strtr($body, ['\\\\' => '\\', "\\'" => "'"]);
        }

        return stripcslashes($body);
    }

    /**
     * Single quotes, as the files are written; a text with line breaks goes
     * in double quotes with \n, as the files write those.
     */
    private function quote(string $value): string
    {
        if (preg_match('/[\r\n\t]/', $value)) {
            return '"'.strtr($value, ['\\' => '\\\\', '"' => '\\"', '$' => '\\$', "\n" => '\\n', "\r" => '\\r', "\t" => '\\t']).'"';
        }

        return "'".strtr($value, ['\\' => '\\\\', "'" => "\\'"])."'";
    }

    /** The text before the closing bracket ends in a comma and a newline. */
    private function ensureTrailingComma(string $out): string
    {
        $trimmed = rtrim($out);
        if (! str_ends_with($trimmed, ',') && ! str_ends_with($trimmed, '[')) {
            $trimmed .= ',';
        }

        return $trimmed."\n";
    }

    /**
     * @param  array<string, string>  $values
     */
    private function verify(string $target, string $contents, array $values): void
    {
        $tmp = $target.'.tmp-'.bin2hex(random_bytes(4));
        file_put_contents($tmp, $contents);

        try {
            $loaded = (function (string $file) {
                return require $file;
            })($tmp);

            if (! is_array($loaded)) {
                throw new RuntimeException("{$target} would not return an array.");
            }

            $flat = $this->flatten($loaded);
            foreach ($values as $key => $value) {
                if (($flat[$key] ?? null) !== $value) {
                    throw new RuntimeException("{$target}: {$key} did not read back as written.");
                }
            }
        } catch (\Throwable $e) {
            @unlink($tmp);

            throw $e instanceof RuntimeException ? $e : new RuntimeException("{$target}: {$e->getMessage()}", 0, $e);
        }

        if (! rename($tmp, $target)) {
            @unlink($tmp);

            throw new RuntimeException("Could not write {$target}.");
        }
    }

    /**
     * Dotted keys as OfficialTranslationRepository reads them.
     *
     * @return array<string, string>
     */
    private function flatten(array $values, string $prefix = ''): array
    {
        $result = [];
        foreach ($values as $key => $value) {
            $key = $prefix === '' ? (string) $key : $prefix.'.'.$key;
            if (is_array($value)) {
                foreach ($this->flatten($value, $key) as $nestedKey => $nestedValue) {
                    $result[$nestedKey] = $nestedValue;
                }
            } elseif (is_string($value)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
