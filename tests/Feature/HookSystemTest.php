<?php

use App\Contracts\ServerModuleInterface;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\ModuleQueue;
use App\Models\Product;
use App\Models\Server;
use App\Models\Service;
use App\Services\HookManager;
use App\Services\InvoiceService;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Services\ProvisioningService;

class AlwaysFailingServerModule implements ServerModuleInterface
{
    public function create(Service $service): array { return ['success' => false, 'message' => 'boom']; }
    public function suspend(Service $service, string $reason = ''): array { return ['success' => false]; }
    public function unsuspend(Service $service): array { return ['success' => false]; }
    public function terminate(Service $service): array { return ['success' => false]; }
    public function changePassword(Service $service, string $newPassword): array { return ['success' => false]; }
    public function changePackage(Service $service, array $newPackage): array { return ['success' => false]; }
    public function usageUpdate(Server $server): array { return []; }
    public function testConnection(Server $server): bool { return false; }
    public function getConfigFields(): array { return []; }
    public function getModuleName(): string { return 'Always Failing'; }
}

// ---------------------------------------------------------------------------
// HookManager core behavior
// ---------------------------------------------------------------------------

test('add_hook and run_hook round-trip with params', function () {
    $received = null;
    add_hook('MyTestPoint', 10, function (array $vars) use (&$received) {
        $received = $vars;
        return 'ok';
    });

    $results = run_hook('MyTestPoint', ['foo' => 'bar']);

    expect($received)->toBe(['foo' => 'bar'])
        ->and($results)->toBe(['ok']);
});

test('hooks run in priority order, FIFO within same priority', function () {
    $order = [];
    add_hook('PriorityPoint', 20, function () use (&$order) { $order[] = 'late'; });
    add_hook('PriorityPoint', 1, function () use (&$order) { $order[] = 'early'; });
    add_hook('PriorityPoint', 10, function () use (&$order) { $order[] = 'mid-a'; });
    add_hook('PriorityPoint', 10, function () use (&$order) { $order[] = 'mid-b'; });

    run_hook('PriorityPoint');

    expect($order)->toBe(['early', 'mid-a', 'mid-b', 'late']);
});

test('hook names are case-insensitive', function () {
    $called = false;
    add_hook('CaseTest', function () use (&$called) { $called = true; });

    run_hook('casetest');

    expect($called)->toBeTrue();
});

test('a throwing hook is skipped and later hooks still run', function () {
    $ran = false;
    add_hook('BrokenPoint', 1, function () { throw new RuntimeException('broken addon'); });
    add_hook('BrokenPoint', 2, function () use (&$ran) { $ran = true; return 'survived'; });

    $results = run_hook('BrokenPoint');

    expect($ran)->toBeTrue()
        ->and($results)->toBe(['survived']);
});

test('hook files load from a directory', function () {
    $dir = sys_get_temp_dir() . '/pnlcs-hook-test-' . uniqid();
    mkdir($dir);
    file_put_contents($dir . '/test-hook.php', '<?php add_hook("FileLoadedPoint", fn () => "from-file");');

    $loaded = app(HookManager::class)->loadHookFilesFrom($dir);
    $results = run_hook('FileLoadedPoint');

    unlink($dir . '/test-hook.php');
    rmdir($dir);

    expect($loaded)->toBe(1)
        ->and($results)->toBe(['from-file']);
});

// ---------------------------------------------------------------------------
// Event bridge + core hook points
// ---------------------------------------------------------------------------

test('InvoicePaid hook fires when an invoice is settled', function () {
    $client  = Client::factory()->create();
    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'status' => 'unpaid', 'total' => 10.00]);

    $seen = null;
    add_hook('InvoicePaid', function (array $vars) use (&$seen) { $seen = $vars; });

    app(InvoiceService::class)->markPaid($invoice, 'TXN-HOOK-1', 'stripe');

    expect($seen)->not->toBeNull()
        ->and($seen['invoice']->id)->toBe($invoice->id)
        ->and($seen['transactionId'])->toBe('TXN-HOOK-1');
});

test('InvoicePartiallyPaid hook fires on partial payment', function () {
    $client  = Client::factory()->create();
    $invoice = Invoice::factory()->create(['client_id' => $client->id, 'status' => 'unpaid', 'total' => 100.00]);

    $seen = null;
    add_hook('InvoicePartiallyPaid', function (array $vars) use (&$seen) { $seen = $vars; });

    app(PaymentService::class)->applyPayment($invoice, 'banktransfer', 'TXN-HOOK-P1', 30.00);

    expect($seen)->not->toBeNull()
        ->and((float) $seen['amount'])->toBe(30.0)
        ->and((float) $seen['balance'])->toBe(70.0);
});

test('provisioning fires PreModuleCreate, AfterModuleCreate and AcceptOrder hooks', function () {
    $client  = Client::factory()->create(['tax_exempt' => true]);
    $product = Product::factory()->create(['tax' => false, 'server_type' => 'custom', 'auto_setup' => 'payment']);

    $fired = [];
    foreach (['PreModuleCreate', 'AfterModuleCreate', 'AcceptOrder', 'ServiceActivated'] as $point) {
        add_hook($point, function () use (&$fired, $point) { $fired[] = $point; });
    }

    $order = app(OrderService::class)->processOrder($client, [[
        'type' => 'service', 'product_id' => $product->id,
        'domain' => 'hook-chain.com', 'amount' => 5.00, 'billing_cycle' => 'Monthly',
    ]], 'stripe');

    $invoice = Invoice::find($order->invoice_id);
    app(PaymentService::class)->applyPayment($invoice, 'stripe', 'TXN-HOOK-C1', (float) $invoice->total);

    expect($fired)->toContain('PreModuleCreate')
        ->and($fired)->toContain('AfterModuleCreate')
        ->and($fired)->toContain('AcceptOrder')
        ->and($fired)->toContain('ServiceActivated');
});

test('ModuleActionFailed hook fires when a module action fails', function () {
    app(\App\Services\Module\ModuleRegistry::class)->registerServer('alwaysfailing', AlwaysFailingServerModule::class);

    $product = Product::factory()->create(['server_type' => 'alwaysfailing']);
    $service = Service::factory()->pending()->create(['product_id' => $product->id]);

    $seen = null;
    add_hook('ModuleActionFailed', function (array $vars) use (&$seen) { $seen = $vars; });

    app(ProvisioningService::class)->createAccount($service->fresh(['product']));

    expect($seen)->not->toBeNull()
        ->and($seen['action'])->toBe('create')
        ->and($seen['error'])->toBe('boom')
        ->and(ModuleQueue::where('service_id', $service->id)->where('status', 'pending')->exists())->toBeTrue();
});

test('ClientAdd WHMCS alias fires alongside ClientCreated', function () {
    $firedAlias = false;
    $firedCanonical = false;
    add_hook('ClientAdd', function () use (&$firedAlias) { $firedAlias = true; });
    add_hook('ClientCreated', function () use (&$firedCanonical) { $firedCanonical = true; });

    event(new \App\Events\ClientCreated(Client::factory()->create()));

    expect($firedAlias)->toBeTrue()
        ->and($firedCanonical)->toBeTrue();
});
