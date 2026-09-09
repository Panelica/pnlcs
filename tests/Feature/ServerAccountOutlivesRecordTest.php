<?php

use App\Contracts\ServerModuleInterface;
use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Product;
use App\Models\Server;
use App\Models\Service;
use App\Services\Module\ModuleRegistry;
use App\Services\OrderService;
use App\Services\PaymentService;

/*
 * A billing record can be closed while the account it stands for keeps
 * running on the server. Cancelling an order flipped its services to
 * cancelled without telling the panel; deleting a service by hand dropped
 * the row and left the account. Both leave hosting that nobody bills and
 * nothing says exists.
 */

class RecordingServerModule implements ServerModuleInterface
{
    public static array $calls = [];

    public function create(Service $service): array { self::$calls[] = 'create'; return ['success' => true]; }
    public function suspend(Service $service, string $reason = ''): array { self::$calls[] = 'suspend'; return ['success' => true]; }
    public function unsuspend(Service $service): array { self::$calls[] = 'unsuspend'; return ['success' => true]; }
    public function terminate(Service $service): array { self::$calls[] = 'terminate'; return ['success' => true]; }
    public function changePassword(Service $service, string $newPassword): array { return ['success' => true]; }
    public function changePackage(Service $service, array $newPackage): array { return ['success' => true]; }
    public function usageUpdate(Server $server): array { return []; }
    public function testConnection(Server $server): bool { return true; }
    public function getConfigFields(): array { return []; }
    public function getModuleName(): string { return 'Recorder'; }
}

function provisionedOrder(): array
{
    app(ModuleRegistry::class)->registerServer('recorder', RecordingServerModule::class);
    RecordingServerModule::$calls = [];

    $client = Client::factory()->create(['tax_exempt' => true]);
    $product = Product::factory()->create(['tax' => false, 'server_type' => 'recorder', 'auto_setup' => 'payment']);
    $order = app(OrderService::class)->processOrder($client, [[
        'type' => 'service', 'product_id' => $product->id, 'domain' => 'live.example.com', 'amount' => 10, 'billing_cycle' => 'Monthly',
    ]], 'banktransfer');
    app(PaymentService::class)->applyPayment($order->invoice, 'banktransfer', 'tx-live', 10.0);

    $service = Service::where('order_id', $order->id)->first();
    expect($service->status)->toBe('active')->and(RecordingServerModule::$calls)->toBe(['create']);

    return [$order->fresh(), $service];
}

test('cancelling an order with a live account terminates it on the server', function () {
    [$order, $service] = provisionedOrder();

    app(OrderService::class)->cancelOrder($order);

    expect(RecordingServerModule::$calls)->toContain('terminate')
        ->and($service->fresh()->status)->toBe('cancelled');
});

test('a service with a live account cannot simply be deleted from the books', function () {
    [, $service] = provisionedOrder();
    $admin = Admin::factory()->create([
        'role_id' => AdminRole::factory()->create(['name' => 'Ops', 'permissions' => ['list_services', 'manage_services']])->id,
    ]);

    $this->actingAs($admin, 'admin')->delete(route('admin.services.destroy', $service))->assertSessionHas('error');

    expect(Service::find($service->id))->not->toBeNull()
        ->and(RecordingServerModule::$calls)->not->toContain('terminate');
});

test('a terminated service can be deleted', function () {
    [, $service] = provisionedOrder();
    $service->update(['status' => 'terminated']);
    $admin = Admin::factory()->create([
        'role_id' => AdminRole::factory()->create(['name' => 'Ops2', 'permissions' => ['list_services', 'manage_services']])->id,
    ]);

    $this->actingAs($admin, 'admin')->delete(route('admin.services.destroy', $service))->assertSessionHas('success');

    expect(Service::find($service->id))->toBeNull();
});
