<?php

use App\Providers\ModuleServiceProvider;
use App\Services\Module\ModuleRegistry;
use Illuminate\Support\Facades\File;

/*
 * Third-party modules found through their pnlcs.json manifest (PR #47).
 *
 * Each test writes a real module directory under modules/, rebuilds the
 * registry the way the application boots it, and removes the directory
 * afterwards. The class files are real PSR-4 files so class_exists() loads
 * them exactly as it would for an operator's module.
 */

function discoveryDirs(): array
{
    return [
        base_path('modules/Gateways/ZzDiscoveryPay'),
        base_path('modules/Gateways/ZzDiscoveryWrongType'),
        base_path('modules/Gateways/ZzDiscoveryStripeClash'),
    ];
}

function writeModule(string $dir, string $class, string $body, array $manifest): void
{
    File::ensureDirectoryExists($dir);
    $namespace = 'Modules\\Gateways\\'.basename($dir);
    File::put("{$dir}/{$class}.php", "<?php\n\nnamespace {$namespace};\n\n{$body}\n");
    File::put("{$dir}/pnlcs.json", json_encode($manifest + ['class' => "{$namespace}\\{$class}"]));
}

function freshRegistry(): ModuleRegistry
{
    app()->forgetInstance(ModuleRegistry::class);
    (new ModuleServiceProvider(app()))->register();

    return app(ModuleRegistry::class);
}

beforeEach(function () {
    foreach (discoveryDirs() as $dir) {
        File::deleteDirectory($dir);
    }
});

afterEach(function () {
    foreach (discoveryDirs() as $dir) {
        File::deleteDirectory($dir);
    }
    freshRegistry();
});

test('a module dropped into modules/ with a manifest is registered', function () {
    writeModule(base_path('modules/Gateways/ZzDiscoveryPay'), 'ZzDiscoveryPayModule',
        'class ZzDiscoveryPayModule extends \\Modules\\Gateways\\BankTransfer\\BankTransferModule {}',
        ['name' => 'ZzDiscoveryPay', 'type' => 'gateway']);

    $registry = freshRegistry();

    expect($registry->getGatewayModules())->toContain('zzdiscoverypay')
        ->and($registry->isDiscovered('gateway', 'zzdiscoverypay'))->toBeTrue()
        ->and($registry->getGatewayModule('zzdiscoverypay'))->toBeInstanceOf(\App\Contracts\GatewayModuleInterface::class)
        // The built-ins are still there, and still built-in.
        ->and($registry->getGatewayModules())->toContain('stripe', 'iyzico', 'paypal')
        ->and($registry->isDiscovered('gateway', 'stripe'))->toBeFalse();
});

test('a manifest cannot replace a module the core registered', function () {
    writeModule(base_path('modules/Gateways/ZzDiscoveryStripeClash'), 'ZzClashModule',
        'class ZzClashModule extends \\Modules\\Gateways\\BankTransfer\\BankTransferModule {}',
        ['name' => 'Stripe', 'type' => 'gateway']);

    $registry = freshRegistry();

    expect($registry->getGatewayModule('stripe'))->toBeInstanceOf(\Modules\Gateways\Stripe\StripeModule::class)
        ->and($registry->isDiscovered('gateway', 'stripe'))->toBeFalse();
});

test('a manifest whose class is not the type it claims is ignored', function () {
    // A server module announced as a gateway would load, pass class_exists(),
    // and fail only when a customer reached checkout.
    writeModule(base_path('modules/Gateways/ZzDiscoveryWrongType'), 'ZzWrongModule',
        'class ZzWrongModule extends \\Modules\\Servers\\Custom\\CustomModule {}',
        ['name' => 'ZzWrong', 'type' => 'gateway']);

    $registry = freshRegistry();

    expect($registry->has('gateway', 'zzwrong'))->toBeFalse();
});

test('the built-in manifests register nothing on their own', function () {
    // Ten built-in modules ship pnlcs.json files without a "class"; discovery
    // must leave the registry exactly as the core's own list builds it.
    $registry = freshRegistry();

    foreach (['server', 'gateway', 'registrar', 'ssl'] as $type) {
        foreach (array_keys($registry->classesOf($type)) as $key) {
            expect($registry->isDiscovered($type, $key))->toBeFalse();
        }
    }

    expect($registry->getServerModules())->toEqualCanonicalizing(['custom', 'panelica', 'cpanel', 'plesk', 'directadmin', 'proxmox', 'hestiacp', 'vultr'])
        ->and($registry->getGatewayModules())->toEqualCanonicalizing(['banktransfer', 'stripe', 'paypal', 'authorize', 'mollie', 'razorpay', 'tpay', 'iyzico'])
        ->and($registry->getRegistrarModules())->toEqualCanonicalizing(['namecheap', 'resellerclub', 'openprovider', 'enom', 'manual', 'hrd'])
        ->and($registry->getSslModules())->toEqual(['gogetssl']);
});
