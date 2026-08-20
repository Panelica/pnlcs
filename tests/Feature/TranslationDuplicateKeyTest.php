<?php

/*
 * The same key written twice in one file.
 *
 * Several language files carry a key both nested ('success' => ['x' => ...])
 * and flat ('success.x' => ...). Laravel looks for the literal key first, so
 * the flat one is what customers actually see and the nested one is dead
 * weight - which is a trap: editing the nested copy looks like a fix, changes
 * the file, passes review and changes nothing on screen.
 *
 * Living with the duplication is fine. Letting the two copies disagree about
 * placeholders is not: whichever copy loses, someone is reading a sentence with
 * a ':state' in it, or one that silently drops the number the code passed in.
 */

/** @return array{0: array<string,string>, 1: array<string,string>} nested map, flat map */
function duplicatedKeyPairs(string $file): array
{
    $rows = (array) require $file;

    $flatten = function (array $node, string $prefix) use (&$flatten): array {
        $out = [];
        foreach ($node as $key => $value) {
            $full = $prefix === '' ? (string) $key : $prefix.'.'.$key;
            if (is_array($value)) {
                $out += $flatten($value, $full);
            } else {
                $out[$full] = (string) $value;
            }
        }

        return $out;
    };

    $nested = [];
    $flat = [];
    foreach ($rows as $key => $value) {
        if (is_array($value)) {
            $nested += $flatten($value, (string) $key);
        } elseif (str_contains((string) $key, '.')) {
            $flat[(string) $key] = (string) $value;
        }
    }

    return [$nested, $flat];
}

function placeholdersIn(string $text): array
{
    preg_match_all('/:([A-Za-z_][A-Za-z0-9_]*)/', $text, $found);
    $names = $found[1];
    sort($names);

    return $names;
}

test('a key defined twice agrees with itself about placeholders', function () {
    $conflicts = [];

    foreach (glob(base_path('lang/*/*.php')) as $file) {
        [$nested, $flat] = duplicatedKeyPairs($file);
        foreach ($flat as $key => $flatValue) {
            if (! array_key_exists($key, $nested)) {
                continue;
            }
            if (placeholdersIn($flatValue) !== placeholdersIn($nested[$key])) {
                $conflicts[] = sprintf(
                    '%s [%s] flat: %s | nested: %s',
                    str_replace(base_path('lang/'), '', $file),
                    $key,
                    implode(',', placeholdersIn($flatValue)) ?: 'none',
                    implode(',', placeholdersIn($nested[$key])) ?: 'none',
                );
            }
        }
    }

    expect($conflicts)->toBe([]);
});
