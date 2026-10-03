<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;

/*
 * Log retention from the admin panel.
 *
 * pnlcs:prune-logs pruned each log table nightly by a retention read from
 * settings that no screen could change, and nothing showed how big the
 * tables had grown.
 */

function lrAdmin(array $permissions = ['manage_settings', 'view_activity_log']): Admin
{
    return Admin::factory()->create(['role_id' => AdminRole::factory()->create(['name' => 'R'.uniqid(), 'permissions' => $permissions])->id]);
}

function lrActivity(int $daysAgo): void
{
    DB::table('activity_logs')->insert(['date' => now(), 'description' => 'x', 'user' => 'test', 'created_at' => now()->subDays($daysAgo), 'updated_at' => now()]);
}

it('lists each log with its size, oldest entry and retention', function () {
    lrActivity(10);
    lrActivity(400);

    test()->actingAs(lrAdmin(), 'admin')->get(route('admin.settings.log-retention'))->assertOk()
        ->assertSee(__('admin.log_retention.table_activity_logs'))
        ->assertSee('name="days[activity_logs]"', false)
        ->assertSee('value="365"', false);
});

it('saves the retention per log', function () {
    test()->actingAs(lrAdmin(), 'admin')->put(route('admin.settings.log-retention.update'), ['days' => ['activity_logs' => 30, 'emails' => 0, 'not_a_log' => 5]])
        ->assertSessionHas('success');

    expect((string) Setting::get('retention_activity_logs_days'))->toBe('30')
        ->and((string) Setting::get('retention_emails_days'))->toBe('0')
        ->and(Setting::get('retention_not_a_log_days'))->toBeNull();
});

it('prunes one log now by its retention, and leaves the others', function () {
    Setting::set('retention_activity_logs_days', '30', 'general');
    lrActivity(10);
    lrActivity(60);
    DB::table('gateway_logs')->insert(['date' => now(), 'gateway' => 'x', 'data' => '{}', 'result' => 'ok', 'created_at' => now()->subDays(500), 'updated_at' => now()]);

    test()->actingAs(lrAdmin(), 'admin')->post(route('admin.settings.log-retention.prune'), ['table' => 'activity_logs'])
        ->assertSessionHas('success', __('admin.log_retention.pruned', ['count' => 1]));

    expect(DB::table('activity_logs')->count())->toBe(1)->and(DB::table('gateway_logs')->count())->toBe(1);
});

it('refuses an unknown table and staff without the settings permission', function () {
    test()->actingAs(lrAdmin(), 'admin')->post(route('admin.settings.log-retention.prune'), ['table' => 'users'])->assertNotFound();
    test()->actingAs(lrAdmin(['view_activity_log']), 'admin')->get(route('admin.settings.log-retention'))->assertForbidden();
});

it('names every pruned table in every language', function () {
    foreach (array_keys(\App\Console\Commands\PruneLogsCommand::retentionTargets()) as $table) {
        foreach (['en', 'tr', 'de', 'pl', 'zh'] as $locale) {
            $key = 'admin.log_retention.table_'.$table;
            expect(__($key, [], $locale))->not->toBe($key, "{$locale}: {$table}");
        }
    }
});
