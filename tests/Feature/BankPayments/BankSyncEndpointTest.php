<?php

use App\Models\AddonSetting;
use App\Models\Admin;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Setting;
use App\Providers\AddonServiceProvider;
use App\Services\AddonManager;
use Carbon\CarbonInterface;
use Modules\Addons\BankPayments\Models\BankImport;
use Modules\Addons\BankPayments\Models\BankMailbox;
use Modules\Addons\BankPayments\Models\BankMatch;
use Modules\Addons\BankPayments\Models\BankMessage;
use Modules\Addons\BankPayments\Services\BankMailboxClient;

class BankPaymentsSyncEndpointClient implements BankMailboxClient
{
    public function test(BankMailbox $mailbox): array
    {
        return ['success' => true, 'message' => 'connected', 'uidvalidity' => 12];
    }

    public function fetch(BankMailbox $mailbox, ?CarbonInterface $since, int $limit): array
    {
        return [
            'uidvalidity' => 12,
            'messages' => [[
                'uid' => 21,
                'raw' => file_get_contents(base_path('tests/Fixtures/BankPayments/nest-bank-incoming-transfer.eml')),
            ]],
            'error' => null,
        ];
    }

    public function markSeen(BankMailbox $mailbox, int $uid): void {}

    public function disconnect(): void {}
}

function bootBankPaymentsAddonForSyncEndpoint(): void
{
    Setting::set('addon_bank_payments_active', '1', 'addons');
    app()->forgetInstance(AddonManager::class);
    (new AddonServiceProvider(app()))->boot();
    app('router')->getRoutes()->refreshNameLookups();
}

it('registers the scheduled sync command while active', function () {
    bootBankPaymentsAddonForSyncEndpoint();

    $this->artisan('pnlcs:bank-payments:sync')
        ->expectsOutput(__('bank-payments::messages.no_active_mailboxes'))
        ->assertSuccessful();
});

it('shows a matched invoice in the matching queue', function () {
    bootBankPaymentsAddonForSyncEndpoint();
    $this->actingAs(Admin::factory()->create(), 'admin');

    $client = Client::factory()->create(['company_name' => 'Przykładowy Kontrahent Sp. z o.o.']);
    Invoice::factory()->create([
        'client_id' => $client->id,
        'invoice_num' => 'PRO-2026/11-000123',
        'total' => 1234.87,
        'status' => 'unpaid',
    ]);

    BankMailbox::create([
        'name' => 'Nest Bank queue',
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

    app()->instance(BankMailboxClient::class, new BankPaymentsSyncEndpointClient);

    $this->post(route('admin.bank-payments.sync'))->assertSessionHas('success');

    $this->get(route('admin.bank-payments.matches.index'))
        ->assertOk()
        ->assertSee('PRO-2026/11-000123');
});

it('approves a queued match from the admin page', function () {
    bootBankPaymentsAddonForSyncEndpoint();
    Setting::set('addon_bank_payments_active', '1', 'addons');
    AddonSetting::setSetting('bank_payments', 'mode', 'semi_auto');
    $this->actingAs(Admin::factory()->create(), 'admin');

    $client = Client::factory()->create(['company_name' => 'Przykładowy Kontrahent Sp. z o.o.']);
    $invoice = Invoice::factory()->create([
        'client_id' => $client->id,
        'invoice_num' => 'PRO-2026/11-000123',
        'total' => 1234.87,
        'status' => 'unpaid',
    ]);

    BankMailbox::create([
        'name' => 'Nest Bank approve',
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

    app()->instance(BankMailboxClient::class, new BankPaymentsSyncEndpointClient);
    $this->post(route('admin.bank-payments.sync'))->assertSessionHas('success');

    $match = BankMatch::firstOrFail();
    $this->post(route('admin.bank-payments.matches.approve', $match), ['amount' => 1234.87])
        ->assertSessionHas('success');

    expect($invoice->fresh()->status)->toBe('paid')
        ->and(BankImport::count())->toBe(1)
        ->and($match->fresh()->status)->toBe('imported');
});

it('runs a manual sync from the admin page', function () {
    bootBankPaymentsAddonForSyncEndpoint();
    $this->actingAs(Admin::factory()->create(), 'admin');

    BankMailbox::create([
        'name' => 'Nest Bank endpoint',
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

    app()->instance(BankMailboxClient::class, new BankPaymentsSyncEndpointClient);

    $this->post(route('admin.bank-payments.sync'))
        ->assertSessionHas('success');

    expect(BankMessage::count())->toBe(1);
});
