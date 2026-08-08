<?php

use App\Models\Client;
use App\Models\Transaction;
use App\Widgets\BillingWidget;

/**
 * Income on the front page that a refund never came out of.
 *
 * The Billing widget summed amount_in and stopped there. Every report in the
 * product treats a refund as money that left again: the annual report nets
 * income against fees and refunds, the income summary breaks the three out side
 * by side, and the top clients report calls SUM(amount_in - amount_out) revenue.
 *
 * So refunding a customer left the dashboard still counting their payment, for
 * ever, and an operator comparing the front page with the reports got two
 * different answers for the same month.
 */
function revenueRow(string $gateway, float $in, float $out, string $date): Transaction
{
    return Transaction::create([
        'client_id' => Client::factory()->create()->id,
        'gateway' => $gateway,
        'date' => $date,
        'description' => 'test movement',
        'amount_in' => $in,
        'amount_out' => $out,
        'fees' => 0,
        'rate' => 1,
    ]);
}

it('takes a refund back out of today', function () {
    revenueRow('stripe', 100, 0, today()->toDateString());
    revenueRow('stripe', 0, 30, today()->toDateString());

    expect((float) (new BillingWidget)->getData()['today'])->toBe(70.0);
});

it('takes a refund back out of the month and the year and the total', function () {
    revenueRow('stripe', 200, 0, today()->startOfMonth()->toDateString());
    revenueRow('stripe', 0, 50, today()->toDateString());

    $data = (new BillingWidget)->getData();

    expect((float) $data['month'])->toBe(150.0)
        ->and((float) $data['year'])->toBe(150.0)
        ->and((float) $data['all'])->toBe(150.0);
});

it('still leaves affiliate movements out of income', function () {
    revenueRow('stripe', 100, 0, today()->toDateString());
    revenueRow('affiliate_commission', 40, 0, today()->toDateString());
    revenueRow('affiliate_payout', 0, 40, today()->toDateString());

    expect((float) (new BillingWidget)->getData()['today'])->toBe(100.0);
});

it('still counts an ordinary payment in full', function () {
    revenueRow('banktransfer', 250, 0, today()->toDateString());

    expect((float) (new BillingWidget)->getData()['today'])->toBe(250.0);
});
