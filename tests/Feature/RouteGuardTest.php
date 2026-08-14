<?php

use Illuminate\Support\Facades\Route;

/**
 * Every door, and what stands in front of it.
 *
 * The individual screens are tested where they live. This asks the question
 * once for the whole application, so a route added later cannot quietly arrive
 * without a guard: the panel has 379 named routes and nobody reviews that list
 * by hand.
 *
 * Anything deliberately open is named below with the reason it is open. Adding
 * a route to an allowlist is a decision somebody makes on purpose; leaving one
 * out is what this is here to catch.
 */

/** Admin doors a signed-out visitor must be able to reach: the way in. */
const GUARD_PUBLIC_ADMIN = [
    'admin.login',
    'admin.login.submit',
    'admin.2fa.verify',
    'admin.2fa.verify.submit',
];

/**
 * Admin doors that need no permission of their own.
 *
 * Signing in and out, the second factor and the operator's own account belong
 * to whoever is holding the session. The dashboard, the API reference, the
 * calendar, the whois lookup and the to-do scratchpad are staff-wide by
 * design - the to-do list says so in routes/admin.php, in as many words.
 */
const GUARD_NO_PERMISSION_ADMIN = [
    'admin.login', 'admin.login.submit', 'admin.logout',
    'admin.2fa.verify', 'admin.2fa.verify.submit', 'admin.2fa.enable', 'admin.2fa.disable',
    'admin.my-account', 'admin.my-account.update',
    'admin.dashboard', 'admin.api-docs',
    'admin.calendar', 'admin.calendar.store', 'admin.calendar.events',
    'admin.calendar.update', 'admin.calendar.destroy',
    'admin.config.todo', 'admin.config.todo.store',
    'admin.config.todo.update', 'admin.config.todo.destroy',
    'admin.whois.index', 'admin.whois.lookup',
];

/** Customer doors that must work before there is a customer: the shop and the way in. */
const GUARD_PUBLIC_CLIENT = [
    'client.login', 'client.login.submit', 'client.register', 'client.register.submit',
    'client.password.request', 'client.password.email', 'client.password.reset',
    'client.password.update.reset',
    'client.contact', 'client.contact.submit',
    'client.announcements.index', 'client.announcements.show',
    'client.kb.index', 'client.kb.show',
    'client.network-status',
    'client.domain.pricing', 'client.domain.search', 'client.domain.check',
    'client.store', 'client.store.configure',
];

/** @return array<int, array{name: string, middleware: string}> */
function guardedRoutesStartingWith(string $prefix): array
{
    $rows = [];

    foreach (Route::getRoutes() as $route) {
        $name = $route->getName();

        if (! $name || ! str_starts_with($name, $prefix)) {
            continue;
        }

        $rows[] = ['name' => $name, 'middleware' => implode(',', $route->gatherMiddleware())];
    }

    return $rows;
}

it('keeps every admin door behind the admin sign-in', function () {
    $open = [];

    foreach (guardedRoutesStartingWith('admin.') as $route) {
        if (in_array($route['name'], GUARD_PUBLIC_ADMIN, true)) {
            continue;
        }

        if (! str_contains($route['middleware'], 'admin.auth')) {
            $open[] = $route['name'];
        }
    }

    expect($open)->toBe([]);
});

it('asks for a permission at every admin door that is not personal', function () {
    $unguarded = [];

    foreach (guardedRoutesStartingWith('admin.') as $route) {
        if (in_array($route['name'], GUARD_NO_PERMISSION_ADMIN, true)) {
            continue;
        }

        if (! str_contains($route['middleware'], 'admin.permission')) {
            $unguarded[] = $route['name'];
        }
    }

    expect($unguarded)->toBe([]);
});

it('keeps every customer door behind the customer sign-in', function () {
    $open = [];

    foreach (guardedRoutesStartingWith('client.') as $route) {
        if (in_array($route['name'], GUARD_PUBLIC_CLIENT, true)) {
            continue;
        }

        if (! preg_match('/\bauth\b/', $route['middleware'])) {
            $open[] = $route['name'];
        }
    }

    expect($open)->toBe([]);
});

it('does not leave a name in an allowlist that no longer exists', function () {
    $names = array_column(array_merge(
        guardedRoutesStartingWith('admin.'),
        guardedRoutesStartingWith('client.')
    ), 'name');

    $stale = array_values(array_diff(
        array_merge(GUARD_PUBLIC_ADMIN, GUARD_NO_PERMISSION_ADMIN, GUARD_PUBLIC_CLIENT),
        $names
    ));

    expect($stale)->toBe([]);
});
