<?php

use App\Models\Admin;
use App\Models\Language;

/*
 * A language's flag. flagcdn serves ISO 3166-1 alpha-2 country codes only, so a
 * regional code such as es-ct (Catalan) answers 404 - measured, for both the
 * png and the svg endpoint. Asking it anyway drew a broken image in the
 * language selector and left an empty cell in the admin list.
 */

it('draws a country flag from flagcdn', function () {
    Language::updateOrCreate(['code' => 'tr'],
        ['name' => 'Turkish', 'native_name' => 'Türkçe', 'flag_code' => 'tr', 'is_active' => true]);

    $this->actingAs(Admin::factory()->create(), 'admin')
        ->get(route('admin.config.languages.index'))
        ->assertOk()
        ->assertSee('flagcdn.com/24x18/tr.png', false);
});

it('draws a regional flag itself instead of asking flagcdn for a 404', function () {
    Language::updateOrCreate(['code' => 'es-ct'],
        ['name' => 'Catalan', 'native_name' => 'Català', 'flag_code' => 'es-ct', 'is_active' => true]);

    $html = $this->actingAs(Admin::factory()->create(), 'admin')
        ->get(route('admin.config.languages.index'))
        ->assertOk()
        ->getContent();

    expect($html)->not->toContain('flagcdn.com/24x18/es-ct')
        ->and($html)->not->toContain('flagcdn.com/es-ct')
        // the Senyera, drawn here: yellow ground, red bands
        ->and($html)->toContain('#FCDD09')
        ->and($html)->toContain('#DA121A');
});

it('asks flagcdn for nothing when the code is one it does not serve', function () {
    Language::updateOrCreate(['code' => 'zz-xx'],
        ['name' => 'Nowhere', 'native_name' => 'Nowhere', 'flag_code' => 'zz-xx', 'is_active' => true]);

    $html = $this->actingAs(Admin::factory()->create(), 'admin')
        ->get(route('admin.config.languages.index'))
        ->assertOk()
        ->getContent();

    // No picture that cannot load; the code itself stands in for the flag.
    expect($html)->not->toContain('flagcdn.com/24x18/zz-xx')
        ->and($html)->toContain('ZZ-XX');
});

it('shows the initial of a project message author in any script', function () {
    // The fourth place that took a name's first byte; #421 fixed the other three.
    expect(initial('张伟', 'A'))->toBe('张')
        ->and(initial(null, 'A'))->toBe('A');

    expect(file_get_contents(resource_path('views/admin/projects/show.blade.php')))
        ->not->toContain('strtoupper(substr(');
});
