<?php

use App\Models\Client;
use App\Models\Transaction;
use App\Widgets\BillingWidget;
use Illuminate\Http\Request;
use Modules\Reports\AnnualIncomeReport;
use Modules\Reports\IncomeSummaryReport;
use Modules\Reports\TopClientsReport;

/**
 * Whose money is in the ledger.
 *
 * Affiliate commission and affiliate payouts share the transactions table with
 * customer payments. Every revenue figure summed the table whole, so money
 * owed to an affiliate was reported as income, a payout was reported as a
 * refund, and an affiliate could climb the top-customers list without ever
 * having bought anything.
 */
function ledgerRow(array $attrs): Transaction
{
    return Transaction::create(array_merge([
        'client_id' => Client::factory()->create()->id,
        'date' => now()->toDateString(),
        'description' => 'row',
        'amount_in' => 0,
        'amount_out' => 0,
        'fees' => 0,
        'rate' => 1,
    ], $attrs));
}

test('commission owed to an affiliate is not income', function () {
    ledgerRow(['gateway' => 'stripe', 'amount_in' => 100]);
    ledgerRow(['gateway' => 'affiliate_commission', 'amount_in' => 10]);

    $report = (new IncomeSummaryReport)->generate(new Request);

    expect((float) $report['totals'][1])->toEqual(100.0);
});

test('paying an affiliate out is not a refund', function () {
    ledgerRow(['gateway' => 'stripe', 'amount_in' => 100]);
    ledgerRow(['gateway' => 'affiliate_payout', 'amount_out' => 25]);

    $report = (new IncomeSummaryReport)->generate(new Request);

    expect((float) $report['totals'][3])->toEqual(0.0);
});

test('the annual figures leave affiliate money out too', function () {
    ledgerRow(['gateway' => 'stripe', 'amount_in' => 100]);
    ledgerRow(['gateway' => 'affiliate_commission', 'amount_in' => 10]);
    ledgerRow(['gateway' => 'affiliate_payout', 'amount_out' => 25]);

    $rows = collect((new AnnualIncomeReport)->generate(new Request)['rows']);

    expect((float) $rows->sum('income'))->toEqual(100.0)
        ->and((float) $rows->sum('refunds'))->toEqual(0.0);
});

test('an affiliate does not appear among the top customers on commission alone', function () {
    $affiliateClient = Client::factory()->create(['first_name' => 'Referring', 'last_name' => 'Partner']);

    Transaction::create([
        'client_id' => $affiliateClient->id,
        'gateway' => 'affiliate_commission',
        'date' => now()->toDateString(),
        'description' => 'commission',
        'amount_in' => 500,
        'amount_out' => 0,
        'fees' => 0,
        'rate' => 1,
    ]);

    $rows = collect((new TopClientsReport)->generate(new Request)['rows']);

    expect($rows->pluck('id')->all())->not->toContain($affiliateClient->id);
});

test('the dashboard revenue figure leaves commission out', function () {
    ledgerRow(['gateway' => 'stripe', 'amount_in' => 100]);
    ledgerRow(['gateway' => 'affiliate_commission', 'amount_in' => 10]);

    expect((float) (new BillingWidget)->getData()['all'])->toEqual(100.0);
});
