<?php

use App\Models\AddonSetting;
use App\Models\Admin;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Setting;
use App\Providers\AddonServiceProvider;
use App\Services\AddonManager;
use Modules\Addons\BankPayments\Models\BankMailbox;
use Modules\Addons\BankPayments\Models\BankMatch;
use Modules\Addons\BankPayments\Models\BankMessage;
use Modules\Addons\BankPayments\Models\BankPayerProfile;
use Modules\Addons\BankPayments\Models\BankTransaction;
use Modules\Addons\BankPayments\Services\InvoiceMatcher;

beforeEach(fn () => ensureBankPaymentsTables());

function bankAssignmentBoot(): void
{
    Setting::set('addon_bank_payments_active', '1', 'addons');
    app()->forgetInstance(AddonManager::class);
    (new AddonServiceProvider(app()))->boot();
    app('router')->getRoutes()->refreshNameLookups();
}

function bankAssignmentTransaction(string $sender = 'MOONSTONE MAGDALENA NOWARA', float $amount = 172.20): BankTransaction
{
    $mailbox = BankMailbox::create([
        'name' => 'Assignment '.uniqid(),
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

    return BankTransaction::create([
        'bank_message_id' => $message->id,
        'bank' => 'nest_bank',
        'operation_type' => 'Wpływ na konto',
        'transaction_date' => '2026-11-27',
        'amount' => $amount,
        'currency' => 'PLN',
        'sender_name' => $sender,
        'reference' => 'Proforma',
        'fingerprint' => hash('sha256', uniqid('', true)),
        'matching_status' => 'unmatched',
    ]);
}

function bankAssignmentInvoice(): array
{
    $client = Client::factory()->create(['company_name' => 'MOONSTONE MAGDALENA NOWARA']);
    $invoice = Invoice::factory()->create([
        'client_id' => $client->id,
        'invoice_num' => 'PRO-NAWARA-'.uniqid(),
        'total' => 172.20,
        'status' => 'unpaid',
        'due_date' => now()->addDays(14),
    ]);

    return [$client, $invoice];
}

it('lets an operator assign an unmatched transfer to an invoice', function () {
    bankAssignmentBoot();
    $this->actingAs(Admin::factory()->create(), 'admin');
    [$client, $invoice] = bankAssignmentInvoice();
    $transaction = bankAssignmentTransaction();

    $this->get(route('admin.bank-payments.transactions.assign', $transaction))
        ->assertOk()
        ->assertSee($client->company_name);

    $this->post(route('admin.bank-payments.transactions.assign.store', $transaction), [
        'invoice_id' => $invoice->id,
    ])->assertSessionHas('success');

    $match = BankMatch::where('bank_transaction_id', $transaction->id)->firstOrFail();

    expect($match->invoice_id)->toBe($invoice->id)
        ->and((float) $match->amount)->toBe(172.20)
        ->and($match->criteria['manual'])->toBeTrue()
        ->and($transaction->fresh()->matching_status)->toBe('review')
        ->and($invoice->fresh()->status)->toBe('unpaid');
});

it('learns a payer after a manual booking and can forget it', function () {
    bankAssignmentBoot();
    AddonSetting::setSetting('bank_payments', 'mode', 'semi_auto');
    $this->actingAs(Admin::factory()->create(), 'admin');
    [, $invoice] = bankAssignmentInvoice();
    $transaction = bankAssignmentTransaction();

    $this->post(route('admin.bank-payments.transactions.assign.store', $transaction), [
        'invoice_id' => $invoice->id,
        'amount' => 172.20,
    ])->assertSessionHas('success');

    $match = BankMatch::where('bank_transaction_id', $transaction->id)->firstOrFail();

    $this->post(route('admin.bank-payments.matches.approve', $match))
        ->assertSessionHas('success');

    $profile = BankPayerProfile::where('client_id', $invoice->client_id)->firstOrFail();

    expect($profile->sender_name_normalized)->toBe('moonstone magdalena nowara')
        ->and($invoice->fresh()->status)->toBe('paid')
        ->and($transaction->fresh()->matching_status)->toBe('imported');

    $this->get(route('admin.bank-payments.payers.index'))
        ->assertOk()
        ->assertSee($profile->sender_name);

    $this->delete(route('admin.bank-payments.payers.destroy', $profile))
        ->assertSessionHas('success');

    expect(BankPayerProfile::find($profile->id))->toBeNull();
});

it('recalculates an existing unmatched transaction', function () {
    bankAssignmentBoot();
    $this->actingAs(Admin::factory()->create(), 'admin');
    [, $invoice] = bankAssignmentInvoice();
    $transaction = bankAssignmentTransaction();

    $this->artisan('pnlcs:bank-payments:rematch', ['--transaction' => $transaction->id])
        ->assertSuccessful();

    expect(BankMatch::where('bank_transaction_id', $transaction->id)->firstOrFail()->invoice_id)
        ->toBe($invoice->id)
        ->and($transaction->fresh()->matching_status)->toBe('review');

    $this->get(route('admin.bank-payments.matches.index'))
        ->assertOk()
        ->assertSee('56%');
});

it('uses a learned payer to propose a future invoice for the same client', function () {
    bankAssignmentBoot();
    AddonSetting::setSetting('bank_payments', 'mode', 'semi_auto');
    $this->actingAs(Admin::factory()->create(), 'admin');
    [$client, $invoice] = bankAssignmentInvoice();
    $transaction = bankAssignmentTransaction();

    $this->post(route('admin.bank-payments.transactions.assign.store', $transaction), [
        'invoice_id' => $invoice->id,
        'amount' => 172.20,
    ]);
    $this->post(route('admin.bank-payments.matches.approve', BankMatch::where('bank_transaction_id', $transaction->id)->firstOrFail()));

    $client->update(['company_name' => 'A new trading name']);

    $next = Invoice::factory()->create([
        'client_id' => $client->id,
        'invoice_num' => 'PRO-NAWARA-'.uniqid(),
        'total' => 88.00,
        'status' => 'unpaid',
        'due_date' => now()->addDays(14),
    ]);
    $nextTransaction = bankAssignmentTransaction('Moonstone Magdalena Nowara', 88.00);

    $result = app(InvoiceMatcher::class)->match($nextTransaction);
    $match = BankMatch::where('bank_transaction_id', $nextTransaction->id)->firstOrFail();

    expect($result['status'])->toBe('review')
        ->and($match->invoice_id)->toBe($next->id)
        ->and($match->criteria['learned_match'])->toBeTrue()
        ->and($match->criteria['client_match'])->toBeTrue();
});
