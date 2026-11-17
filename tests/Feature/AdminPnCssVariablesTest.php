<?php

use App\Models\Admin;
use App\Models\AdminRole;
use Symfony\Component\Finder\Finder;

/*
 * Admin views and widgets style themselves with --pn-* variables (report
 * headings are background:var(--pn-primary);color:#fff), but nothing
 * defined them, so a report's column titles were white on no background
 * (#450). The admin layout now defines them from the theme tokens.
 */

test('every --pn variable the admin uses is defined on the admin pages', function () {
    $used = [];
    foreach ((new Finder)->files()->in([resource_path('views'), app_path()])->name(['*.php']) as $file) {
        preg_match_all('/var\((--pn-[a-z0-9-]+)/', $file->getContents(), $m);
        $used = array_merge($used, $m[1]);
    }
    $used = array_values(array_unique($used));

    $admin = Admin::factory()->create(['role_id' => AdminRole::factory()->fullAdmin()->create()->id]);
    $html = $this->actingAs($admin, 'admin')->get(route('admin.dashboard'))->assertOk()->getContent();

    expect($used)->not->toBeEmpty();
    foreach ($used as $name) {
        expect($html)->toContain($name.':');
    }
});
