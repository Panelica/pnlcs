<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Product;
use App\Models\ProductGroup;


test("products index page loads", function () {
    $role = AdminRole::factory()->fullAdmin()->create();
    $admin = Admin::factory()->create(["role_id" => $role->id]);
    $response = $this->actingAs($admin, "admin")->get(route("admin.products.index"));
    $response->assertStatus(200)->assertSee("Products/Services");
});

test("create product group page loads", function () {
    $role = AdminRole::factory()->fullAdmin()->create();
    $admin = Admin::factory()->create(["role_id" => $role->id]);
    $response = $this->actingAs($admin, "admin")->get(route("admin.products.groups.create"));
    $response->assertStatus(200);
});

test("can create product group", function () {
    $role = AdminRole::factory()->fullAdmin()->create();
    $admin = Admin::factory()->create(["role_id" => $role->id]);
    $response = $this->actingAs($admin, "admin")->post(route("admin.products.groups.store"), [
        "name" => "Shared Hosting",
        "headline" => "Our hosting plans",
    ]);
    $response->assertRedirect(route("admin.products.index"));
    expect(ProductGroup::where("name", "Shared Hosting")->exists())->toBeTrue();
});

test("create product page loads", function () {
    $role = AdminRole::factory()->fullAdmin()->create();
    $admin = Admin::factory()->create(["role_id" => $role->id]);
    ProductGroup::create(["name" => "Test Group", "slug" => "test-group"]);
    $response = $this->actingAs($admin, "admin")->get(route("admin.products.create"));
    $response->assertStatus(200);
});

test("can create product", function () {
    $role = AdminRole::factory()->fullAdmin()->create();
    $admin = Admin::factory()->create(["role_id" => $role->id]);
    $group = ProductGroup::create(["name" => "Web Hosting", "slug" => "web-hosting"]);
    $response = $this->actingAs($admin, "admin")->post(route("admin.products.store"), [
        "name" => "Starter Plan",
        "group_id" => $group->id,
        "type" => "hosting",
        "pay_type" => "recurring",
    ]);
    expect(Product::where("name", "Starter Plan")->exists())->toBeTrue();
});

test("can edit product", function () {
    $role = AdminRole::factory()->fullAdmin()->create();
    $admin = Admin::factory()->create(["role_id" => $role->id]);
    $group = ProductGroup::create(["name" => "Hosting", "slug" => "hosting"]);
    $product = Product::create(["name" => "Basic", "slug" => "basic", "group_id" => $group->id, "type" => "hosting", "pay_type" => "recurring"]);
    $response = $this->actingAs($admin, "admin")->get(route("admin.products.edit", $product));
    $response->assertStatus(200)->assertSee("Basic");
});

test("can update product", function () {
    $role = AdminRole::factory()->fullAdmin()->create();
    $admin = Admin::factory()->create(["role_id" => $role->id]);
    $group = ProductGroup::create(["name" => "Hosting", "slug" => "hosting2"]);
    $product = Product::create(["name" => "Basic", "slug" => "basic2", "group_id" => $group->id, "type" => "hosting", "pay_type" => "recurring"]);
    $this->actingAs($admin, "admin")->put(route("admin.products.update", $product), [
        "name" => "Pro Plan",
        "group_id" => $group->id,
        "type" => "hosting",
        "pay_type" => "recurring",
    ]);
    expect($product->fresh()->name)->toBe("Pro Plan");
});
