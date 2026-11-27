<?php

use App\Models\AddonSetting;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Setting;
use App\Models\Transaction;
use App\Services\PaymentService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Mail;
use Modules\Addons\BankPayments\Mail\BankPaymentsReviewMail;
use Modules\Addons\BankPayments\Models\BankImport;
use Modules\Addons\BankPayments\Models\BankMailbox;
use Modules\Addons\BankPayments\Models\BankMatch;
use Modules\Addons\BankPayments\Models\BankMessage;
use Modules\Addons\BankPayments\Models\BankTransaction;
use Modules\Addons\BankPayments\Services\BankMailboxClient;
use Modules\Addons\BankPayments\Services\BankSyncService;

class BankPaymentsSyncTestClient implements BankMailboxClient
{
    /** @var array<int, array{uid: int, raw: string}> */
    public array $messages = [];

    public ?CarbonInterface $since = null;

    public int $limit = 0;

    /** @var array<int, int> */
    public array $seen = [];

    public function test(BankMailbox $mailbox): array
    {
        return ['success' => true, 'message' => 'connected', 'uidvalidity' => 11];
    }

    public function fetch(BankMailbox $mailbox, ?CarbonInterface $since, int $limit): array
    {
        $this->since = $since;
        $this->limit = $limit;

        return ['uidvalidity' => 11, 'messages' => $this->messages, 'error' => null];
    }

    public function markSeen(BankMailbox $mailbox, int $uid): void
    {
        $this->seen[] = $uid;
    }

    public function disconnect(): void {}
}

function bankPaymentsSyncMailbox(): BankMailbox
{
    return BankMailbox::create([
        'name' => 'Nest Bank '.uniqid(),
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
}

function bankPaymentsSyncRaw(): string
{
    return file_get_contents(base_path('tests/Fixtures/BankPayments/nest-bank-example.eml'));
}

function bankPaymentsRealIncomingRaw(): string
{
    return file_get_contents(base_path('tests/Fixtures/BankPayments/nest-bank-incoming-transfer.eml'));
}

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-11-27 10:00:00'));
});

it('stores a parsed transaction but leaves the core invoice/transaction tables alone', function () {
    $mailbox = bankPaymentsSyncMailbox();
    $client = new BankPaymentsSyncTestClient;
    $client->messages = [['uid' => 5, 'raw' => bankPaymentsSyncRaw()]];

    app()->instance(BankMailboxClient::class, $client);
    $payments = Mockery::mock(PaymentService::class);
    $payments->shouldNotReceive('applyPayment');
    app()->instance(PaymentService::class, $payments);

    $result = app(BankSyncService::class)->sync($mailbox);

    expect($result['status'])->toBe('ok')
        ->and($result['fetched'])->toBe(1)
        ->and($result['parsed'])->toBe(1)
        ->and($result['new_transactions'])->toBe(1)
        ->and($client->limit)->toBe(100)
        ->and($client->since?->toDateString())->toBe('2026-11-20')
        ->and($client->seen)->toBe([5]);

    $message = BankMessage::firstOrFail();
    $transaction = BankTransaction::firstOrFail();

    expect($message->status)->toBe('parsed')
        ->and($message->subject)->toBe('Przelew przychodzacy')
        ->and($transaction->amount)->toBe('1234.56')
        ->and($transaction->currency)->toBe('PLN')
        ->and($transaction->reference)->toBe('Faktura R2026/10/NR-161')
        ->and(Transaction::count())->toBe(0);

    // The mailbox now continues from the UID it has seen, so the next run does
    // not re-read the whole history.
    expect($mailbox->fresh()->last_uid)->toBe(5)
        ->and($mailbox->fresh()->last_uidvalidity)->toBe(11);
});

it('stores the real signed HTML incoming-transfer format', function () {
    $mailbox = bankPaymentsSyncMailbox();
    $client = new BankPaymentsSyncTestClient;
    $client->messages = [['uid' => 7, 'raw' => bankPaymentsRealIncomingRaw()]];

    app()->instance(BankMailboxClient::class, $client);

    $result = app(BankSyncService::class)->sync($mailbox);
    $transaction = BankTransaction::firstOrFail();

    expect($result['status'])->toBe('ok')
        ->and($transaction->amount)->toBe('1234.87')
        ->and($transaction->currency)->toBe('PLN')
        ->and($transaction->reference)->toBe('PRO-2026/11-000123')
        ->and($transaction->sender_name)->toBe('Przykładowy Kontrahent Sp. z o.o.');
});

it('matches the real incoming transaction to an open invoice without booking it', function () {
    $client = Client::factory()->create(['company_name' => 'Przykładowy Kontrahent Sp. z o.o.']);
    $invoice = Invoice::factory()->create([
        'client_id' => $client->id,
        'invoice_num' => 'PRO-2026/11-000123',
        'total' => 1234.87,
        'status' => 'unpaid',
    ]);

    $mailbox = bankPaymentsSyncMailbox();
    $clientData = new BankPaymentsSyncTestClient;
    $clientData->messages = [['uid' => 8, 'raw' => bankPaymentsRealIncomingRaw()]];

    app()->instance(BankMailboxClient::class, $clientData);

    $result = app(BankSyncService::class)->sync($mailbox);
    $transaction = BankTransaction::firstOrFail();

    expect($result['status'])->toBe('ok')
        ->and($transaction->matching_status)->toBe('auto_ready')
        ->and($transaction->matching_score)->toBe(90)
        ->and(BankMatch::count())->toBe(1)
        ->and(BankMatch::first()->invoice_id)->toBe($invoice->id)
        ->and($invoice->fresh()->status)->toBe('unpaid')
        ->and(Transaction::count())->toBe(0);
});

it('auto-books a full match through the sync pipeline', function () {
    Setting::set('addon_bank_payments_active', '1', 'addons');
    AddonSetting::setSetting('bank_payments', 'mode', 'auto');

    $client = Client::factory()->create(['company_name' => 'Przykładowy Kontrahent Sp. z o.o.']);
    $invoice = Invoice::factory()->create([
        'client_id' => $client->id,
        'invoice_num' => 'PRO-2026/11-000123',
        'total' => 1234.87,
        'status' => 'unpaid',
    ]);

    $mailbox = bankPaymentsSyncMailbox();
    $fake = new BankPaymentsSyncTestClient;
    $fake->messages = [['uid' => 9, 'raw' => bankPaymentsRealIncomingRaw()]];

    app()->instance(BankMailboxClient::class, $fake);

    $result = app(BankSyncService::class)->sync($mailbox);

    expect($result['status'])->toBe('ok')
        ->and($invoice->fresh()->status)->toBe('paid')
        ->and(BankTransaction::firstOrFail()->matching_status)->toBe('imported')
        ->and(BankImport::count())->toBe(1)
        ->and(Transaction::count())->toBe(1);
});

it('notifies the operator when a match needs approval', function () {
    Mail::fake();
    Setting::set('SystemEmailAddress', 'ops@example.test');
    AddonSetting::setSetting('bank_payments', 'mode', 'semi_auto');

    $client = Client::factory()->create(['company_name' => 'Przykładowy Kontrahent Sp. z o.o.']);
    Invoice::factory()->create([
        'client_id' => $client->id,
        'invoice_num' => 'PRO-2026/11-000123',
        'total' => 1234.87,
        'status' => 'unpaid',
    ]);

    $mailbox = bankPaymentsSyncMailbox();
    $fake = new BankPaymentsSyncTestClient;
    $fake->messages = [['uid' => 11, 'raw' => bankPaymentsRealIncomingRaw()]];

    app()->instance(BankMailboxClient::class, $fake);

    app(BankSyncService::class)->sync($mailbox);

    Mail::assertQueued(BankPaymentsReviewMail::class, fn (BankPaymentsReviewMail $mail): bool => $mail->hasTo('ops@example.test')
        && $mail->matchCount === 1);
});

it('does not create a second message or transaction for the same UID', function () {
    $mailbox = bankPaymentsSyncMailbox();
    $client = new BankPaymentsSyncTestClient;
    $client->messages = [['uid' => 5, 'raw' => bankPaymentsSyncRaw()]];

    app()->instance(BankMailboxClient::class, $client);

    $sync = app(BankSyncService::class);
    $sync->sync($mailbox);
    $second = $sync->sync($mailbox->fresh());

    expect($second['duplicates'])->toBe(1)
        ->and(BankMessage::count())->toBe(1)
        ->and(BankTransaction::count())->toBe(1)
        ->and($client->since)->toBeNull();
});

it('marks a message unparsed and keeps the sync usable when the parser finds no amount', function () {
    $mailbox = bankPaymentsSyncMailbox();
    $client = new BankPaymentsSyncTestClient;
    $client->messages = [[
        'uid' => 9,
        'raw' => "From: bank@example.test\nSubject: Wplyw na konto\n\nBank: Nest Bank\nNadawca: Jan Kowalski\n",
    ]];

    app()->instance(BankMailboxClient::class, $client);

    $result = app(BankSyncService::class)->sync($mailbox);

    expect($result['status'])->toBe('partial')
        ->and($result['errors'])->toBe(1)
        ->and(BankMessage::firstOrFail()->status)->toBe('unparsed')
        ->and(BankTransaction::count())->toBe(0);
});

it('ignores a bank notification that is not an incoming transfer', function () {
    $mailbox = bankPaymentsSyncMailbox();
    $client = new BankPaymentsSyncTestClient;
    $client->messages = [[
        'uid' => 10,
        'raw' => "From: bank@example.test\nSubject: Płatność BLIK\nContent-Type: text/html; charset=UTF-8\n\n"
            .'<p>dnia 27.11.2026 nastąpiła płatność BLIK w kwocie 49,99 PLN.</p>',
    ]];

    app()->instance(BankMailboxClient::class, $client);

    $result = app(BankSyncService::class)->sync($mailbox);

    expect($result['status'])->toBe('ok')
        ->and($result['errors'])->toBe(0)
        ->and(BankMessage::firstOrFail()->status)->toBe('ignored')
        ->and(BankTransaction::count())->toBe(0);
});
