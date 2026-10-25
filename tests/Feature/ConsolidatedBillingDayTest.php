<?php

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Client;
use App\Models\Domain;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Service;
use App\Models\Setting;
use App\Services\InvoiceGenerationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;

/*
 * One invoice a month (community request: a shop whose customers are mostly
 * resellers, with many small renewals on different days).
 *
 * Renewals are invoiced InvoiceDaysBeforeDue days ahead, each on its own day,
 * so such a customer got an invoice for every service and domain. With a
 * consolidated billing day set, the run on that day takes everything due
 * before the same day next month (plus the lead) onto one invoice per
 * customer; between billing days only what would otherwise be invoiced late
 * is raised. Off, nothing changes.
 */

beforeEach(fn () => Mail::fake());

function cbdService(Client $client, string $due, float $amount = 10.0): Service
{
    return Service::factory()->create([
        'client_id' => $client->id,
        'product_id' => Product::factory()->create(['group_id' => ProductGroup::factory()->create()->id, 'tax' => false])->id,
        'status' => 'active', 'auto_renew' => true, 'billing_cycle' => 'Monthly',
        'amount' => $amount, 'next_due_date' => $due,
    ]);
}

function cbdDomain(Client $client, string $due): Domain
{
    return Domain::factory()->create(['client_id' => $client->id, 'next_due_date' => $due, 'recurring_amount' => 12, 'payment_method' => 'banktransfer']);
}

function cbdRun(string $date): array
{
    test()->travelTo(Carbon::parse($date.' 06:00:00'));

    return app(InvoiceGenerationService::class)->generateDueInvoices();
}

/** @return array<int, string> the item types and ids on each of the client's invoices */
function cbdInvoices(Client $client): array
{
    return Invoice::where('client_id', $client->id)->orderBy('id')->get()
        ->map(fn ($i) => InvoiceItem::where('invoice_id', $i->id)->orderBy('id')->get()->map(fn ($it) => $it->type.'#'.$it->rel_id)->implode(','))
        ->all();
}

test('on the billing day everything due before the next one goes on one invoice per customer', function () {
    Setting::set('ConsolidatedBillingDay', '1');
    Setting::set('InvoiceDaysBeforeDue', '0');
    Setting::set('InvoiceDueDays', '14');
    $client = Client::factory()->create(['tax_exempt' => true]);
    $a = cbdService($client, '2026-12-12');
    $b = cbdService($client, '2026-12-28');
    $d = cbdDomain($client, '2026-12-15');
    $later = cbdService($client, '2027-01-05');
    $other = Client::factory()->create(['tax_exempt' => true]);
    $o = cbdService($other, '2026-12-20');

    // Nothing on the days before: these renewals wait for the billing day.
    cbdRun('2026-11-20');
    expect(Invoice::count())->toBe(0);

    cbdRun('2026-12-01');

    expect(cbdInvoices($client))->toBe(["Hosting#{$a->id},Hosting#{$b->id},Domain#{$d->id}"])
        ->and(cbdInvoices($other))->toBe(["Hosting#{$o->id}"])
        ->and(Invoice::where('client_id', $client->id)->value('due_date')->toDateString())->toBe('2026-12-15');

    // The next day adds nothing; January's renewal waits for 1 January.
    cbdRun('2026-12-02');
    expect(Invoice::count())->toBe(2);
    cbdRun('2027-01-01');
    expect(cbdInvoices($client)[1])->toBe("Hosting#{$later->id}");
});

test('the lead days reach into the month after', function () {
    Setting::set('ConsolidatedBillingDay', '1');
    Setting::set('InvoiceDaysBeforeDue', '31');
    $client = Client::factory()->create(['tax_exempt' => true]);
    $dec = cbdService($client, '2026-12-10');
    $jan = cbdService($client, '2027-01-20');
    $feb = cbdService($client, '2027-02-03');

    cbdRun('2026-12-01');

    expect(cbdInvoices($client))->toBe(["Hosting#{$dec->id},Hosting#{$jan->id}"]);
    expect($feb->fresh()->next_due_date->toDateString())->toBe('2027-02-03');
});

test('between billing days a renewal that would be late is still invoiced, at the usual lead', function () {
    Setting::set('ConsolidatedBillingDay', '1');
    Setting::set('InvoiceDaysBeforeDue', '7');
    $client = Client::factory()->create(['tax_exempt' => true]);

    cbdRun('2026-12-01');
    // Ordered after the billing day, renewing before the next one.
    $new = cbdService($client, '2026-12-20');

    cbdRun('2026-12-05');
    expect(Invoice::count())->toBe(0);
    cbdRun('2026-12-13');
    expect(cbdInvoices($client))->toBe(["Hosting#{$new->id}"]);
});

test('a domain paid for on the billing day is not billed again while its renewal is pending', function () {
    Setting::set('ConsolidatedBillingDay', '1');
    Setting::set('InvoiceDaysBeforeDue', '14');
    $client = Client::factory()->create(['tax_exempt' => true]);
    $domain = cbdDomain($client, '2026-12-30');

    cbdRun('2026-12-01');
    Invoice::where('client_id', $client->id)->sole()->update(['status' => 'paid']);

    // The registrar refused, so the dates stay; the run later in the month
    // must not take the domain for unbilled.
    cbdRun('2026-12-17');

    expect(Invoice::where('client_id', $client->id)->count())->toBe(1)
        ->and($domain->fresh()->next_due_date->toDateString())->toBe('2026-12-30');
});

test('off, renewals are invoiced on their own days as before', function () {
    Setting::set('InvoiceDaysBeforeDue', '14');
    $client = Client::factory()->create(['tax_exempt' => true]);
    cbdService($client, '2026-12-12');
    cbdService($client, '2026-12-28');

    cbdRun('2026-12-01');
    expect(Invoice::count())->toBe(1);
    cbdRun('2026-12-14');
    expect(Invoice::count())->toBe(2);
});

test('the window is worked out from the day of the month', function (int $day, int $lead, string $today, string $cutoff) {
    Setting::set('ConsolidatedBillingDay', (string) $day);
    Setting::set('InvoiceDaysBeforeDue', (string) $lead);

    [$end] = app(InvoiceGenerationService::class)->billingWindow(Carbon::parse($today));

    expect($end->toDateString())->toBe($cutoff);
})->with([
    'billing day, no lead' => [1, 0, '2026-12-01', '2026-12-31'],
    'billing day, lead' => [1, 14, '2026-12-01', '2027-01-14'],
    'the 15th, short month' => [15, 0, '2027-02-15', '2027-03-14'],
    'between, lead before the next day' => [1, 7, '2026-12-10', '2026-12-17'],
    'between, lead past the next day' => [1, 14, '2026-12-25', '2026-12-31'],
    'off' => [0, 14, '2026-12-25', '2027-01-08'],
]);

test('the setting is saved from General Settings and offered in the form', function () {
    $admin = Admin::factory()->create(['role_id' => AdminRole::factory()->fullAdmin()->create()->id]);

    $this->actingAs($admin, 'admin')->post(route('admin.settings.general.update'), ['ConsolidatedBillingDay' => '1'])->assertSessionHasNoErrors();
    expect(Setting::get('ConsolidatedBillingDay'))->toBe('1');

    $this->actingAs($admin, 'admin')->post(route('admin.settings.general.update'), ['ConsolidatedBillingDay' => '31'])->assertSessionHasErrors('ConsolidatedBillingDay');

    $this->actingAs($admin, 'admin')->get(route('admin.settings.general'))->assertOk()
        ->assertSee('name="ConsolidatedBillingDay"', false)->assertSee(__('admin.settings.consolidated_billing_day'));
});
