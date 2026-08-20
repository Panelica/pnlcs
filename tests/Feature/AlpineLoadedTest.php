<?php

use App\Models\Admin;
use App\Models\AdminRole;

/**
 * The pages written against Alpine have to be given Alpine.
 *
 * resources/js/app.js held one line - a comment saying Alpine comes bundled
 * with Livewire 3 - and Livewire is not installed. Nothing imported Alpine, so
 * the built bundle was zero bytes and no page ever loaded it.
 *
 * Four admin screens are written against it. The invoice and quote builders
 * render their line items inside <template x-for>, and a <template> renders
 * nothing at all on its own: the tables came up empty and there was no way to
 * put a line on a manual invoice or a quote.
 */
function alpineAdmin(): Admin
{
    return Admin::factory()->create([
        'role_id' => AdminRole::factory()->fullAdmin()->create()->id,
    ]);
}

test('the javascript bundle actually carries alpine', function () {
    // A checkout without built assets is not a broken product. Fail here only
    // where the assets exist - a deployment, or CI after npm run build - so the
    // guard keeps meaning instead of being red on every fresh clone.
    if (! file_exists(public_path('build/manifest.json'))) {
        $this->markTestSkipped('Front-end assets are not built here: run npm ci && npm run build.');
    }

    $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);
    $entry = $manifest['resources/js/app.js']['file'] ?? null;

    expect($entry)->not->toBeNull();

    $bundle = file_get_contents(public_path('build/'.$entry));

    expect(strlen($bundle))->toBeGreaterThan(1000)
        ->and($bundle)->toContain('Alpine');
});

test('the invoice builder is served the script it is written against', function () {
    $html = $this->actingAs(alpineAdmin(), 'admin')
        ->get(route('admin.invoices.create'))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('x-data="invoiceBuilder()"')
        ->and($html)->toContain('function invoiceBuilder');
});

test('both layouts ask for the bundle', function () {
    // The suite calls withoutVite(), so a rendered page never carries the tag;
    // the layouts are read instead.
    foreach (['admin/layouts/app.blade.php', 'client/layouts/app.blade.php'] as $layout) {
        expect(file_get_contents(resource_path('views/'.$layout)))
            ->toContain('resources/js/app.js');
    }
});
