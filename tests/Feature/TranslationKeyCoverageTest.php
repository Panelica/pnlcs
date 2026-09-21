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
 *
 * THE THREE WAYS THIS CODEBASE ASKS FOR A STRING. Grepped, not assumed:
 * __( is 5,723 calls, @lang( is 10, trans_choice( is 5, and trans( is a single
 * occurrence inside a comment. Nothing else reaches the translator with a
 * literal key: Lang::has()/Lang::get() are only ever handed a key composed at
 * runtime (app/Support/formatting.php:49-50 and :75-76,
 * app/Http/Controllers/Admin/ConfigController.php:1704), and each of the three
 * sits behind a has() check that supplies its own fallback, so there is no
 * literal there to verify and no raw key it could print.
 *
 * All three used to go unchecked except __(. trans_choice() invisibility cost
 * us the four plural keys in the product - client.store.res_domains,
 * client.store.res_apps, client.hosting.containers.services and
 * admin.automation.every_minutes. Three of them had no Turkish at all, so a
 * Turkish customer was shown the English plural: "5 websites" on a page where
 * Turkish says "5 web sitesi". Nothing failed, because nothing was looking.
 *
 * WHERE IT LOOKS. resources/views and app/ were not the whole product either.
 * modules/ holds 150 translation calls of its own - the KSeF, iyzico, company
 * lookup and registrar modules - and bootstrap/app.php names one more in the
 * 404 handler. Adding those three roots put another 108 keys under this test.
 */
test('no screen can show a raw translation key', function () {
    $groups = array_map(fn ($file) => basename($file, '.php'), glob(base_path('lang/en/*.php')));
    $keys = [];

    $roots = [resource_path('views'), app_path(), base_path('modules'), base_path('bootstrap')];

    foreach ($roots as $dir) {
        if (! is_dir($dir)) {
            continue;
        }

        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));

        foreach ($files as $file) {
            $path = (string) $file;

            if (! preg_match('/\.php$/', $path)) {
                continue;
            }

            // Compiled by the framework, not written by anyone here.
            if (str_contains($path, '/bootstrap/cache/')) {
                continue;
            }

            // __('group.key'), @lang('group.key') and
            // trans_choice('group.key', $n) alike.
            preg_match_all(
                '/(?:__|@lang|trans_choice)\(\s*.([a-zA-Z0-9_]+)\.([a-zA-Z0-9_.]+).\s*[,)]/',
                file_get_contents($path),
                $matches,
                PREG_SET_ORDER
            );

            foreach ($matches as $match) {
                if (! in_array($match[1], $groups, true)) {
                    continue;
                }

                $keys[$match[1].'.'.$match[2]] = str_replace(base_path().'/', '', $path);
            }
        }
    }

    /*
     * The scan is not allowed to quietly narrow again. Each of these four keys
     * is reachable through exactly one of the forms or roots this test had to
     * be widened to cover, so dropping any one of them from the pattern above
     * fails here rather than going unnoticed for another release:
     *
     *   @lang         admin.products.package_loading
     *                 (resources/views/admin/products/create.blade.php:103)
     *   trans_choice  client.store.res_domains  (app/Models/Product.php:167)
     *   modules/      client.hosting.containers.component_delete_refused
     *                 (modules/Servers/Panelica/PanelicaModule.php:1868)
     *   bootstrap/    admin.errors.record_not_found  (bootstrap/app.php:78)
     */
    expect(array_keys($keys))->toContain(
        'admin.products.package_loading',
        'client.store.res_domains',
        'client.hosting.containers.component_delete_refused',
        'admin.errors.record_not_found',
    );

    $missing = [];

    foreach ($keys as $key => $file) {
        if (__($key) === $key) {
            $missing[] = "{$key} ({$file})";
        }
    }

    expect($missing)->toBe([], sprintf(
        "%d keys are asked for and answered with themselves. Add the English to\n".
        "lang/en/<group>.php, or seed it into dynamic_translations the way the\n".
        "migrations in database/migrations do:\n%s",
        count($missing),
        implode("\n", array_slice($missing, 0, 20))
    ));
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
