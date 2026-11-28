<?php

use App\Models\Admin;
use App\Models\Setting;
use App\Providers\AddonServiceProvider;
use App\Services\AddonManager;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Modules\Addons\BankPayments\Models\BankMailbox;
use Modules\Addons\BankPayments\Services\BankMailboxClient;

class BankPaymentsMailboxTestClient implements BankMailboxClient
{
    public bool $ok = true;

    public function test(BankMailbox $mailbox): array
    {
        return [
            'success' => $this->ok,
            'message' => $this->ok ? 'connected' : 'failed',
            'uidvalidity' => 7,
        ];
    }

    public function fetch(BankMailbox $mailbox, ?CarbonInterface $since, int $limit): array
    {
        return ['uidvalidity' => 7, 'messages' => [], 'error' => null];
    }

    public function markSeen(BankMailbox $mailbox, int $uid): void {}

    public function disconnect(): void {}
}

function bootBankPaymentsAddonForMailbox(): void
{
    Setting::set('addon_bank_payments_active', '1', 'addons');
    app()->forgetInstance(AddonManager::class);
    (new AddonServiceProvider(app()))->boot();
    app('router')->getRoutes()->refreshNameLookups();
}

beforeEach(function () {
    bootBankPaymentsAddonForMailbox();
    $this->actingAs(Admin::factory()->create(), 'admin');
});

it('stores a mailbox password encrypted and never returns it in the form', function () {
    $this->post(route('admin.bank-payments.mailboxes.store'), [
        'name' => 'Nest Bank',
        'host' => 'imap.example.test',
        'port' => 993,
        'encryption' => 'ssl',
        'username' => 'bank@example.test',
        'password' => 'super-secret',
        'folder' => 'INBOX',
        'bank' => 'nest_bank',
        'parser' => 'nest_bank',
        'active' => '1',
        'validate_cert' => '1',
    ])->assertRedirect(route('admin.bank-payments.mailboxes.index'));

    $mailbox = BankMailbox::firstOrFail();

    expect($mailbox->password)->toBe('super-secret')
        ->and(DB::table('bank_mailboxes')->where('id', $mailbox->id)->value('password'))->not->toBe('super-secret');

    $this->get(route('admin.bank-payments.mailboxes.edit', $mailbox))
        ->assertOk()
        ->assertDontSee('super-secret');
});

it('keeps the stored password when the edit form leaves it blank', function () {
    $mailbox = BankMailbox::create([
        'name' => 'Nest Bank',
        'host' => 'imap.example.test',
        'port' => 993,
        'encryption' => 'ssl',
        'username' => 'bank@example.test',
        'password' => 'old-secret',
        'folder' => 'INBOX',
        'bank' => 'nest_bank',
        'parser' => 'nest_bank',
        'active' => true,
        'validate_cert' => true,
    ]);

    $this->put(route('admin.bank-payments.mailboxes.update', $mailbox), [
        'name' => 'Nest Bank renamed',
        'host' => 'imap.example.test',
        'port' => 993,
        'encryption' => 'ssl',
        'username' => 'bank@example.test',
        'password' => '',
        'folder' => 'INBOX',
        'bank' => 'nest_bank',
        'parser' => 'nest_bank',
        'active' => '1',
        'validate_cert' => '1',
    ])->assertRedirect(route('admin.bank-payments.mailboxes.index'));

    expect($mailbox->fresh()->name)->toBe('Nest Bank renamed')
        ->and($mailbox->fresh()->password)->toBe('old-secret');
});

it('reports a mailbox connection result without exposing the password', function () {
    $mailbox = BankMailbox::create([
        'name' => 'Nest Bank',
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

    $client = new BankPaymentsMailboxTestClient;
    app()->instance(BankMailboxClient::class, $client);

    $this->post(route('admin.bank-payments.mailboxes.test', $mailbox))
        ->assertSessionHas('success', 'connected');

    $client->ok = false;

    $this->post(route('admin.bank-payments.mailboxes.test', $mailbox))
        ->assertSessionHas('error', 'failed');
});

it('derives the parser from the selected bank and rejects an unknown bank', function () {
    $this->post(route('admin.bank-payments.mailboxes.store'), [
        'name' => 'Nest Bank selector',
        'host' => 'imap.example.test',
        'port' => 993,
        'encryption' => 'ssl',
        'username' => 'bank@example.test',
        'password' => 'secret',
        'folder' => 'INBOX',
        'bank' => 'nest_bank',
        'active' => '1',
        'validate_cert' => '1',
    ])->assertRedirect(route('admin.bank-payments.mailboxes.index'));

    expect(BankMailbox::where('name', 'Nest Bank selector')->firstOrFail()->parser)->toBe('nest_bank');

    $this->from(route('admin.bank-payments.mailboxes.create'))
        ->post(route('admin.bank-payments.mailboxes.store'), [
            'name' => 'Unknown bank',
            'host' => 'imap.example.test',
            'port' => 993,
            'encryption' => 'ssl',
            'username' => 'bank@example.test',
            'password' => 'secret',
            'folder' => 'INBOX',
            'bank' => 'not_a_bank',
            'active' => '1',
            'validate_cert' => '1',
        ])->assertSessionHasErrors('bank');

    expect(BankMailbox::where('name', 'Unknown bank')->exists())->toBeFalse();
});
