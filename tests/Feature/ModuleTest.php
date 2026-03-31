<?php

use App\Services\Module\ModuleRegistry;


test("module registry is singleton", function () {
    $r1 = app(ModuleRegistry::class);
    $r2 = app(ModuleRegistry::class);
    expect($r1)->toBe($r2);
});

test("custom server module is registered", function () {
    $registry = app(ModuleRegistry::class);
    expect($registry->getServerModules())->toContain("custom");
});

test("bank transfer gateway is registered", function () {
    $registry = app(ModuleRegistry::class);
    expect($registry->getGatewayModules())->toContain("banktransfer");
});

test("custom server module implements interface", function () {
    $registry = app(ModuleRegistry::class);
    $module = $registry->getServerModule("custom");
    expect($module)->toBeInstanceOf(\App\Contracts\ServerModuleInterface::class);
    expect($module->getModuleName())->toBe("Custom/No Module");
});

test("bank transfer gateway implements interface", function () {
    $registry = app(ModuleRegistry::class);
    $module = $registry->getGatewayModule("banktransfer");
    expect($module)->toBeInstanceOf(\App\Contracts\GatewayModuleInterface::class);
    expect($module->isTokenised())->toBeFalse();
});

test("nonexistent module returns null", function () {
    $registry = app(ModuleRegistry::class);
    expect($registry->getServerModule("nonexistent"))->toBeNull();
});
