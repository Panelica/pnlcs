<?php

use App\Models\Setting;
use Illuminate\Support\Facades\DB;

function seedRow(string $table, array $extra, string $createdAt): void
{
    DB::table($table)->insert(array_merge([
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ], $extra));
}

test('prune-logs deletes rows older than the retention window and keeps recent ones', function () {
    // emails default retention = 180 days
    seedRow('emails', ['subject' => 'old', 'message' => 'x', 'date' => now()->subDays(200), 'to' => 'a@b.c'], now()->subDays(200));
    seedRow('emails', ['subject' => 'fresh', 'message' => 'x', 'date' => now()->subDays(5), 'to' => 'a@b.c'], now()->subDays(5));

    $this->artisan('pnlcs:prune-logs')->assertExitCode(0);

    expect(DB::table('emails')->where('subject', 'old')->exists())->toBeFalse()
        ->and(DB::table('emails')->where('subject', 'fresh')->exists())->toBeTrue();
});

test('module_queue pending rows are never pruned even when old', function () {
    $svc = \App\Models\Service::factory()->create();

    seedRow('module_queue', ['service_id' => $svc->id, 'action' => 'create', 'status' => 'pending', 'attempts' => 0, 'max_attempts' => 5], now()->subDays(90));
    seedRow('module_queue', ['service_id' => $svc->id, 'action' => 'create', 'status' => 'completed', 'attempts' => 1, 'max_attempts' => 5], now()->subDays(90));

    $this->artisan('pnlcs:prune-logs')->assertExitCode(0);

    expect(DB::table('module_queue')->where('status', 'pending')->count())->toBe(1)
        ->and(DB::table('module_queue')->where('status', 'completed')->count())->toBe(0);
});

test('retention of 0 disables pruning for that table', function () {
    Setting::set('retention_emails_days', '0');
    seedRow('emails', ['subject' => 'ancient', 'message' => 'x', 'date' => now()->subDays(999), 'to' => 'a@b.c'], now()->subDays(999));

    $this->artisan('pnlcs:prune-logs')->assertExitCode(0);

    expect(DB::table('emails')->where('subject', 'ancient')->exists())->toBeTrue();
    Setting::set('retention_emails_days', '180');
});

test('a custom retention setting overrides the default', function () {
    Setting::set('retention_gateway_logs_days', '10');
    seedRow('gateway_logs', ['gateway' => 'stripe', 'data' => 'x', 'date' => now()->subDays(20)], now()->subDays(20));
    seedRow('gateway_logs', ['gateway' => 'stripe', 'data' => 'y', 'date' => now()->subDays(3)], now()->subDays(3));

    $this->artisan('pnlcs:prune-logs')->assertExitCode(0);

    expect(DB::table('gateway_logs')->count())->toBe(1);
    Setting::set('retention_gateway_logs_days', '90');
});

test('dry run reports without deleting', function () {
    seedRow('emails', ['subject' => 'old', 'message' => 'x', 'date' => now()->subDays(200), 'to' => 'a@b.c'], now()->subDays(200));

    $this->artisan('pnlcs:prune-logs', ['--dry-run' => true])->assertExitCode(0);

    expect(DB::table('emails')->where('subject', 'old')->exists())->toBeTrue();
});
