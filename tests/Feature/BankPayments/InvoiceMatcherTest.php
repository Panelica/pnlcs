<?php

use App\Models\AddonSetting;
use App\Models\Admin;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Setting;
use App\Models\Transaction;
use Modules\Addons\BankPayments\Models\BankImport;
use Modules\Addons\BankPayments\Models\BankMailbox;
use Modules\Addons\BankPayments\Models\BankMatch;
use Modules\Addons\BankPayments\Models\BankMessage;
use Modules\Addons\BankPayments\Models\BankTransaction;
use Modules\Addons\BankPayments\Services\BankPaymentImporter;
use Modules\Addons\BankPayments\Services\InvoiceMatcher;

function matcherTransaction(array $overrides = []): BankTransaction
{
    $mailbox = BankMailbox::create([
        'name' => 'Matcher '.uniqid(),
        'host' => 'imap.example.test',
        'port' => 993,
        'encryption' => 'ssl',
        'username' => 'bank@example.test',
        'password' => 'secret',
        'folder' => 'INBOX',
        'bank' => 'nest_bank',
        'parser' => 'nest_bank',
        'active' => true,
        'validate_cert' => true,
    ]);

    $message = BankMessage::create([
        'bank_mailbox_id' => $mailbox->id,
        'uid' => random_int(1, 1000000),
        'uidvalidity' => 1,
        'subject' => 'Wpływ na konto',
        'content_hash' => hash('sha256', uniqid('', true)),
        'status' => 'parsed',
    ]);

    return BankTransaction::create($overrides + [
        'bank_message_id' => $message->id,
        'bank' => 'nest_bank',
        'operation_type' => 'Wpływ na konto',
        'transaction_date' => '2026-11-27',
        'amount' => 1234.87,
        'currency' => 'PLN',
        'sender_name' => 'Acme Sp. z o.o.',
        'reference' => 'Faktura PRO-2026/11-000123',
        'fingerprint' => hash('sha256', uniqid('', true)),
    ]);
}

function matcherInvoice(Client $client, array $overrides = []): Invoice
{
    return Invoice::factory()->create($overrides + [
        'client_id' => $client->id,
        'invoice_num' => 'PRO-2026/11-000123',
        'total' => 1234.87,
        'status' => 'unpaid',
    ]);
}

it('scores a full number, amount and client match as auto_ready', function () {
    $client = Client::factory()->create(['company_name' => 'Acme Sp. z o.o.']);
    $invoice = matcherInvoice($client);
    $transaction = matcherTransaction();

    $result = app(InvoiceMatcher::class)->match($transaction);

    expect($result['status'])->toBe('auto_ready')
        ->and($result['score'])->toBe(90)
        ->and($transaction->fresh()->matching_status)->toBe('auto_ready')
        ->and($transaction->fresh()->matching_score)->toBe(90)
        ->and(BankMatch::count())->toBe(1)
        ->and(BankMatch::first()->invoice_id)->toBe($invoice->id)
        ->and(BankMatch::first()->score)->toBe(90)
        ->and($invoice->fresh()->status)->toBe('unpaid')
        ->and(Transaction::count())->toBe(0);
});

it('sends a number and client match with a different amount to review', function () {
    $client = Client::factory()->create(['company_name' => 'Acme Sp. z o.o.']);
    matcherInvoice($client, ['total' => 1000]);
    $transaction = matcherTransaction();

    $result = app(InvoiceMatcher::class)->match($transaction);

    expect($result['status'])->toBe('review')
        ->and($result['score'])->toBe(60)
        ->and($transaction->fresh()->matching_status)->toBe('review')
        ->and(BankMatch::count())->toBe(1);
});

it('does not auto-select between two matching invoices', function () {
    $client = Client::factory()->create(['company_name' => 'Acme Sp. z o.o.']);

    foreach (['INV-100', 'INV-200'] as $number) {
        matcherInvoice($client, ['invoice_num' => $number, 'total' => 1234.87]);
    }

    $transaction = matcherTransaction(['reference' => 'Faktura INV-100 oraz INV-200']);

    $result = app(InvoiceMatcher::class)->match($transaction);

    expect($result['status'])->toBe('ambiguous')
        ->and($transaction->fresh()->matching_status)->toBe('ambiguous')
        ->and(BankMatch::count())->toBe(2);
});

it('does not match a currency other than PLN', function () {
    $client = Client::factory()->create(['company_name' => 'Acme Sp. z o.o.']);
    matcherInvoice($client);
    $transaction = matcherTransaction(['currency' => 'EUR']);

    $result = app(InvoiceMatcher::class)->match($transaction);

    expect($result['status'])->toBe('manual_currency')
        ->and(BankMatch::count())->toBe(0);
});

it('leaves a settled invoice out of matching', function () {
    $client = Client::factory()->create(['company_name' => 'Acme Sp. z o.o.']);
    matcherInvoice($client, ['status' => 'paid']);
    $transaction = matcherTransaction();

    $result = app(InvoiceMatcher::class)->match($transaction);

    expect($result['status'])->toBe('unmatched')
        ->and(BankMatch::count())->toBe(0);
});

it('books a manual approval through PaymentService and records an import', function () {
    Setting::set('addon_bank_payments_active', '1', 'addons');
    AddonSetting::setSetting('bank_payments', 'mode', 'semi_auto');

    $client = Client::factory()->create(['company_name' => 'Acme Sp. z o.o.']);
    $invoice = matcherInvoice($client);
    $transaction = matcherTransaction();
    app(InvoiceMatcher::class)->match($transaction);

    $match = BankMatch::firstOrFail();
    $result = app(BankPaymentImporter::class)->import($match, Admin::factory()->create(), null, 'manual');

    expect($result['success'])->toBeTrue()
        ->and($invoice->fresh()->status)->toBe('paid')
        ->and(Transaction::count())->toBe(1)
        ->and(BankImport::firstOrFail()->status)->toBe('imported')
        ->and(BankImport::firstOrFail()->payment_reference)->toBe('BANK:'.$transaction->id.':'.$invoice->id)
        ->and($match->fresh()->status)->toBe('imported')
        ->and($transaction->fresh()->matching_status)->toBe('imported');
});

it('is idempotent when the same match is approved twice', function () {
    Setting::set('addon_bank_payments_active', '1', 'addons');
    AddonSetting::setSetting('bank_payments', 'mode', 'semi_auto');

    $client = Client::factory()->create(['company_name' => 'Acme Sp. z o.o.']);
    $invoice = matcherInvoice($client);
    $transaction = matcherTransaction();
    app(InvoiceMatcher::class)->match($transaction);

    $importer = app(BankPaymentImporter::class);
    $match = BankMatch::firstOrFail();
    $first = $importer->import($match);
    $second = $importer->import($match->fresh());

    expect($first['success'])->toBeTrue()
        ->and($second['success'])->toBeTrue()
        ->and($second['duplicate'])->toBeTrue()
        ->and(Transaction::count())->toBe(1)
        ->and($invoice->fresh()->status)->toBe('paid');
});

it('allows automatic booking only for a single full match', function () {
    Setting::set('addon_bank_payments_active', '1', 'addons');
    AddonSetting::setSetting('bank_payments', 'mode', 'auto');

    $client = Client::factory()->create(['company_name' => 'Acme Sp. z o.o.']);
    $invoice = matcherInvoice($client);
    $transaction = matcherTransaction();
    app(InvoiceMatcher::class)->match($transaction);

    $result = app(BankPaymentImporter::class)->import(BankMatch::firstOrFail(), null, null, 'auto');

    expect($result['success'])->toBeTrue()
        ->and($invoice->fresh()->status)->toBe('paid')
        ->and(Transaction::count())->toBe(1);
});

it('blocks automatic booking when two invoices match', function () {
    Setting::set('addon_bank_payments_active', '1', 'addons');
    AddonSetting::setSetting('bank_payments', 'mode', 'auto');

    $client = Client::factory()->create(['company_name' => 'Acme Sp. z o.o.']);
    foreach (['INV-100', 'INV-200'] as $number) {
        matcherInvoice($client, ['invoice_num' => $number, 'total' => 1234.87]);
    }

    $transaction = matcherTransaction(['reference' => 'Faktura INV-100 oraz INV-200']);
    app(InvoiceMatcher::class)->match($transaction);
    $match = BankMatch::firstOrFail();

    $result = app(BankPaymentImporter::class)->import($match, null, null, 'auto');

    expect($result['success'])->toBeFalse()
        ->and(Transaction::count())->toBe(0)
        ->and($match->fresh()->status)->not->toBe('imported');
});

it('refuses to book while the addon is in test mode', function () {
    Setting::set('addon_bank_payments_active', '1', 'addons');
    AddonSetting::setSetting('bank_payments', 'mode', 'test');

    $client = Client::factory()->create(['company_name' => 'Acme Sp. z o.o.']);
    $invoice = matcherInvoice($client);
    $transaction = matcherTransaction();
    app(InvoiceMatcher::class)->match($transaction);

    $result = app(BankPaymentImporter::class)->import(BankMatch::firstOrFail());

    expect($result['success'])->toBeFalse()
        ->and(Transaction::count())->toBe(0)
        ->and($invoice->fresh()->status)->toBe('unpaid');
});
