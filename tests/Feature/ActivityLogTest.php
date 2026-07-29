<?php

use App\Models\ActivityLog;
use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Setting;
use App\Services\PaymentService;
use App\Widgets\AutomationWidget;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Mail;

/**
 * The activity log the admin area shows.
 *
 * A listener is wired to ten business events and was named for the job, but it
 * only ever wrote a line to the file log. The Activity Log page, the
 * dashboard's recent-activity widget, a customer's Log tab and the
 * getactivitylog API therefore all read a table nothing had written to since
 * the demo data was seeded — four months of silence on a busy installation.
 */
test('paying an invoice is recorded where the admin area looks for it', function () {
    Mail::fake();

    $client = Client::factory()->create();
    $invoice = Invoice::factory()->create([
        'client_id' => $client->id,
        'status' => 'unpaid',
        'subtotal' => 50,
        'total' => 50,
    ]);

    app(PaymentService::class)->applyPayment($invoice, 'stripe', 'TXN-LOG-1', 50.0);

    $entry = ActivityLog::latest('id')->first();

    expect($entry)->not->toBeNull()
        ->and($entry->description)->toContain("Invoice #{$invoice->id} paid")
        ->and($entry->client_id)->toBe($client->id);
});

test('a customers log shows their own entries and not a customer whose id starts the same way', function () {
    $short = Client::factory()->create(['id' => 700001, 'email' => 'short@example.test']);
    $long = Client::factory()->create(['id' => 7000012, 'email' => 'long@example.test']);

    ActivityLog::log('Invoice #1 paid', 'System', $long->id);
    ActivityLog::log('Invoice #2 paid', 'System', $short->id);

    $entries = ActivityLog::forClient($short)->get();

    expect($entries)->toHaveCount(1)
        ->and($entries->first()->description)->toBe('Invoice #2 paid');
});

test('the activity log page shows what was recorded', function () {
    $admin = Admin::factory()->create([
        'role_id' => AdminRole::factory()->create([
            'name' => 'Logs',
            'permissions' => ['view_activity_log'],
        ])->id,
    ]);

    ActivityLog::log('Invoice #4242 paid', 'System');

    $this->actingAs($admin, 'admin')->get(route('admin.logs.index'))
        ->assertOk()
        ->assertSee('Invoice #4242 paid');
});

test('the dashboard reports when the scheduler last ran', function () {
    expect((new AutomationWidget)->getData()['last_cron'])->toBe('Never');

    event(new ScheduledTaskFinished(
        app(Schedule::class)->command('pnlcs:mark-overdue'),
        0.5
    ));

    expect(Setting::get('LastCronRun'))->not->toBeEmpty()
        ->and((new AutomationWidget)->getData()['last_cron'])->not->toBe('Never');
});
