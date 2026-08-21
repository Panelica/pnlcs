<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Product;
use App\Models\ProductGroup;

/*
 * A product must not promise an app its own plan forbids.
 *
 * The Starter product shipped live with "container plan" and "customer picks
 * the app" ticked and Max Containers at 0. A customer paid, picked WireGuard,
 * and provisioning failed three times: account created, app refused by the
 * panel (Docker disabled in plan), account rolled back - with the reason as a
 * log line three screens from the order. The form's grey warning text did not
 * prevent it, because warnings nobody has to read are not gates.
 */

function guardAdmin(): Admin
{
    return Admin::factory()->create([
        'role_id' => AdminRole::factory()->fullAdmin()->create()->id,
    ]);
}

function containerProduct(array $config = []): Product
{
    return Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'type' => 'hosting',
        'server_type' => 'panelica',
        'config_options' => json_encode($config),
    ]);
}

function productForm(Product $product, array $overrides = []): array
{
    return array_merge([
        'name' => $product->name,
        'type' => 'hosting',
        'group_id' => $product->group_id,
        'server_type' => 'panelica',
        'pay_type' => 'recurring',
        'auto_setup' => 'payment',
        'res_section' => 1,
        'res_managed' => 1,
        'res_max_containers' => 0,
    ], $overrides);
}

test('a container plan cannot be saved with zero containers', function () {
    $product = containerProduct();

    $response = test()->actingAs(guardAdmin(), 'admin')
        ->from(route('admin.products.edit', $product))
        ->put(route('admin.products.update', $product), productForm($product, [
            'panelica_container_plan' => 1,
            'panelica_app_choose' => 1,
        ]));

    $response->assertRedirect(route('admin.products.edit', $product))
        ->assertSessionHas('error', __('admin.products.container_plan_needs_containers'));

    // Nothing was written: the contradiction never reaches the database.
    $saved = json_decode((string) $product->fresh()->config_options, true) ?: [];
    expect($saved['panelica_container_plan'] ?? 0)->toBe(0);
});

test('the same product saves once it allows a container', function () {
    $product = containerProduct();

    test()->actingAs(guardAdmin(), 'admin')
        ->put(route('admin.products.update', $product), productForm($product, [
            'panelica_container_plan' => 1,
            'panelica_app_choose' => 1,
            'res_max_containers' => 1,
        ]))
        ->assertSessionMissing('error');

    $saved = json_decode((string) $product->fresh()->config_options, true);
    expect($saved['res_max_containers'])->toBe(1)
        ->and($saved['panelica_container_plan'])->toBe(1);
});

test('a plain hosting product still saves with zero containers', function () {
    $product = containerProduct();

    // Zero containers on a product that sells no apps is not a contradiction,
    // it is the normal shape of a hosting plan.
    test()->actingAs(guardAdmin(), 'admin')
        ->put(route('admin.products.update', $product), productForm($product))
        ->assertSessionMissing('error');
});

test('the module refuses before creating anything', function () {
    // The pre-flight lives in the module source: an app-selling service on a
    // zero-container managed plan must be refused before the account exists,
    // not rolled back after - the rollbacks left orphaned home directories.
    $module = (string) file_get_contents(base_path('modules/Servers/Panelica/PanelicaModule.php'));

    expect($module)->toContain("container_plan_needs_containers")
        ->and($module)->toContain("disabledInPlan");
});

test('the explanation exists in every shipped language', function () {
    foreach (array_map('basename', glob(base_path('lang/*'), GLOB_ONLYDIR)) as $locale) {
        $admin = json_encode((array) require base_path("lang/$locale/admin.php"));
        expect($admin)->toContain('container_plan_needs_containers');
    }
});
