<?php

/*
 * No settings key may dangle.
 *
 * An audit of every key against its writers found both directions broken:
 * keys that were seeded and never read (EnableTax - editing it changed
 * nothing), and keys that were read and never written (the invoice logo -
 * uploading one changed nothing). Each looks like a working knob and is
 * connected to nothing, and both kinds cost a live install a support
 * question before they cost anyone a code read.
 */

/** @return array<int, string> every 'setting' key the seeders create */
function seededSettingKeys(): array
{
    $keys = [];
    foreach (glob(database_path('seeders/*.php')) as $file) {
        preg_match_all("/\['setting' => '([A-Za-z_0-9]+)'/", (string) file_get_contents($file), $found);
        $keys = array_merge($keys, $found[1]);
    }

    return array_values(array_unique($keys));
}

/** @return string the app source, concatenated, for existence checks */
function applicationSource(): string
{
    static $source = null;
    if ($source !== null) {
        return $source;
    }

    $source = '';
    $dirs = [app_path(), resource_path('views'), base_path('routes')];
    foreach ($dirs as $dir) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
        foreach ($it as $f) {
            if ($f->isFile() && str_ends_with($f->getFilename(), '.php')) {
                $source .= file_get_contents($f->getPathname());
            }
        }
    }

    return $source;
}

test('every seeded setting is read by something', function () {
    $unread = [];
    foreach (seededSettingKeys() as $key) {
        if (! str_contains(applicationSource(), "'".$key."'") && ! str_contains(applicationSource(), '"'.$key.'"')) {
            $unread[] = $key;
        }
    }

    // A seeded knob nothing reads is an invitation to edit it and expect a
    // change. InvoicePayTerms, EnableTax and TaxType were exactly this.
    expect($unread)->toBe([]);
});

test('the invoice reads only keys an operator can actually set', function () {
    $source = (string) file_get_contents(app_path('Services/InvoicePdfService.php'));
    preg_match_all("/Setting::get\('([A-Za-z_0-9]+)'/", $source, $found);

    // GENERAL_KEYS via the settings screen, the appearance uploads, and the
    // hand-set legacy names the invoice deliberately honours - a tested
    // decision (CompanyDetailsTest), the same stance the logo takes.
    $writable = [
        'Address', 'CompanyCity', 'Country', 'PhoneNumber', 'Email', 'TaxID', 'Domain',
        'custom_logo_path', 'Logo',
        'CompanyAddress', 'City', 'CompanyCountry', 'CompanyPhone', 'CompanyEmail',
    ];

    expect(array_values(array_diff($found[1], $writable)))->toBe([]);
});
