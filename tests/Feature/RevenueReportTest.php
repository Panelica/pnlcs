<?php

use App\Models\Client;
use App\Models\Currency;
use App\Models\Invoice;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use App\Services\ReportManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Modules\Reports\IncomeSummaryReport;
use Modules\Reports\TopClientsReport;

/**
 * What the operator is shown about money coming in.
 *
 * Two of these reports read the transaction ledger, which is the record of
 * money that actually moved. The client-facing ones read invoice totals with
 * status = paid, which is not the same thing once a refund or a partial
 * payment is involved.
 */
function revenueScenario(): array
{
    $currency = Currency::where('is_default', true)->first()
        ?? Currency::create(['code' => 'USD', 'prefix' => '$', 'suffix' => '', 'rate' => 1, 'is_default' => true]);

    // Straightforward customer: pays 100, keeps it.
    $plain = Client::factory()->create(['first_name' => 'Plain', 'last_name' => 'Payer', 'tax_exempt' => true]);
    $a = app(InvoiceService::class)->createInvoice($plain, [
        ['type' => 'Hosting', 'rel_id' => 0, 'description' => 'Hosting', 'amount' => 100, 'taxed' => false],
    ]);
    app(PaymentService::class)->applyPayment($a, 'banktransfer', 'TXN-A', 100.0);

    // Refunded customer: pays 200, gets 50 back. Net 150.
    $refunded = Client::factory()->create(['first_name' => 'Partly', 'last_name' => 'Refunded', 'tax_exempt' => true]);
    $b = app(InvoiceService::class)->createInvoice($refunded, [
        ['type' => 'Hosting', 'rel_id' => 0, 'description' => 'Hosting', 'amount' => 200, 'taxed' => false],
    ]);
    app(PaymentService::class)->applyPayment($b, 'banktransfer', 'TXN-B', 200.0);
    app(PaymentService::class)->refundInvoice($b->fresh(), 50.0);

    return compact('plain', 'refunded', 'a', 'b');
}

test('the income summary reports the money that actually moved', function () {
    Mail::fake();
    revenueScenario();

    $report = (new IncomeSummaryReport)->generate(new Request);

    // 300 in, 50 back out.
    expect((float) $report['totals'][1])->toBe(300.0)
        ->and((float) $report['totals'][3])->toBe(50.0);
});

test('a customer who was refunded still counts towards what they have paid', function () {
    Mail::fake();
    $fx = revenueScenario();

    $rows = collect((new TopClientsReport)->generate(new Request)['rows']);
    $refunded = $rows->firstWhere('id', $fx['refunded']->id);

    // Refunding part of an invoice moves it off 'paid', and a report keyed on
    // that status drops the customer out of the list altogether — as if they
    // had never paid the 200 in the first place.
    expect($refunded)->not->toBeNull()
        ->and((float) $refunded->revenue)->toBe(150.0);
});

test('a customer who paid in full is reported at what they paid', function () {
    Mail::fake();
    $fx = revenueScenario();

    $rows = collect((new TopClientsReport)->generate(new Request)['rows']);
    $plain = $rows->firstWhere('id', $fx['plain']->id);

    expect($plain)->not->toBeNull()
        ->and((float) $plain->revenue)->toBe(100.0);
});

test('an invoice settled out of credit is not counted as fresh income', function () {
    Mail::fake();
    $client = Client::factory()->create(['credit' => 80, 'tax_exempt' => true]);

    // The money for this arrived earlier, when the balance was funded.
    $invoice = app(InvoiceService::class)->createInvoice($client, [
        ['type' => 'Hosting', 'rel_id' => 0, 'description' => 'Hosting', 'amount' => 80, 'taxed' => false],
    ]);

    expect($invoice->fresh()->status)->toBe('paid');

    $report = (new IncomeSummaryReport)->generate(new Request);

    expect((float) $report['totals'][1])->toBe(0.0);
});

test('every report runs and returns something the page can render', function () {
    Mail::fake();
    revenueScenario();

    $manager = app(ReportManager::class)->discover();
    // all() groups by category; the reports themselves are one level down.
    $reports = $manager->all()->flatten();

    expect($reports->count())->toBeGreaterThan(20);

    $broken = [];

    foreach ($reports as $report) {
        try {
            $out = $report->generate(new Request);

            if (! is_array($out) || ! array_key_exists('columns', $out) || ! array_key_exists('rows', $out)) {
                $broken[] = $report->getTitle().' → malformed output';
            }
        } catch (Throwable $e) {
            $broken[] = $report->getTitle().' → '.$e->getMessage();
        }
    }

    expect($broken)->toBe([]);
});
