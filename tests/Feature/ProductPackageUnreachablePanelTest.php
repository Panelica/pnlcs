<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Server;
use Illuminate\Support\Facades\Http;

/**
 * The product page lists the panel's packages live. When the panel could not
 * be reached the list was empty, the saved package was not among the options,
 * and saving the product for any other reason posted "Default" and wiped it.
 */
it('keeps the saved package selectable when the panel cannot be reached', function () {
    Server::factory()->create([
        'type' => 'cpanel', 'hostname' => 'whm.example.test', 'ip_address' => '203.0.113.10',
        'port' => 2087, 'username' => 'root', 'access_hash' => 'TOKEN', 'active' => true,
    ]);
    Http::fake(['*' => Http::response('', 500)]);

    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id, 'server_type' => 'cpanel',
        'config_options' => json_encode(['package_name' => 'starter_1gb']),
    ]);
    $admin = Admin::factory()->create(['role_id' => AdminRole::factory()->fullAdmin()->create()->id]);

    $this->actingAs($admin, 'admin')->get(route('admin.products.edit', $product))
        ->assertOk()
        ->assertSee('<option value="starter_1gb" selected>starter_1gb</option>', false);
});
