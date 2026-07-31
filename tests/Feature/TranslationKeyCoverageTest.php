<?php

use App\Models\Client;
use App\Models\Contact;
use App\Models\User;

/**
 * Every translation key the code asks for has something to say.
 *
 * Laravel answers a missing key with the key itself, so a customer who edited
 * one of their contacts was shown the words "messages.success.contact_updated"
 * where the confirmation should have been. The screen it was added on came
 * with the button; the sentence did not.
 */
test('no screen can show a raw translation key', function () {
    $groups = array_map(fn ($file) => basename($file, '.php'), glob(base_path('lang/en/*.php')));
    $keys = [];

    foreach ([resource_path('views'), app_path()] as $dir) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));

        foreach ($files as $file) {
            if (! preg_match('/\.php$/', (string) $file)) {
                continue;
            }

            preg_match_all(
                '/__\(\s*.([a-zA-Z0-9_]+)\.([a-zA-Z0-9_.]+).\s*[,)]/',
                file_get_contents((string) $file),
                $matches,
                PREG_SET_ORDER
            );

            foreach ($matches as $match) {
                if (! in_array($match[1], $groups, true)) {
                    continue;
                }

                $keys[$match[1].'.'.$match[2]] = str_replace(base_path().'/', '', (string) $file);
            }
        }
    }

    expect($keys)->not->toBeEmpty();

    $missing = [];

    foreach ($keys as $key => $file) {
        if (__($key) === $key) {
            $missing[] = "{$key} ({$file})";
        }
    }

    expect($missing)->toBe([]);
});

test('a customer who edits a contact is told it worked', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create();
    $user->clients()->attach($client->id);

    $contact = Contact::create([
        'client_id' => $client->id,
        'first_name' => 'Accounts',
        'last_name' => 'Department',
        'email' => 'accounts@example.test',
    ]);

    $this->actingAs($user)->put(route('client.account.contacts.update', $contact), [
        'first_name' => 'Accounts',
        'last_name' => 'Team',
        'email' => 'accounts@example.test',
    ])->assertRedirect();

    expect(session('success'))->not->toContain('messages.')
        ->and(session('success'))->not->toBeEmpty();
});
