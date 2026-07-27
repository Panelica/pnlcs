<?php

use App\Models\Client;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Server;
use App\Models\Service;
use App\Services\CartService;
use Illuminate\Support\Facades\Http;
use Modules\Servers\CPanel\CPanelModule;

/**
 * Server modules kept their remote identifiers as JSON inside services.notes,
 * the same column the cart and the customer use for a human note. The first
 * module write therefore destroyed the customer's note (proved at runtime:
 * "Lutfen PHP 8.3 kurun" became {"cpanel_username":"user123"}), and a human
 * note made the module data unreadable. Module data now has its own column.
 */
function moduleDataProbe(): object
{
    return new class extends CPanelModule
    {
        public function read(Service $service): array
        {
            return $this->getModuleData($service);
        }

        public function write(Service $service, array $data): void
        {
            $this->setModuleData($service, $data);
        }
    };
}

test('writing module data leaves the customer note intact', function () {
    $service = Service::factory()->create([
        'client_id' => Client::factory()->create()->id,
        'notes' => 'Lütfen PHP 8.3 kurun',
    ]);

    moduleDataProbe()->write($service, ['cpanel_username' => 'user123']);

    $fresh = $service->fresh();
    expect($fresh->notes)->toBe('Lütfen PHP 8.3 kurun')
        ->and($fresh->module_data)->toBe(['cpanel_username' => 'user123']);
});

test('module data survives a customer note being edited afterwards', function () {
    $service = Service::factory()->create(['client_id' => Client::factory()->create()->id]);
    $probe = moduleDataProbe();

    $probe->write($service, ['cpanel_username' => 'user123']);
    $service->update(['notes' => 'Müşteri sonradan not ekledi']);

    expect($probe->read($service->fresh()))->toBe(['cpanel_username' => 'user123']);
});

test('module data merges instead of replacing', function () {
    $service = Service::factory()->create(['client_id' => Client::factory()->create()->id]);
    $probe = moduleDataProbe();

    $probe->write($service, ['cpanel_username' => 'user123']);
    $probe->write($service, ['remote_id' => 55]);

    // MySQL's native JSON type normalises key order, so compare canonically.
    expect($probe->read($service->fresh()))->toEqualCanonicalizing(['cpanel_username' => 'user123', 'remote_id' => 55]);
});

test('legacy JSON stored in notes is still readable and is cleaned up on write', function () {
    $service = Service::factory()->create(['client_id' => Client::factory()->create()->id]);
    // Simulate a pre-migration row.
    $service->forceFill(['notes' => json_encode(['panelica_user_id' => 4242]), 'module_data' => null])->saveQuietly();

    $probe = moduleDataProbe();
    expect($probe->read($service->fresh()))->toBe(['panelica_user_id' => 4242]);

    $probe->write($service->fresh(), ['cpanel_username' => 'u']);

    $fresh = $service->fresh();
    expect($fresh->module_data)->toEqualCanonicalizing(['panelica_user_id' => 4242, 'cpanel_username' => 'u'])
        ->and($fresh->notes)->toBeNull();
});

test('a cart note reaches the service and survives provisioning', function () {
    Http::fake(['*' => Http::response(['status' => 1, 'result' => ['status' => 1]], 200)]);

    $client = Client::factory()->create();
    $product = Product::factory()->create([
        'group_id' => ProductGroup::factory()->create()->id,
        'server_type' => null,
    ]);

    $cart = app(CartService::class)->getOrCreateCart($client->id);
    app(CartService::class)->addProduct($cart, $product, 'monthly', 'yeni-site.com', [], 'SSL de kurun lütfen', 'register');
    $order = app(CartService::class)->checkout($cart, $client->id, 'banktransfer');

    $service = Service::where('order_id', $order->id)->firstOrFail();
    expect($service->notes)->toContain('SSL de kurun lütfen');

    // A module writing its data must not wipe that.
    moduleDataProbe()->write($service, ['cpanel_username' => 'u']);
    expect($service->fresh()->notes)->toContain('SSL de kurun lütfen');
});
