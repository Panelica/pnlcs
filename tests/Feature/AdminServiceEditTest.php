<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Service;
use App\Services\ProvisioningService;

/*
 * Staff edit a service's record from its admin page: product, domain,
 * username, billing cycle, recurring amount, "do not suspend until" and notes.
 * The page could only change the due date and the status.
 */

function aseAdmin(array $permissions = ['view_services', 'manage_services']): Admin
{
    return Admin::factory()->create(['role_id' => AdminRole::factory()->create(['name' => 'R'.uniqid(), 'permissions' => $permissions])->id]);
}

function aseService(array $attrs = [], array $productAttrs = []): Service
{
    $product = Product::factory()->create(array_merge(['group_id' => ProductGroup::factory()->create()->id, 'pay_type' => 'recurring', 'server_type' => ''], $productAttrs));

    return Service::factory()->create(array_merge([
        'client_id' => Client::factory()->create()->id, 'product_id' => $product->id, 'status' => 'pending',
        'domain' => 'old.example.com', 'amount' => 10, 'billing_cycle' => 'Monthly', 'next_due_date' => now()->addMonth()->toDateString(),
    ], $attrs));
}

function aseForm(Service $service, array $data = []): array
{
    return array_merge([
        'product_id' => $service->product_id, 'domain' => $service->domain, 'username' => $service->username,
        'billing_cycle' => $service->billing_cycle, 'amount' => $service->amount,
        'override_auto_suspend_date' => null, 'notes' => $service->notes,
    ], $data);
}

it('saves the edited fields', function () {
    $service = aseService();
    $bigger = Product::factory()->create(['group_id' => $service->product->group_id, 'server_type' => '']);

    test()->actingAs(aseAdmin(), 'admin')->put(route('admin.services.update', $service), aseForm($service, [
        'product_id' => $bigger->id, 'domain' => 'NEW.example.com', 'username' => 'newuser', 'billing_cycle' => 'Annually',
        'amount' => '99.50', 'override_auto_suspend_date' => now()->addDays(10)->toDateString(), 'notes' => 'Agreed by phone.',
    ]))->assertSessionHas('success');

    $service->refresh();
    expect($service->product_id)->toBe($bigger->id)
        ->and($service->domain)->toBe('new.example.com')
        ->and($service->username)->toBe('newuser')
        ->and($service->billing_cycle)->toBe('Annually')
        ->and((float) $service->amount)->toBe(99.5)
        ->and($service->notes)->toBe('Agreed by phone.');
});

it('holds off the automatic suspension until the chosen day', function () {
    $service = aseService(['status' => 'active']);

    test()->actingAs(aseAdmin(), 'admin')->put(route('admin.services.update', $service), aseForm($service, ['override_auto_suspend_date' => now()->addDays(5)->toDateString()]))
        ->assertSessionHas('success');

    $held = Service::where('id', $service->id)->where(fn ($q) => $q->whereNull('override_auto_suspend_date')->orWhereDate('override_auto_suspend_date', '<', today()))->exists();
    expect($held)->toBeFalse();
});

it('moves an active service through the server module, and changes nothing if the server refuses', function () {
    $service = aseService(['status' => 'active'], ['server_type' => 'cpanel']);
    $bigger = Product::factory()->create(['group_id' => $service->product->group_id, 'server_type' => 'cpanel']);
    $old = $service->product_id;

    $provisioning = Mockery::mock(ProvisioningService::class);
    $provisioning->shouldReceive('changePackage')->once()->andReturn(['success' => false, 'message' => 'Package not found on server']);
    app()->instance(ProvisioningService::class, $provisioning);

    test()->actingAs(aseAdmin(), 'admin')->put(route('admin.services.update', $service), aseForm($service, ['product_id' => $bigger->id, 'amount' => 50]))
        ->assertSessionHas('error');

    $service->refresh();
    expect($service->product_id)->toBe($old)->and((float) $service->amount)->toBe(10.0);
});

it('refuses a domain that is not a hostname and a cycle orders never write', function () {
    $service = aseService();

    test()->actingAs(aseAdmin(), 'admin')->put(route('admin.services.update', $service), aseForm($service, ['domain' => 'https://x y']))->assertSessionHasErrors('domain');
    test()->actingAs(aseAdmin(), 'admin')->put(route('admin.services.update', $service), aseForm($service, ['billing_cycle' => 'Weekly']))->assertSessionHasErrors('billing_cycle');

    expect($service->fresh()->domain)->toBe('old.example.com');
});

it('shows the form to staff who manage services, and refuses the rest', function () {
    $service = aseService();

    test()->actingAs(aseAdmin(), 'admin')->get(route('admin.services.show', $service))->assertOk()->assertSee('name="override_auto_suspend_date"', false);
    test()->actingAs(aseAdmin(['view_services']), 'admin')->put(route('admin.services.update', $service), aseForm($service, ['amount' => 1]))->assertForbidden();
    expect((float) $service->fresh()->amount)->toBe(10.0);
});
