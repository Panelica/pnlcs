<?php
/**
 * Manual CLI run of the same reconciliation the module's scheduled task performs every 5 minutes.
 * Normally NOT needed: PNLCS's scheduler cron line runs it. Use it to check by hand: php bin/reconcile.php 20
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit(1); }
$root = dirname(__DIR__, 4);
chdir($root);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    $gateway = app(\App\Services\Module\ModuleRegistry::class)->getGatewayModule('monobank');
    if (!$gateway || !method_exists($gateway, 'reconcilePending')) {
        throw new \RuntimeException('Monobank gateway not available');
    }
    $count = min(50, max(1, (int) ($argv[1] ?? 20)));
    $stats = ['payments' => $gateway->reconcilePending($count), 'refunds' => $gateway->reconcileOpenRefunds($count)];
    echo json_encode($stats, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), PHP_EOL;
    exit((($stats['payments']['errors'] ?? 0) > 0) ? 2 : 0);
} catch (\Throwable $e) {
    fwrite(STDERR, 'Monobank reconciliation failed: '.$e->getMessage().PHP_EOL);
    exit(1);
}
