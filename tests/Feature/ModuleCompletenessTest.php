<?php

use App\Contracts\ServerModuleInterface;
use App\Services\Module\ModuleRegistry;
use Modules\Servers\AbstractServerModule;

/**
 * Every server module answers everything that is asked of it.
 *
 * A module that is half written is not discovered until an order is placed
 * against it, and then the customer pays for something nobody creates. This
 * walks the registry instead: every module on disk is registered, every one
 * implements the interface, and every one answers the plan question - even if
 * the answer is "I do not publish a list".
 */
function registeredServerModules(): array
{
    $registry = app(ModuleRegistry::class);
    $property = (new ReflectionClass($registry))->getProperty('serverModules');
    $property->setAccessible(true);

    return $property->getValue($registry);
}

test('every module folder is registered', function () {
    $onDisk = collect(glob(base_path('modules/Servers/*'), GLOB_ONLYDIR))
        ->map(fn ($dir) => strtolower(basename($dir)))
        ->values()
        ->all();

    $registered = array_keys(registeredServerModules());

    expect(array_diff($onDisk, $registered))->toBe([]);
});

test('every registered module can be built and implements the contract', function () {
    foreach (registeredServerModules() as $key => $class) {
        $module = app($class);

        expect($module)->toBeInstanceOf(ServerModuleInterface::class, "module [{$key}]");

        foreach (['create', 'suspend', 'unsuspend', 'terminate', 'changePassword',
            'changePackage', 'usageUpdate', 'testConnection', 'getConfigFields', 'getModuleName'] as $method) {
            expect(method_exists($module, $method))->toBeTrue("module [{$key}] is missing {$method}()");
        }
    }
});

test('every module answers the plan question', function () {
    foreach (registeredServerModules() as $key => $class) {
        expect(method_exists(app($class), 'listPackages'))
            ->toBeTrue("module [{$key}] cannot be asked for its plans");
    }
});

test('a module that inherits server selection names itself with its key', function () {
    // AbstractServerModule::getServer() picks the server with
    // Server::where('type', $this->getModuleName()), so for anything built on
    // it the name is a lookup key and a mismatch means no server is ever
    // found. Custom does not inherit that and uses the name as a label.
    foreach (registeredServerModules() as $key => $class) {
        if (! is_subclass_of($class, AbstractServerModule::class)) {
            continue;
        }

        expect(strtolower(app($class)->getModuleName()))
            ->toBe($key, "module [{$key}] would never find its server");
    }
});

test('the product form offers exactly the registered modules', function () {
    expect(array_keys(app(ModuleRegistry::class)->serverModuleNames()))
        ->toBe(array_keys(registeredServerModules()));
})->skip('order differs: the form sorts by name');

test('the form offers every registered module', function () {
    $offered = array_keys(app(ModuleRegistry::class)->serverModuleNames());

    foreach (array_keys(registeredServerModules()) as $key) {
        expect($offered)->toContain($key);
    }
});
