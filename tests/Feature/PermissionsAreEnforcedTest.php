<?php

use App\Constants\Permissions;
use App\Models\Admin;
use App\Models\AdminRole;

/**
 * Permissions that are offered but never asked about.
 *
 * The role screen lists every permission in the catalogue as a checkbox. Four
 * of them were consulted nowhere: an operator building a read-only reviewer
 * could tick "list quotes" and hand over a role that cannot open the quotes
 * screen at all, because the screen asks for "manage quotes" instead. A
 * control that does nothing is worse than no control - it is believed.
 */
function adminWith(array $permissions): Admin
{
    $role = AdminRole::create([
        'name' => 'Scoped '.uniqid(),
        'permissions' => $permissions,
        'is_full_admin' => false,
    ]);

    return Admin::factory()->create(['role_id' => $role->id]);
}

it('asks about every permission it offers', function () {
    $catalogue = collect(Permissions::grouped())->flatten()->unique()->values();

    $asked = collect();

    foreach (['routes/admin.php', 'routes/client.php', 'routes/api.php'] as $file) {
        preg_match_all('/admin\.permission:([a-z_,|]+)/', file_get_contents(base_path($file)), $matches);
        foreach ($matches[1] ?? [] as $group) {
            $asked = $asked->merge(preg_split('/[,|]/', $group));
        }
    }

    foreach (['app', 'resources'] as $dir) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($dir)));
        foreach ($files as $file) {
            if (! in_array($file->getExtension(), ['php'], true)) {
                continue;
            }
            preg_match_all('/hasPermission\([\'"]([a-z_]+)[\'"]/', file_get_contents($file->getPathname()), $m);
            $asked = $asked->merge($m[1] ?? []);
            preg_match_all('/Permissions::([A-Z_]+)/', file_get_contents($file->getPathname()), $c);
            foreach ($c[1] ?? [] as $const) {
                $asked->push(strtolower($const));
            }
        }
    }

    $asked = $asked->unique();

    expect($catalogue->diff($asked)->values()->all())->toBe([]);
});

it('lets someone who may only list quotes open the list', function () {
    $admin = adminWith([Permissions::LIST_QUOTES]);

    $this->actingAs($admin, 'admin')->get(route('admin.quotes.index'))->assertOk();
});

it('still refuses someone with no quote permission at all', function () {
    $admin = adminWith(['list_clients']);

    $this->actingAs($admin, 'admin')->get(route('admin.quotes.index'))->assertForbidden();
});

it('lets someone who may only list projects open the list', function () {
    $admin = adminWith([Permissions::LIST_PROJECTS]);

    $this->actingAs($admin, 'admin')->get(route('admin.projects.index'))->assertOk();
});

it('keeps writing behind the managing permission', function () {
    $admin = adminWith([Permissions::LIST_QUOTES]);

    $this->actingAs($admin, 'admin')->get(route('admin.quotes.create'))->assertForbidden();
});
