<?php

use App\Contracts\GatewayModuleInterface;
use App\Contracts\TokenizableGatewayInterface;
use App\Enums\ChargeAttemptState;
use App\Enums\ChargeClaim;
use App\Enums\InvoiceStatus;
use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\CancellationRequest;
use App\Models\GatewaySettings;
use App\Models\Invoice;
use App\Models\InvoiceChargeAttempt as Attempt;
use App\Models\InvoiceItem;
use App\Models\PaymentMethod;
use App\Models\Service;
use App\Models\Setting;
use App\Models\Transaction;
use App\Services\InvoiceService;
use App\Services\Module\ModuleRegistry;
use App\Services\PaymentService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Sleep;

/*
 * What happens when the charge does not go cleanly.
 *
 * The file beside this one proves the charger takes the right money from the
 * right people. This one is about the edges it meets on a bad morning: a
 * process killed with a charge in flight, a database that throws after the card
 * has been charged, an invoice cancelled while the run is walking the list, a
 * service the customer ended, and the invoices that can never be charged again
 * quietly filling the window so that the ones that can are never looked at.
 *
 * Every test here failed before the change it is attached to. They are the
 * three reviewers' own probes, rewritten to stay.
 */

beforeEach(function () {
    Sleep::fake();
});

afterEach(function () {
    Sleep::fake(false);
});

function recOn(array $settings = []): void
{
    Setting::set('AutoChargeEnabled', '1');

    foreach ($settings as $key => $value) {
        Setting::set($key, (string) $value);
    }
}

/** @param  callable|null  $responder  fn (Invoice, PaymentMethod, float, int $call): array */
function recGateway(?callable $responder = null): ArrayObject
{
    $calls = new ArrayObject;

    $responder ??= fn ($invoice, $method, $amount) => [
        'success' => true,
        'status' => 'succeeded',
        'transaction_id' => 'pi_'.$invoice->id,
        'amount' => $amount,
    ];

    $fake = Mockery::mock(GatewayModuleInterface::class.', '.TokenizableGatewayInterface::class);
    $fake->shouldReceive('getModuleName')->andReturn('fake');
    $fake->shouldReceive('isTokenised')->andReturn(true);
    $fake->shouldReceive('getConfigFields')->andReturn([]);
    $fake->shouldReceive('chargeStoredMethod')->andReturnUsing(
        function ($invoice, $method, $amount, $params = []) use ($calls, $responder) {
            $amount = round((float) $amount, 2);
            $calls[] = ['invoice' => $invoice->id, 'method' => $method->id, 'amount' => $amount];

            return $responder($invoice, $method, $amount, count($calls));
        }
    );

    app()->instance(GatewayModuleInterface::class, $fake);
    app(ModuleRegistry::class)->registerGateway('stripe', GatewayModuleInterface::class);

    GatewaySettings::updateOrCreate(['gateway' => 'stripe', 'setting' => 'active'], ['value' => '1']);

    return $calls;
}

function recInvoice(float $total = 100.0, array $attributes = []): Invoice
{
    return Invoice::factory()->create($attributes + [
        'status' => InvoiceStatus::Unpaid->value,
        'subtotal' => $total,
        'total' => $total,
        'due_date' => now()->addDay(),
    ]);
}

function recCard(Invoice $invoice, array $attributes = []): PaymentMethod
{
    return PaymentMethod::create($attributes + [
        'client_id' => $invoice->client_id,
        'gateway_name' => 'stripe',
        'payment_type' => 'cc',
        'description' => 'Visa 4242',
        'remote_token' => 'pm_'.fake()->unique()->numerify('##########'),
        'gateway_customer_id' => 'cus_'.fake()->unique()->numerify('##########'),
        'last_four' => '4242',
        'expiry_date' => '2030-07',
        'status' => PaymentMethod::STATUS_ACTIVE,
    ]);
}

/** @return array{0: Invoice, 1: PaymentMethod} */
function recReady(float $total = 100.0, array $attributes = []): array
{
    $invoice = recInvoice($total, $attributes);

    return [$invoice, recCard($invoice)];
}

function recRun(array $arguments = []): void
{
    Artisan::call('pnlcs:auto-charge', $arguments);
}

/** An attempt a killed run left behind, claimed $minutes ago. */
function recAbandoned(Invoice $invoice, PaymentMethod $card, float $amount, int $minutes, ?string $transactionId = null): Attempt
{
    return Attempt::create([
        'invoice_id' => $invoice->id,
        'payment_method_id' => $card->id,
        'state' => ChargeAttemptState::InFlight,
        'attempts' => 1,
        'amount' => $amount,
        'currency' => shop_currency_code(),
        'claimed_at' => now()->subMinutes($minutes),
        'last_transaction_id' => $transactionId,
    ]);
}

function recAdmin(): Admin
{
    return Admin::factory()->create(['role_id' => AdminRole::factory()->fullAdmin()->create()->id]);
}

// ---------------------------------------------------------------------------
// B1  The crash replay has to be reachable on the schedule that ships
// ---------------------------------------------------------------------------

test('the rescue runs often enough for a replay to still be provable', function () {
    // THE ARITHMETIC THAT MADE CRASH RECOVERY UNREACHABLE. A replay is only
    // provably the same request while Stripe still holds the idempotency key —
    // "at least 24 hours" — so the window is 23. Consecutive daily runs are 24
    // hours apart, which means the rescue was always looking an hour after the
    // window it depends on had closed, on every installation, for ever. The
    // window cannot be widened past the gateway's retention; the cadence is
    // what had to move.
    $auto = collect(app(Illuminate\Console\Scheduling\Schedule::class)->events())
        ->filter(fn ($event) => str_contains((string) $event->command, 'pnlcs:auto-charge'));

    expect($auto)->not->toBeEmpty();

    $shortest = $auto->map(function ($event) {
        $cron = new Cron\CronExpression($event->expression);
        $first = $cron->getNextRunDate('2026-01-01 00:00:00');

        return $cron->getNextRunDate($first)->getTimestamp() - $first->getTimestamp();
    })->min();

    expect($shortest)->toBeLessThan(Attempt::REPLAY_WINDOW_SECONDS);
});

test('an attempt a killed run left behind is replayed by the rescue sweep, and credited once', function () {
    recOn();
    $calls = recGateway();
    [$invoice, $card] = recReady(100.0);

    // The run was killed twenty minutes ago with the charge sent. The lease has
    // gone cold (900s) and the money may or may not have moved.
    recAbandoned($invoice, $card, 100.0, 20);

    recRun(['--rescue' => true]);

    $row = Attempt::forInvoice($invoice);

    expect($calls->count())->toBe(1)
        // The same request at the gateway, answered out of its own record —
        // so not a second attempt here either.
        ->and($row->attempts)->toBe(1)
        ->and($row->state)->toBe(ChargeAttemptState::Succeeded)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Paid->value)
        ->and(Transaction::where('invoice_id', $invoice->id)->count())->toBe(1);
});

test('the rescue sweep collects nothing new', function () {
    recOn();
    $calls = recGateway();
    [$invoice] = recReady(100.0);

    // An ordinary due invoice, and no abandoned attempt anywhere. --rescue is
    // for finishing what was started, not for collecting between mornings:
    // charging here would mean a stored card being presented ninety-six times a
    // day instead of once.
    recRun(['--rescue' => true]);

    expect($calls->count())->toBe(0)
        ->and(Attempt::forInvoice($invoice))->toBeNull()
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Unpaid->value);
});

// ---------------------------------------------------------------------------
// B2  A fault after the card is charged must not lose the payment
// ---------------------------------------------------------------------------

/** A PaymentService that throws where a deadlock on its lockForUpdate would. */
function recBreakPaymentService(): void
{
    $payments = Mockery::mock(PaymentService::class)->makePartial();
    $payments->shouldReceive('applyPayment')->andThrow(
        new Illuminate\Database\QueryException(
            'mysql',
            'select * from `invoices` where `id` = ? for update',
            [1],
            new PDOException('SQLSTATE[40001]: Serialization failure: 1213 Deadlock found when trying to get lock')
        )
    );

    app()->instance(PaymentService::class, $payments);
}

test('a fault after the card is charged does not lose the payment', function () {
    recOn();
    $calls = recGateway();
    [$invoice, $card] = recReady(100.0);

    recBreakPaymentService();

    recRun();

    $row = Attempt::forInvoice($invoice);

    // The card WAS charged and the ledger has nothing. What must be true is
    // that PNLCS knows it: the gateway's own reference for the money is on the
    // attempt row, written before the credit was attempted.
    expect($calls->count())->toBe(1)
        ->and(Transaction::where('invoice_id', $invoice->id)->count())->toBe(0)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Unpaid->value)
        ->and($row->state)->toBe(ChargeAttemptState::InFlight)
        ->and($row->last_transaction_id)->toBe('pi_'.$invoice->id)
        ->and((float) $row->amount)->toBe(100.0);

    // Twenty minutes later the database is healthy and the sweep finishes the
    // job — from our own records, without asking the gateway anything.
    app()->forgetInstance(PaymentService::class);
    $this->travel(20)->minutes();

    recRun(['--rescue' => true]);

    $row = Attempt::forInvoice($invoice);

    expect($calls->count())->toBe(1)
        ->and($row->state)->toBe(ChargeAttemptState::Succeeded)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Paid->value)
        ->and(Transaction::where('invoice_id', $invoice->id)->count())->toBe(1)
        ->and(Transaction::where('invoice_id', $invoice->id)->value('transaction_id'))->toBe('pi_'.$invoice->id);
});

test('the webhook and the rescue never both credit the same payment', function () {
    recOn();
    $calls = recGateway();
    [$invoice, $card] = recReady(100.0);

    recBreakPaymentService();
    recRun();

    expect(Attempt::forInvoice($invoice)->last_transaction_id)->toBe('pi_'.$invoice->id);

    // Stripe delivers payment_intent.succeeded in the meantime and
    // GatewayWebhookController credits it — the same door, the same (gateway,
    // transaction id) pair.
    app()->forgetInstance(PaymentService::class);
    app(PaymentService::class)->applyPayment($invoice->fresh(), 'stripe', 'pi_'.$invoice->id, 100.0);

    $this->travel(20)->minutes();
    recRun(['--rescue' => true]);

    expect(Transaction::where('invoice_id', $invoice->id)->count())->toBe(1)
        ->and(Attempt::forInvoice($invoice)->state)->toBe(ChargeAttemptState::Succeeded)
        ->and((float) $invoice->fresh()->total)->toBe(100.0)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Paid->value)
        ->and((float) $invoice->client->fresh()->credit)->toBe(0.0);
});

// ---------------------------------------------------------------------------
// B3  One bad invoice must not abandon the rest of the morning
// ---------------------------------------------------------------------------

test('an exception on one invoice does not abandon every later invoice', function () {
    recOn();

    [$first, $firstCard] = recReady(100.0, ['due_date' => now()->subDays(3)]);
    [$second, $secondCard] = recReady(50.0, ['due_date' => now()->subDay()]);

    $calls = recGateway(function ($invoice, $method, $amount) use ($first) {
        if ((int) $invoice->id === (int) $first->id) {
            throw new RuntimeException('the gateway module blew up');
        }

        return ['success' => true, 'status' => 'succeeded', 'transaction_id' => 'pi_'.$invoice->id, 'amount' => $amount];
    });

    recRun();

    // The oldest debt is attempted first and throws. Everybody behind it in the
    // list still gets collected — with the shipped cap of a thousand invoices,
    // one throw on invoice #3 used to mean #4 to #1000 were never looked at,
    // and every one of those customers was suspended at 07:00 for a bill the
    // shop was configured to pay.
    expect($calls->count())->toBe(2)
        ->and($second->fresh()->status)->toBe(InvoiceStatus::Paid->value)
        ->and($first->fresh()->status)->toBe(InvoiceStatus::Unpaid->value)
        ->and(Artisan::output())->toContain('threw while being collected');
});

// ---------------------------------------------------------------------------
// B4  The snapshot the run started from is not consent
// ---------------------------------------------------------------------------

test('an invoice cancelled while the run is in progress is not charged', function () {
    recOn();

    [$first, $firstCard] = recReady(100.0, ['due_date' => now()->subDays(3)]);
    [$second, $secondCard] = recReady(100.0, ['due_date' => now()->subDay()]);

    $calls = recGateway(function ($invoice, $method, $amount) use ($first, $second) {
        if ((int) $invoice->id === (int) $first->id) {
            // The operator calls the second one off while the run is walking
            // the list. At 500ms a charge and a thousand invoices, the last one
            // is acted on eight minutes after the list was read.
            Invoice::whereKey($second->id)->update(['status' => InvoiceStatus::Cancelled->value]);
        }

        return ['success' => true, 'status' => 'succeeded', 'transaction_id' => 'pi_'.$invoice->id, 'amount' => $amount];
    });

    recRun();

    expect($calls->count())->toBe(1)
        ->and($calls[0]['invoice'])->toBe($first->id)
        ->and(Transaction::where('invoice_id', $second->id)->count())->toBe(0)
        ->and(Attempt::forInvoice($second))->toBeNull()
        ->and((float) $second->client->fresh()->credit)->toBe(0.0);
});

test('a customer who switches automatic payment off while the run is in progress is not charged', function () {
    recOn();

    [$first] = recReady(100.0, ['due_date' => now()->subDays(3)]);
    [$second] = recReady(100.0, ['due_date' => now()->subDay()]);

    $calls = recGateway(function ($invoice, $method, $amount) use ($first, $second) {
        if ((int) $invoice->id === (int) $first->id) {
            $second->client->update(['auto_charge' => false]);
        }

        return ['success' => true, 'status' => 'succeeded', 'transaction_id' => 'pi_'.$invoice->id, 'amount' => $amount];
    });

    recRun();

    expect($calls->count())->toBe(1)
        ->and($calls[0]['invoice'])->toBe($first->id)
        ->and($second->fresh()->status)->toBe(InvoiceStatus::Unpaid->value);
});

test('credit applied while the run is in progress is charged at the fresh total', function () {
    recOn();

    [$first] = recReady(100.0, ['due_date' => now()->subDays(3)]);
    [$second] = recReady(100.0, ['due_date' => now()->subDay()]);

    $calls = recGateway(function ($invoice, $method, $amount) use ($first, $second) {
        if ((int) $invoice->id === (int) $first->id) {
            $second->client->update(['credit' => 40.0]);
            app(InvoiceService::class)->applyCredit($second->fresh(), 40.0);
        }

        return ['success' => true, 'status' => 'succeeded', 'transaction_id' => 'pi_'.$invoice->id, 'amount' => $amount];
    });

    recRun();

    // applyCredit lowers invoices.total and writes no transaction, so a charger
    // working from the list it read minutes ago asks the card for money the
    // customer no longer owes.
    expect($calls->count())->toBe(2)
        ->and($calls[1]['amount'])->toBe(60.0)
        ->and($second->fresh()->status)->toBe(InvoiceStatus::Paid->value);
});

// ---------------------------------------------------------------------------
// B5  A service the customer ended is not collected for
// ---------------------------------------------------------------------------

/** An invoice that bills one hosting service. */
function recServiceInvoice(string $serviceStatus = 'active'): array
{
    [$invoice, $card] = recReady(100.0);

    $service = Service::factory()->create([
        'client_id' => $invoice->client_id,
        'status' => $serviceStatus,
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'client_id' => $invoice->client_id,
        'type' => 'Hosting',
        'rel_id' => $service->id,
        'description' => 'Hosting renewal',
        'amount' => 100.0,
        'taxed' => false,
    ]);

    return [$invoice, $card, $service];
}

test('a service the customer has asked to cancel is not collected for', function () {
    recOn();
    $calls = recGateway();

    [$invoice, $card, $service] = recServiceInvoice();

    // InvoiceGenerationService:51 refuses to RAISE an invoice for this service.
    // The invoice was raised a fortnight before the customer asked, and the
    // charger had no notion of services at all.
    CancellationRequest::create([
        'service_id' => $service->id,
        'type' => 'End of Billing Period',
        'reason' => 'Not needed any more',
        'processed_at' => null,
    ]);

    recRun();

    expect($calls->count())->toBe(0)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Unpaid->value)
        ->and(Attempt::forInvoice($invoice))->toBeNull();
});

test('a service that has already been cancelled and terminated is not collected for', function () {
    recOn();
    $calls = recGateway();

    [$invoice, $card, $service] = recServiceInvoice('cancelled');

    CancellationRequest::create([
        'service_id' => $service->id,
        'type' => 'Immediate',
        'reason' => 'Done with it',
        'processed_at' => now()->subDay(),
    ]);

    // pnlcs:process-cancellations ended the service at 02:00 and never touched
    // the outstanding invoice; this used to take the money at 06:45.
    recRun();

    expect($calls->count())->toBe(0)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Unpaid->value);
});

test('an invoice for a service that is still running is still collected', function () {
    recOn();
    $calls = recGateway();

    [$invoice] = recServiceInvoice();

    recRun();

    expect($calls->count())->toBe(1)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Paid->value);
});

// ---------------------------------------------------------------------------
// B6  Invoices that can never be charged must not fill the window
// ---------------------------------------------------------------------------

test('invoices that can never be charged again do not starve the ones that can', function () {
    recOn();
    $calls = recGateway();

    [$live, $card] = recReady(100.0);

    // Two invoices given up on three hundred days ago, for the same client and
    // the same card. They match every clause of the candidate query, they are
    // the oldest, and the query takes twice the run limit in due-date order —
    // so at --limit 1 they fill the window and the invoice that would have been
    // paid this morning is never even loaded. Nothing counts it, because
    // nothing has seen it.
    foreach ([300, 299] as $daysAgo) {
        $dead = recInvoice(250.0, [
            'client_id' => $live->client_id,
            'status' => InvoiceStatus::Overdue->value,
            'due_date' => now()->subDays($daysAgo),
        ]);

        Attempt::create([
            'invoice_id' => $dead->id,
            'payment_method_id' => $card->id,
            'state' => ChargeAttemptState::Exhausted,
            'attempts' => 3,
            'amount' => 250.0,
            'currency' => shop_currency_code(),
        ]);
    }

    recRun(['--limit' => 1]);

    expect($calls->count())->toBe(1)
        ->and($calls[0]['invoice'])->toBe($live->id)
        ->and($live->fresh()->status)->toBe(InvoiceStatus::Paid->value);
});

test('a card the customer has replaced still reopens an invoice that was given up on', function () {
    recOn();
    $calls = recGateway();

    [$invoice, $dead] = recReady(100.0);

    Attempt::create([
        'invoice_id' => $invoice->id,
        'payment_method_id' => $dead->id,
        'state' => ChargeAttemptState::Exhausted,
        'attempts' => 3,
        'amount' => 100.0,
        'currency' => shop_currency_code(),
    ]);

    // The exclusion above must not cost the customer the one thing that gets
    // them collected again: a different card is a different question.
    $dead->update(['status' => PaymentMethod::STATUS_REQUIRES_UPDATE]);
    recCard($invoice, ['is_default' => true]);

    recRun();

    expect($calls->count())->toBe(1)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Paid->value)
        ->and(Attempt::forInvoice($invoice)->attempts)->toBe(1);
});

// ---------------------------------------------------------------------------
// B7  needs_review has to reach a person, and a person has to be able to end it
// ---------------------------------------------------------------------------

/** An abandoned charge whose amount has since moved, which is unreplayable. */
function recUnprovable(): array
{
    [$invoice, $card] = recReady(100.0);
    recAbandoned($invoice, $card, 100.0, 20);

    // pnlcs:apply-late-fees ran between the crash and now. The amount is inside
    // the gateway's idempotency key, so asking for 110 would be a brand new
    // request on top of a 100 that may already have gone through.
    $invoice->update(['total' => 110.00]);

    return [$invoice, $card];
}

test('an invoice parked for a person is announced, not folded in with the ones that were paid', function () {
    recOn();
    $calls = recGateway();
    [$invoice] = recUnprovable();

    $sent = 0;
    Illuminate\Support\Facades\Event::listen(Illuminate\Mail\Events\MessageSending::class, function () use (&$sent) { $sent++; });
    Setting::set('SystemEmailAddress', 'ops@example.test');

    recRun(['--rescue' => true]);

    $output = Artisan::output();

    expect($calls->count())->toBe(0)
        ->and(Attempt::forInvoice($invoice)->state)->toBe(ChargeAttemptState::NeedsReview)
        // Said in the words an operator can act on, naming the invoice, and NOT
        // counted inside "already finished with" beside the invoices that were
        // paid — which is how it read before.
        ->and($output)->toContain('NEED A PERSON')
        ->and($output)->toContain('#'.$invoice->id)
        // And an email, the same door RegistrarBalanceCheckCommand uses for the
        // other "somebody has to act" alert in this codebase.
        ->and($sent)->toBe(1);
});

test('a parked invoice is announced once, not every fifteen minutes', function () {
    recOn();
    recGateway();
    [$invoice] = recUnprovable();

    $sent = 0;
    Illuminate\Support\Facades\Event::listen(Illuminate\Mail\Events\MessageSending::class, function () use (&$sent) { $sent++; });
    Setting::set('SystemEmailAddress', 'ops@example.test');

    recRun(['--rescue' => true]);
    $this->travel(15)->minutes();
    recRun(['--rescue' => true]);
    $this->travel(15)->minutes();
    recRun(['--rescue' => true]);

    expect($sent)->toBe(1);
});

test('the operator is shown how many charges need a person, and can get to them', function () {
    recOn();
    recGateway();
    [$invoice] = recUnprovable();

    recRun(['--rescue' => true]);

    $admin = recAdmin();

    $dashboard = $this->actingAs($admin, 'admin')->get(route('admin.dashboard'))->assertOk()->getContent();

    expect($dashboard)->toContain(__('admin.dashboard.charges_need_review'))
        ->and($dashboard)->toContain('status=charge_review');

    $list = $this->actingAs($admin, 'admin')
        ->get(route('admin.invoices.index', ['status' => 'charge_review']))
        ->assertOk()->getContent();

    expect($list)->toContain($invoice->invoice_num);

    $page = $this->actingAs($admin, 'admin')->get(route('admin.invoices.show', $invoice))->assertOk()->getContent();

    expect($page)->toContain(__('admin.invoices.charge_review_title'))
        ->and($page)->toContain(__('admin.invoices.charge_review_release'));
});

test('an operator who has checked the gateway can put the invoice back into collection', function () {
    recOn();
    $calls = recGateway();
    [$invoice] = recUnprovable();

    recRun(['--rescue' => true]);

    expect(Attempt::forInvoice($invoice)->state)->toBe(ChargeAttemptState::NeedsReview);

    // Before this existed, needs_review was the end of the road: nothing in the
    // tree read it, nothing cleared it, and the invoice was out of automatic
    // collection for ever even once the operator had established that no money
    // had moved.
    $this->actingAs(recAdmin(), 'admin')
        ->post(route('admin.invoices.charge-review.release', $invoice))
        ->assertRedirect();

    expect(Attempt::forInvoice($invoice))->toBeNull();

    recRun();

    expect($calls->count())->toBe(1)
        ->and($calls[0]['amount'])->toBe(110.0)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Paid->value);
});

test('nothing but a review can be released, and a released invoice starts clean', function () {
    recOn();
    recGateway();
    [$invoice, $card] = recReady(100.0);

    // A scheduled retry is live state: releasing it would hand the invoice
    // straight back with the customer's attempt count reset, and an in-flight
    // row is the very thing that stops a second charge.
    Attempt::create([
        'invoice_id' => $invoice->id,
        'payment_method_id' => $card->id,
        'state' => ChargeAttemptState::Scheduled,
        'attempts' => 2,
        'amount' => 100.0,
        'currency' => shop_currency_code(),
        'next_attempt_at' => now()->addDays(2),
    ]);

    $this->actingAs(recAdmin(), 'admin')
        ->post(route('admin.invoices.charge-review.release', $invoice))
        ->assertRedirect();

    expect(Attempt::forInvoice($invoice))->not->toBeNull()
        ->and(Attempt::forInvoice($invoice)->attempts)->toBe(2);
});

// ---------------------------------------------------------------------------
// The edges of the rescue itself
// ---------------------------------------------------------------------------

test('an abandoned attempt over an invoice somebody else settled is left for a person, not called a card success', function () {
    recOn();
    $calls = recGateway();
    [$invoice, $card] = recReady(100.0);

    recAbandoned($invoice, $card, 100.0, 40);

    // An admin took a bank transfer for it while our charge was in flight. That
    // says nothing whatever about whether the card was charged as well — and
    // stamping the transfer's reference onto the row and calling it a card
    // success is how a customer who paid twice becomes invisible.
    app(PaymentService::class)->applyPayment($invoice->fresh(), 'banktransfer', 'BT-9911', 100.0);

    recRun(['--rescue' => true]);

    $row = Attempt::forInvoice($invoice);

    expect($calls->count())->toBe(0)
        ->and($row->state)->toBe(ChargeAttemptState::NeedsReview)
        ->and($row->last_message)->toContain('banktransfer')
        ->and($row->last_transaction_id)->not->toBe('BT-9911');
});

test('an abandoned attempt over an invoice the same gateway settled is closed without touching the card', function () {
    recOn();
    $calls = recGateway();
    [$invoice, $card] = recReady(100.0);

    recAbandoned($invoice, $card, 100.0, 40);

    app(PaymentService::class)->applyPayment($invoice->fresh(), 'stripe', 'pi_webhook', 100.0);

    recRun(['--rescue' => true]);

    $row = Attempt::forInvoice($invoice);

    expect($calls->count())->toBe(0)
        ->and($row->state)->toBe(ChargeAttemptState::Succeeded)
        ->and($row->last_transaction_id)->toBe('pi_webhook')
        ->and(Transaction::where('invoice_id', $invoice->id)->count())->toBe(1);
});

test('an abandoned attempt on an invoice the customer has since opted out of is never replayed', function () {
    recOn();
    $calls = recGateway();
    [$invoice, $card] = recReady(100.0);

    recAbandoned($invoice, $card, 100.0, 20);

    // A replay is not free here. If the original request never reached the
    // gateway, the replay IS the first charge — and it would be a charge on
    // somebody who has said stop.
    $invoice->client->update(['auto_charge' => false]);

    recRun(['--rescue' => true]);

    expect($calls->count())->toBe(0)
        ->and(Attempt::forInvoice($invoice)->state)->toBe(ChargeAttemptState::NeedsReview)
        ->and(Attempt::forInvoice($invoice)->last_message)->toContain('opted_out');
});

test('a dry run still writes nothing, even with an abandoned attempt waiting', function () {
    recOn();
    $calls = recGateway();
    [$invoice, $card] = recReady(100.0);

    $row = recAbandoned($invoice, $card, 100.0, 40);
    app(PaymentService::class)->applyPayment($invoice->fresh(), 'stripe', 'pi_webhook', 100.0);

    recRun(['--dry-run' => true]);

    expect($calls->count())->toBe(0)
        ->and(Attempt::forInvoice($invoice)->state)->toBe(ChargeAttemptState::InFlight)
        ->and(Attempt::forInvoice($invoice)->claimed_at?->timestamp)->toBe($row->claimed_at->timestamp);
});

// ---------------------------------------------------------------------------
// Invariant zero: with the switch off, none of this exists
// ---------------------------------------------------------------------------

test('with the switch off the rescue sweep reads nothing either', function () {
    $calls = recGateway();
    [$invoice, $card] = recReady(100.0);
    recAbandoned($invoice, $card, 100.0, 40);

    $queries = [];
    Illuminate\Support\Facades\DB::listen(function ($query) use (&$queries) {
        $queries[] = $query->sql;
    });

    recRun(['--rescue' => true]);

    $touched = array_values(array_filter(
        $queries,
        fn (string $sql) => ! str_contains($sql, 'settings')
    ));

    expect($touched)->toBe([])
        ->and($calls->count())->toBe(0)
        ->and(Attempt::forInvoice($invoice)->state)->toBe(ChargeAttemptState::InFlight);
});

test('with the switch off the dashboard and the invoice screens are what they were', function () {
    [$invoice, $card] = recReady(100.0);
    recAbandoned($invoice, $card, 100.0, 40);

    $admin = recAdmin();

    $dashboard = $this->actingAs($admin, 'admin')->get(route('admin.dashboard'))->assertOk()->getContent();
    $list = $this->actingAs($admin, 'admin')->get(route('admin.invoices.index'))->assertOk()->getContent();
    $page = $this->actingAs($admin, 'admin')->get(route('admin.invoices.show', $invoice))->assertOk()->getContent();

    expect($dashboard)->not->toContain('status=charge_review')
        ->and($list)->not->toContain(__('admin.invoices.filter_charge_review'))
        ->and($page)->not->toContain(__('admin.invoices.charge_review_title'));
});

// ---------------------------------------------------------------------------
// The branch that was prose: a worker that lost the race writes nothing
// ---------------------------------------------------------------------------

test('a worker acting on a reading that has already been overtaken writes nothing', function () {
    recOn();
    [$invoice, $card] = recReady(100.0);

    Attempt::create([
        'invoice_id' => $invoice->id,
        'payment_method_id' => $card->id,
        'state' => ChargeAttemptState::Scheduled,
        'attempts' => 1,
        'amount' => 100.0,
        'currency' => shop_currency_code(),
        'next_attempt_at' => now()->subHour(),
    ]);

    // Two workers read the same due row. The second one's reading is taken
    // here, before the first one acts on it.
    $stale = Attempt::forInvoice($invoice);

    expect(Attempt::claim($invoice, $card, 100.0, shop_currency_code()))->toBe(ChargeClaim::Taken)
        ->and(Attempt::forInvoice($invoice)->attempts)->toBe(2);

    // Every transition in the model repeats the reading it was decided on in
    // its WHERE clause, so the loser matches no row. Held rather than Taken is
    // the whole of it: Taken here would be a second card presented for one
    // invoice, which is what the table exists to prevent. Mutation-tested by a
    // reviewer who deleted the guard and found nothing failed — so this is the
    // test that was missing rather than the behaviour.
    $takeOver = new ReflectionMethod($stale, 'takeOver');
    $takeOver->setAccessible(true);

    // Two minutes later, so that a write getting through would be visible as a
    // moved claimed_at rather than hidden by MySQL reporting no rows changed
    // when a row is rewritten with the values it already had.
    $this->travel(2)->minutes();
    $held = $takeOver->invoke($stale, $card, 100.0, shop_currency_code(), 3, now());
    $claimedAt = Attempt::forInvoice($invoice)->claimed_at;

    expect($held)->toBe(ChargeClaim::Held)
        ->and($claimedAt->lte(now()->subMinutes(2)))->toBeTrue();

    $row = Attempt::forInvoice($invoice);

    expect($row->attempts)->toBe(2)
        ->and($row->state)->toBe(ChargeAttemptState::InFlight);
});

test('two rescuers replaying the same abandoned attempt produce exactly one winner', function () {
    recOn();
    [$invoice, $card] = recReady(100.0);

    expect(Attempt::claim($invoice, $card, 100.0, shop_currency_code()))->toBe(ChargeClaim::Taken);

    // The lease goes cold: the worker that took it never came back. Two
    // rescuers now meet the row, which is what an overlapping sweep and an
    // operator running the command by hand look like from here.
    $this->travel(20)->minutes();

    // BOTH READINGS ARE TAKEN BEFORE EITHER ACTS, which is the race. claim()
    // re-reads the row each time it is called, so two sequential calls cannot
    // express it; two instances loaded from the same state can. takeOver() is
    // reached by reflection for the same reason the test above it does.
    $first = Attempt::forInvoice($invoice);
    $second = Attempt::forInvoice($invoice);

    $takeOver = new ReflectionMethod($first, 'takeOver');
    $takeOver->setAccessible(true);

    // A SECOND APART, NOT THE SAME INSTANT, and the difference is whether this
    // test can fail at all. Handed the same moment, the loser's UPDATE rewrites
    // claimed_at and replays with the values the winner has just written, MySQL
    // reports nothing changed, and guardedUpdate() returns false for a reason
    // that has nothing to do with the guard — so deleting the claimed_at clause
    // from its WHERE left this test, and the whole suite, green. Its sibling
    // above travels two minutes for exactly this reason and says so; this one
    // walked into the trap the round was written to keep out.
    //
    // Two rescuers are two processes and never share a clock anyway: the sweep
    // and an operator at a terminal arrive when they arrive.
    $now = now();

    $a = $takeOver->invoke($first, $card, 100.0, shop_currency_code(), 3, $now);
    $b = $takeOver->invoke($second, $card, 100.0, shop_currency_code(), 3, $now->copy()->addSecond());

    $row = Attempt::forInvoice($invoice);

    // The replay now writes a second column beside claimed_at, and a counter
    // read-then-written is the classic way a guard gets quietly weakened: both
    // rescuers read replays = 0, both would write 1, and the row would show one
    // replay where two cards had been presented. It does not happen, because
    // the guarded update still matches on the claimed_at both of them read and
    // only one row can carry it.
    expect($a)->toBe(ChargeClaim::Taken)
        ->and($b)->toBe(ChargeClaim::Held)
        ->and($row->replays)->toBe(1)
        ->and($row->attempts)->toBe(1)
        ->and($row->state)->toBe(ChargeAttemptState::InFlight)
        // The winner's moment, not the loser's — which is only an assertion at
        // all now that the two are different.
        ->and($row->claimed_at->timestamp)->toBe($now->timestamp);
});

test('an abandoned charge whose card is gone is parked once, not on every sweep for ever', function () {
    recOn();
    Setting::set('SystemEmailAddress', 'ops@example.test');
    $calls = recGateway();
    [$invoice, $card] = recReady(100.0);

    // A charge that reached the gateway and was written down — settle() records
    // the transaction id BEFORE crediting, precisely so the money is
    // recoverable from our own books — and then the process died. The card row
    // is then gone for good, so the payment cannot be recorded under the
    // (gateway, transaction id) pair the webhook would use and a person has to
    // place it by hand.
    $row = Attempt::create([
        'invoice_id' => $invoice->id,
        'payment_method_id' => $card->id,
        'state' => ChargeAttemptState::InFlight,
        'attempts' => 1,
        'amount' => 100.0,
        'currency' => shop_currency_code(),
        'claimed_at' => now()->subMinutes(20),
        'last_transaction_id' => 'pi_orphan',
    ]);

    $card->forceDelete();

    $emails = 0;
    Illuminate\Support\Facades\Event::listen(Illuminate\Mail\Events\MessageSending::class, function () use (&$emails) { $emails++; });

    // Four hours of routes/console.php:47.
    foreach (range(1, 4 * 4) as $ignored) {
        $this->travel(15)->minutes();
        Artisan::call('pnlcs:auto-charge', ['--rescue' => true]);
    }

    $row->refresh();

    // ONE ALERT, NOT SIXTEEN. rescue() keeps revisiting this row because it
    // still carries a transaction id, which is right — that id is how the money
    // gets credited once somebody resolves it — but re-parking an already
    // parked row announced it again on every sweep: ninety-six operator emails
    // a day about one invoice nothing automatic can move.
    expect($emails)->toBe(1)
        ->and($row->state)->toBe(ChargeAttemptState::NeedsReview)
        // Its id is kept, because it is the first thing the operator needs.
        ->and($row->last_transaction_id)->toBe('pi_orphan')
        // And no card was ever presented: there is none to present.
        ->and($calls->count())->toBe(0)
        ->and(Transaction::where('invoice_id', $invoice->id)->count())->toBe(0);
});
