<?php

namespace App\Support;

/**
 * Set keys in a .env file's text. Two hazards the install wizard hit, both
 * proven by the parser and by preg itself:
 *
 *  - An unquoted value is cut at the first '#' or space, so a database or
 *    admin password containing either finished the install with the WRONG
 *    secret and nobody could sign in. Values are quoted so the whole thing
 *    survives.
 *  - preg_replace treats $1 / \2 in its replacement as back-references, so a
 *    password carrying those sequences was mangled. The replacement is passed
 *    as a literal.
 */
class EnvWriter
{
    /** A value that is not a bare token gets double-quoted, with the
     *  double-quote, backslash and newline escaped as dotenv expects. */
    public static function quote(string $value): string
    {
        if ($value !== '' && preg_match('/^[A-Za-z0-9_.\/@:-]+$/', $value)) {
            return $value;
        }

        $escaped = str_replace(
            ['\\', '"', "\r", "\n"],
            ['\\\\', '\\"', '', '\\n'],
            $value
        );

        return '"'.$escaped.'"';
    }

    /**
     * Return $content with each key set to its value: existing lines replaced,
     * new keys appended.
     *
     * @param  array<string, string>  $values
     */
    public static function apply(string $content, array $values): string
    {
        foreach ($values as $key => $val) {
            $line = $key.'='.self::quote((string) $val);

            if (preg_match('/^'.preg_quote($key, '/').'=.*/m', $content)) {
                // Literal replacement: a value carrying $1 or \2 must not be
                // read as a back-reference.
                $content = preg_replace(
                    '/^'.preg_quote($key, '/').'=.*/m',
                    preg_quote($line, null) === $line ? $line : addcslashes($line, '\\$'),
                    $content,
                    1
                );
            } else {
                $content = rtrim($content, "\n")."\n".$line."\n";
            }
        }

        return $content;
    }
}
