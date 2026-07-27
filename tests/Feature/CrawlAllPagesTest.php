<?php

use App\Models\Admin;
use App\Models\Client;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Crawls every parameterless GET route as an authenticated admin and as an
 * authenticated client, and fails on any 500. This is the cheapest possible
 * end-to-end smoke net for the whole panel.
 */
test('every admin GET page renders without a server error', function () {
    $admin = Admin::factory()->create();
    $skip = ['admin.logout', 'admin.system-phpinfo', 'admin.system-database'];

    $broken = [];
    $visited = 0;
    foreach (Route::getRoutes() as $route) {
        if (! in_array('GET', $route->methods(), true)) {
            continue;
        }
        $name = $route->getName();
        if (! $name || ! str_starts_with($name, 'admin.')) {
            continue;
        }
        if (str_contains($route->uri(), '{') || in_array($name, $skip, true)) {
            continue;
        }

        try {
            $response = $this->actingAs($admin, 'admin')->get('/'.ltrim($route->uri(), '/'));
            $status = $response->getStatusCode();
        } catch (Throwable $e) {
            $broken[] = $name.' => EXCEPTION: '.substr($e->getMessage(), 0, 120);

            continue;
        }
        $visited++;
        if ($status >= 500) {
            $broken[] = $name.' => HTTP '.$status;
        }
    }

    // Guard against a silently empty crawl.
    expect($visited)->toBeGreaterThan(20);
    expect($broken)->toBe([]);
});

test('every client GET page renders without a server error', function () {
    $user = User::factory()->create();
    $user->clients()->attach(Client::factory()->create()->id);
    $skip = ['client.logout'];

    $broken = [];
    $visited = 0;
    foreach (Route::getRoutes() as $route) {
        if (! in_array('GET', $route->methods(), true)) {
            continue;
        }
        $name = $route->getName();
        if (! $name || ! str_starts_with($name, 'client.')) {
            continue;
        }
        if (str_contains($route->uri(), '{') || in_array($name, $skip, true)) {
            continue;
        }

        try {
            $response = $this->actingAs($user)->get('/'.ltrim($route->uri(), '/'));
            $status = $response->getStatusCode();
        } catch (Throwable $e) {
            $broken[] = $name.' => EXCEPTION: '.substr($e->getMessage(), 0, 120);

            continue;
        }
        $visited++;
        if ($status >= 500) {
            $broken[] = $name.' => HTTP '.$status;
        }
    }

    // Guard against a silently empty crawl.
    expect($visited)->toBeGreaterThan(20);
    expect($broken)->toBe([]);
});
