<?php

use App\Models\Currency;
use App\Models\Invoice;

/*
 * invoice_money_fmt() remembers the currency it looked up, and used to
 * remember a miss as well. A process that formatted an invoice before the
 * operator had added its currency then printed the bare code for the rest of
 * its life - a queue worker rendering invoice PDFs, or the test suite, where
 * one test's lookup decided what a later one was shown (a lira invoice read
 * "480.00 TRY" instead of "₺480.00" depending on what ran first).
 */

it('sees a currency added after an earlier format missed it', function () {
    $code = 'XQT';
    Currency::where('code', $code)->delete();

    $invoice = Invoice::factory()->create(['source_currency' => $code, 'total' => 480]);

    // No such currency yet: the bare code, which is the documented fallback.
    expect(invoice_money_fmt(480, $invoice))->toBe('480.00 '.$code);

    Currency::factory()->create(['code' => $code, 'prefix' => '₺', 'suffix' => '', 'format' => 1, 'rate' => 1]);

    // Same process, same function: the currency is found now.
    expect(invoice_money_fmt(480, $invoice))->toBe('₺480.00');
});
