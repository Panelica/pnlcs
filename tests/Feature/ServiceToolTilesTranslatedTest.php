<?php

/*
 * The hosting tool tiles on a service page were built from translation keys,
 * except three - Databases, FTP Accounts and Subdomains - written in English
 * in the view, so a Turkish (or German, Polish, Chinese) customer saw those
 * three in English beside the others in their language. The keys exist and
 * the pages behind the tiles already use them.
 */

test('every hosting tool tile takes its name and line from the language files', function () {
    $view = file_get_contents(resource_path('views/client/services/show.blade.php'));
    preg_match_all("/\\['k'=>'([a-z]+)','name'=>(.*?),'desc'=>(.*?),'ic'/", $view, $tiles, PREG_SET_ORDER);

    expect($tiles)->not->toBeEmpty();
    foreach ($tiles as [, $key, $name, $desc]) {
        expect($name)->toStartWith("__('client.hosting.", "{$key}: name")
            ->and($desc)->toStartWith("__('client.hosting.", "{$key}: line");
    }
});

test('the three tiles read in Turkish for a Turkish customer', function () {
    expect(__('client.hosting.databases.title', [], 'tr'))->toBe('Veritabanları')
        ->and(__('client.hosting.ftp.title', [], 'tr'))->not->toBe('FTP Accounts')
        ->and(__('client.hosting.subdomains.title', [], 'tr'))->not->toBe('Subdomains');
});
