<?php

use App\Models\Admin;
use Illuminate\Support\Facades\Route;

/**
 * The API reference screen once documented 26 endpoints that did not exist and
 * left 91 real ones out - an integrator following it got 404s. The endpoint
 * list is now rendered from the live route table; these tests hold the page to
 * exactly the routed set, in both directions, forever.
 */
function routedApiSlugs(): array
{
    $slugs = [];
    foreach (Route::getRoutes() as $route) {
        if (str_starts_with($route->uri(), 'api/v1/')) {
            $slugs[] = basename($route->uri());
        }
    }
    sort($slugs);

    return array_values(array_unique($slugs));
}

function renderedApiSlugs(): array
{
    $html = test()->actingAs(Admin::factory()->create(), 'admin')
        ->get(route('admin.api-docs'))
        ->assertOk()
        ->getContent();

    preg_match_all('/ep-url">([a-z0-9]+)</', $html, $m);
    $slugs = array_values(array_unique($m[1]));
    sort($slugs);

    return $slugs;
}

it('documents every routed API endpoint', function () {
    $missing = array_diff(routedApiSlugs(), renderedApiSlugs());

    expect(array_values($missing))->toBe([]);
});

it('documents no endpoint that does not exist', function () {
    $phantom = array_diff(renderedApiSlugs(), routedApiSlugs());

    expect(array_values($phantom))->toBe([]);
});

it('keeps the parameter hints tied to real endpoints', function () {
    $phantom = array_diff(array_keys(config('api_docs')), routedApiSlugs());

    expect(array_values($phantom))->toBe([]);
});
