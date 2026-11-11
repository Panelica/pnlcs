<?php

$_ENV['APP_ENV'] = 'testing';
$_SERVER['APP_ENV'] = 'testing';
putenv('APP_ENV=testing');

/*
 * Under --parallel every process boots from the same bootstrap/cache, and a
 * test that writes a route cache (AddonServiceProviderTest) made the others
 * load it - or fail to require it once it was deleted again. Each process gets
 * a route cache path of its own; a serial run keeps Laravel's default.
 */
$token = getenv('TEST_TOKEN');
if ($token !== false && $token !== '') {
    $routesCache = 'bootstrap/cache/routes-v7-test-'.$token.'.php';
    $_ENV['APP_ROUTES_CACHE'] = $_SERVER['APP_ROUTES_CACHE'] = $routesCache;
    putenv('APP_ROUTES_CACHE='.$routesCache);
}

require __DIR__.'/../vendor/autoload.php';
