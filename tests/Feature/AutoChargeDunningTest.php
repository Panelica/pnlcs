<?php

use App\Contracts\GatewayModuleInterface;
use App\Contracts\TokenizableGatewayInterface;
use App\Enums\ChargeAttemptState;
use App\Enums\InvoiceStatus;
use App\Mail\AutoChargeActionRequiredMail;
use App\Mail\AutoChargeFailedMail;
use App\Mail\InvoicePaidMail;
use App\Models\Client;
use App\Models\GatewaySettings;
use App\Models\Invoice;
use App\Models\InvoiceChargeAttempt as Attempt;
use App\Models\PaymentMethod;
use App\Models\Setting;
use App\Models\User;
use App\Services\Module\ModuleRegistry;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Sleep;

/*
 * What happens to a card that says no, and what the person holding it is told.
 *
 * Three questions run through the whole file. Is a refused card asked again too
 * soon, too often, or after its issuer has finished with it? Does the customer
 * hear about it, once, in words that match what actually happened? And can they
 * make it stop?
 *
 * The last one is the one to be strict about. Everything else here costs a
 * collection; taking money from somebody who has asked you not to costs their
 * trust, and there is no apology for it.
 */

beforeEach(function () {
    Sleep::fake();
});

afterEach(function () {
    Sleep::fake(false);
});

/** The feature, switched on the way an operator switches it on. */
function dunningOn(array $settings = []): void
{
    Setting::set('AutoChargeEnabled', '1');

    foreach ($settings as $key => $value) {
        Setting::set($key, (string) $value);
    }
}

/**
 * A gateway that can charge a stored card, and a record of what it was asked.
 *
 * @param  callable|null  $responder  fn (Invoice, PaymentMethod, float $amount, int $call): array
 */
function dunningGateway(?callable $responder = null): ArrayObject
{
    $calls = new ArrayObject;

    $responder ??= fn ($invoice, $method, $amount) => [
        'success' => true,
        'status' => 'succeeded',
        'transaction_id' => 'pi_'.$invoice->id.'_'.uniqid(),
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

/** A refusal shaped the way the contract defines one. */
function dunningDecline(string $code, bool $retryable): array
{
    return [
        'success' => false,
        'status' => 'failed',
        'message' => 'Gateway error: the card was declined.',
        'decline_code' => $code,
        'retryable' => $retryable,
    ];
}

/** A customer with a card, and an invoice due inside the charging window. */
function dunningClient(array $attributes = []): Client
{
    return Client::factory()->create($attributes + ['email' => 'zeynep@example.test']);
}

function dunningCard(Client $client, array $attributes = []): PaymentMethod
{
    return PaymentMethod::create($attributes + [
        'client_id' => $client->id,
        'gateway_name' => 'stripe',
        'payment_type' => 'cc',
        'description' => 'Visa 4242',
        'card_brand' => 'visa',
        'remote_token' => 'pm_'.fake()->unique()->numerify('##########'),
        'gateway_customer_id' => 'cus_'.fake()->unique()->numerify('##########'),
        'last_four' => '4242',
        'expiry_date' => '2030-07',
        'status' => PaymentMethod::STATUS_ACTIVE,
    ]);
}

function dunningInvoice(Client $client, float $total = 100.0, array $attributes = []): Invoice
{
    return Invoice::factory()->create($attributes + [
        'client_id' => $client->id,
        'status' => InvoiceStatus::Unpaid->value,
        'subtotal' => $total,
        'total' => $total,
        'due_date' => now()->addDay(),
    ]);
}

/** @return array{0: Client, 1: PaymentMethod, 2: Invoice} */
function dunningReady(float $total = 100.0): array
{
    $client = dunningClient();

    return [$client, dunningCard($client), dunningInvoice($client, $total)];
}

function dunningRun(array $arguments = []): void
{
    Artisan::call('pnlcs:auto-charge', $arguments);
}

function dunningAttempt(Invoice $invoice): Attempt
{
    return Attempt::forInvoice($invoice) ?? test()->fail('no attempt row for invoice '.$invoice->id);
}

// ---------------------------------------------------------------------------
// (a) The retry schedule
// ---------------------------------------------------------------------------

test('a retry is due at the start of a day, not at the second the last one failed', function () {
    // 06:45:03 — a few seconds into the run, which is when an outcome actually
    // gets written: the gateway round-trip happens first.
    $this->travelTo(now()->startOfDay()->addHours(6)->addMinutes(45)->addSeconds(3));

    dunningOn(['AutoChargeRetryDays' => 3]);
    $calls = dunningGateway(fn () => dunningDecline('insufficient_funds', true));
    [, , $invoice] = dunningReady();

    dunningRun();

    $row = dunningAttempt($invoice);

    expect($calls)->toHaveCount(1)
        ->and($row->state)->toBe(ChargeAttemptState::Scheduled)
        // Midnight on day three. Written as "now plus three days" it would be
        // 06:45:03, and the run three days later evaluates the claim BEFORE its
        // gateway call rather than after one — so it would arrive a second or
        // two early, find the row not due, and leave the retry for the day
        // after the operator asked for.
        ->and($row->next_attempt_at->toDateTimeString())
        ->toBe(now()->startOfDay()->addDays(3)->toDateTimeString());
});

test('the daily run three mornings later actually makes the retry', function () {
    $firstRun = now()->startOfDay()->addHours(6)->addMinutes(45);

    $this->travelTo($firstRun->copy()->addSeconds(3));

    dunningOn(['AutoChargeRetryDays' => 3]);
    $calls = dunningGateway(fn () => dunningDecline('insufficient_funds', true));
    [, , $invoice] = dunningReady();

    dunningRun();
    expect($calls)->toHaveCount(1);

    // The cron fires at 06:45:00 — three seconds EARLIER in the minute than the
    // moment the first outcome was recorded. That is the whole defect.
    $this->travelTo($firstRun->copy()->addDays(3));

    dunningRun();

    expect($calls)->toHaveCount(2)
        ->and(dunningAttempt($invoice)->attempts)->toBe(2);
});

test('a card the issuer has ended is not presented again, for this invoice or any other', function () {
    dunningOn();

    // What StripeModule does on a hard decline: refusalEndsTheCard() is true for
    // lost_card, so the row is marked STATUS_REQUIRES_UPDATE before the refusal
    // is returned. The fake does the same thing so the rest of the chain is
    // tested against the state the real module leaves behind.
    $calls = dunningGateway(function ($invoice, $method) {
        $method->update(['status' => PaymentMethod::STATUS_REQUIRES_UPDATE]);

        return dunningDecline('lost_card', false);
    });

    $client = dunningClient();
    $card = dunningCard($client);
    $first = dunningInvoice($client);
    $second = dunningInvoice($client);

    dunningRun();

    expect($calls)->toHaveCount(1)
        ->and(dunningAttempt($first)->state)->toBe(ChargeAttemptState::Exhausted)
        ->and(dunningAttempt($first)->next_attempt_at)->toBeNull()
        // The second invoice was never presented: the card stopped being
        // chargeable the moment the issuer ended it.
        ->and(Attempt::forInvoice($second))->toBeNull()
        ->and($card->fresh()->status)->toBe(PaymentMethod::STATUS_REQUIRES_UPDATE);

    // And not tomorrow either.
    $this->travelTo(now()->addDays(4));
    dunningRun();

    expect($calls)->toHaveCount(1);
});

test('one card is refused once a morning however many invoices the customer owes', function () {
    dunningOn();
    $calls = dunningGateway(fn () => dunningDecline('insufficient_funds', true));

    $client = dunningClient();
    dunningCard($client);
    $first = dunningInvoice($client, 100, ['due_date' => now()->subDays(2)]);
    $second = dunningInvoice($client, 60, ['due_date' => now()->subDay()]);
    $third = dunningInvoice($client, 30, ['due_date' => now()]);

    dunningRun();

    // One decline, not three. Three refusals on one card inside ten seconds is
    // what an issuer reads as fraud, and it drags down the acceptance rate of
    // the cards that would have worked.
    expect($calls)->toHaveCount(1)
        ->and($calls[0]['invoice'])->toBe($first->id)
        // The other two are untouched rather than failed: they have burned no
        // attempt and carry no verdict, because nothing was asked about them.
        ->and(Attempt::forInvoice($second))->toBeNull()
        ->and(Attempt::forInvoice($third))->toBeNull();
});

test('a card that works keeps paying the rest of what the customer owes', function () {
    dunningOn();
    $calls = dunningGateway();

    $client = dunningClient();
    dunningCard($client);
    dunningInvoice($client, 100, ['due_date' => now()->subDay()]);
    dunningInvoice($client, 60, ['due_date' => now()]);

    dunningRun();

    // The per-card guard is about refusals. A card that has just proved itself
    // is exactly what should settle the next invoice.
    expect($calls)->toHaveCount(2);
});

test('the give-up point is the operator cap, and nothing is asked of the card after it', function () {
    dunningOn(['AutoChargeMaxAttempts' => 3, 'AutoChargeRetryDays' => 3]);
    $calls = dunningGateway(fn () => dunningDecline('insufficient_funds', true));
    [, , $invoice] = dunningReady();

    for ($day = 0; $day <= 12; $day += 3) {
        $this->travelTo(now()->startOfDay()->addDays($day)->addHours(6)->addMinutes(45));
        dunningRun();
    }

    expect($calls)->toHaveCount(3)
        ->and(dunningAttempt($invoice)->state)->toBe(ChargeAttemptState::Exhausted)
        ->and(dunningAttempt($invoice)->next_attempt_at)->toBeNull()
        ->and(dunningAttempt($invoice)->attempts)->toBe(3);
});

test('the shipped defaults spend the last attempt before the suspension job runs', function () {
    // The point of the numbers. AutoChargeDaysBefore 3, AutoChargeRetryDays 3,
    // AutoChargeMaxAttempts 3: attempts land on due-3, due and due+3, and
    // pnlcs:auto-suspend takes a client whose invoice is more than
    // AutoSuspensionDays (3) past due — so the third attempt is made at 06:45
    // on the very morning suspension runs at 07:00. A last go at the card
    // before the customer is switched off, rather than a charge the morning
    // after they were.
    $due = now()->startOfDay()->addDays(3);

    $this->travelTo($due->copy()->subDays(3)->addHours(6)->addMinutes(45));

    dunningOn();
    $calls = dunningGateway(fn () => dunningDecline('insufficient_funds', true));
    $client = dunningClient();
    dunningCard($client);
    $invoice = dunningInvoice($client, 100, ['due_date' => $due]);

    $made = [];

    for ($day = -3; $day <= 4; $day++) {
        $this->travelTo($due->copy()->addDays($day)->addHours(6)->addMinutes(45));
        $before = count($calls);
        dunningRun();

        if (count($calls) > $before) {
            $made[] = $day;
        }
    }

    expect($made)->toBe([-3, 0, 3])
        ->and(dunningAttempt($invoice)->state)->toBe(ChargeAttemptState::Exhausted);
});

// ---------------------------------------------------------------------------
// (b) What the customer is told
// ---------------------------------------------------------------------------

test('the first refusal is emailed, with the date the card will be tried again', function () {
    Mail::fake();
    dunningOn(['AutoChargeRetryDays' => 3]);
    dunningGateway(fn () => dunningDecline('insufficient_funds', true));
    [$client, $card, $invoice] = dunningReady(100);

    dunningRun();

    Mail::assertQueued(AutoChargeFailedMail::class, function ($mail) use ($invoice, $card) {
        return $mail->invoice->is($invoice)
            && $mail->paymentMethod->is($card)
            && $mail->amount === 100.0
            && $mail->retryAt !== null
            && $mail->cardEnded === false;
    });
    Mail::assertNotQueued(AutoChargeActionRequiredMail::class);
});

test('the attempt in the middle says nothing, so it sends nothing', function () {
    dunningOn(['AutoChargeMaxAttempts' => 3, 'AutoChargeRetryDays' => 3]);
    dunningGateway(fn () => dunningDecline('insufficient_funds', true));
    [, , $invoice] = dunningReady();

    dunningRun();

    // Only from here on, so the first email is not in the count.
    Mail::fake();

    $this->travelTo(now()->addDays(3)->addHours(7));
    dunningRun();

    expect(dunningAttempt($invoice)->attempts)->toBe(2);
    Mail::assertNothingQueued();
});

test('giving up is emailed, and says so rather than promising another attempt', function () {
    dunningOn(['AutoChargeMaxAttempts' => 2, 'AutoChargeRetryDays' => 3]);
    dunningGateway(fn () => dunningDecline('insufficient_funds', true));
    [, , $invoice] = dunningReady();

    dunningRun();

    Mail::fake();

    $this->travelTo(now()->addDays(3)->addHours(7));
    dunningRun();

    expect(dunningAttempt($invoice)->state)->toBe(ChargeAttemptState::Exhausted);

    Mail::assertQueued(AutoChargeFailedMail::class, fn ($mail) => $mail->retryAt === null);
});

test('a refusal that both fails and finishes sends one email, not two', function () {
    Mail::fake();
    dunningOn();
    dunningGateway(function ($invoice, $method) {
        $method->update(['status' => PaymentMethod::STATUS_REQUIRES_UPDATE]);

        return dunningDecline('lost_card', false);
    });
    dunningReady();

    dunningRun();

    Mail::assertQueued(AutoChargeFailedMail::class, 1);
    // And it tells them the right thing to do, which is not "we will try again"
    // and not "top the card up" either.
    Mail::assertQueued(AutoChargeFailedMail::class, fn ($mail) => $mail->retryAt === null && $mail->cardEnded === true);
});

test('a bank asking for its cardholder is not reported as a decline', function () {
    Mail::fake();
    dunningOn();
    dunningGateway(fn ($invoice) => [
        'success' => false,
        'status' => 'requires_action',
        'message' => "The cardholder's bank wants them to authenticate this payment.",
        'transaction_id' => 'pi_needs_3ds',
        'decline_code' => 'authentication_required',
        'retryable' => false,
    ]);
    [, , $invoice] = dunningReady(80);

    dunningRun();

    expect(dunningAttempt($invoice)->state)->toBe(ChargeAttemptState::ActionRequired);

    Mail::assertQueued(AutoChargeActionRequiredMail::class, fn ($mail) => $mail->invoice->is($invoice) && $mail->amount === 80.0);
    Mail::assertNotQueued(AutoChargeFailedMail::class);
});

test('one pending authentication per card per morning, not one per invoice', function () {
    Mail::fake();
    dunningOn();
    $calls = dunningGateway(fn () => [
        'success' => false,
        'status' => 'requires_action',
        'message' => 'Authenticate.',
        'transaction_id' => 'pi_needs_3ds',
        'decline_code' => 'authentication_required',
        'retryable' => false,
    ]);

    $client = dunningClient();
    dunningCard($client);
    dunningInvoice($client, 100, ['due_date' => now()->subDay()]);
    dunningInvoice($client, 60, ['due_date' => now()]);

    dunningRun();

    // The same card asks for the same person on the second invoice. All a
    // second attempt buys is a second pending intent and a second email.
    expect($calls)->toHaveCount(1);
    Mail::assertQueued(AutoChargeActionRequiredMail::class, 1);
});

test('the failure email carries none of the gateway internals', function () {
    dunningOn();
    [$client, $card, $invoice] = dunningReady(42.5);

    $body = (new AutoChargeFailedMail($invoice, $card, 42.5, now()->addDays(3)))->render();

    expect($body)
        ->not->toContain('Gateway error')
        ->not->toContain('secret key')
        ->not->toContain('insufficient_funds')
        // and no raw translation key reached the customer
        ->not->toContain('email.auto_charge_failed')
        ->toContain('4242');
});

test('the authentication email points at a page that can actually take the payment', function () {
    dunningOn();
    [, $card, $invoice] = dunningReady(42.5);

    $body = (new AutoChargeActionRequiredMail($invoice, $card, 42.5))->render();

    expect($body)
        ->toContain(route('client.invoices.show', $invoice->id))
        ->not->toContain('email.auto_charge_action_required');
});

test('a mail server that is down does not stop the collection', function () {
    dunningOn();
    $calls = dunningGateway(fn ($invoice, $method, $amount, $call) => $call === 1
        ? dunningDecline('insufficient_funds', true)
        : ['success' => true, 'status' => 'succeeded', 'transaction_id' => 'pi_ok_'.$invoice->id, 'amount' => $amount]);

    $failing = dunningClient(['email' => 'first@example.test']);
    dunningCard($failing);
    dunningInvoice($failing, 100, ['due_date' => now()->subDay()]);

    $paying = dunningClient(['email' => 'second@example.test']);
    dunningCard($paying);
    $second = dunningInvoice($paying, 60, ['due_date' => now()]);

    Mail::shouldReceive('to')->andThrow(new RuntimeException('smtp is down'));

    dunningRun();

    expect($calls)->toHaveCount(2)
        ->and($second->fresh()->status)->toBe(InvoiceStatus::Paid->value);
});

// ---------------------------------------------------------------------------
// (c) The receipt, which already exists
// ---------------------------------------------------------------------------

test('a successfully auto-charged invoice sends the receipt PNLCS already sends', function () {
    Mail::fake();
    dunningOn();
    dunningGateway();
    [, , $invoice] = dunningReady();

    dunningRun();

    // Nothing new was written for this. PaymentService::applyPayment raises
    // InvoicePaid, SendNotificationListener::handleInvoicePaid queues
    // InvoicePaidMail, and the card path gets it because it credits through the
    // same door as every other payment.
    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid->value);
    Mail::assertQueued(InvoicePaidMail::class, fn ($mail) => $mail->invoice->is($invoice));
    Mail::assertNotQueued(AutoChargeFailedMail::class);
});

// ---------------------------------------------------------------------------
// (d) The customer can stop it
// ---------------------------------------------------------------------------

test('a customer who has switched it off is never presented to a gateway', function () {
    dunningOn();
    $calls = dunningGateway();

    $client = dunningClient(['auto_charge' => false]);
    dunningCard($client);
    $invoice = dunningInvoice($client);

    dunningRun();

    expect($calls)->toHaveCount(0)
        ->and(Attempt::forInvoice($invoice))->toBeNull()
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Unpaid->value);
});

test('switching it off stops a dunning cycle that has already started', function () {
    dunningOn(['AutoChargeRetryDays' => 3]);
    $calls = dunningGateway(fn () => dunningDecline('insufficient_funds', true));
    [$client, , $invoice] = dunningReady();

    dunningRun();
    expect($calls)->toHaveCount(1)
        ->and(dunningAttempt($invoice)->state)->toBe(ChargeAttemptState::Scheduled);

    // The scheduled retry is sitting there with a date on it. The switch has to
    // reach it, and it does, because the only way that row is ever acted on is
    // the candidate query the switch removes them from.
    $client->update(['auto_charge' => false]);

    $this->travelTo(now()->addDays(4));
    dunningRun();

    expect($calls)->toHaveCount(1)
        ->and(dunningAttempt($invoice)->attempts)->toBe(1);
});

test('a customer who has said nothing is charged, because that is what every other billing switch does', function () {
    dunningOn();
    $calls = dunningGateway();
    [$client, , $invoice] = dunningReady();

    expect($client->fresh()->auto_charge)->toBeTrue();

    dunningRun();

    expect($calls)->toHaveCount(1)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Paid->value);
});

/** A signed-in customer, the way the client area resolves one. */
function dunningSignIn(Client $client): User
{
    $user = User::factory()->create();
    $user->clients()->attach($client->id);

    return $user;
}

test('the customer turns it off from the page their cards are on', function () {
    dunningOn();
    $client = dunningClient();

    $this->actingAs(dunningSignIn($client))
        ->post(route('client.payment-methods.auto-charge'), ['auto_charge' => '0'])
        ->assertRedirect();

    expect($client->fresh()->auto_charge)->toBeFalse();

    $this->actingAs(dunningSignIn($client))
        ->post(route('client.payment-methods.auto-charge'), ['auto_charge' => '1'])
        ->assertRedirect();

    expect($client->fresh()->auto_charge)->toBeTrue();
});

test('the switch is offered on the page when the shop collects by card', function () {
    dunningOn();
    $client = dunningClient();

    $this->actingAs(dunningSignIn($client))
        ->get(route('client.payment-methods.index'))
        ->assertOk()
        ->assertSee(__('client.payment_methods.auto_charge_title'))
        ->assertSee(__('client.payment_methods.auto_charge_turn_off'));
});

test('with the feature off the page is exactly what it was and the switch cannot be set', function () {
    Setting::set('AutoChargeEnabled', '0');
    $client = dunningClient();
    $user = dunningSignIn($client);

    $this->actingAs($user)
        ->get(route('client.payment-methods.index'))
        ->assertOk()
        ->assertDontSee(__('client.payment_methods.auto_charge_title'));

    // No state to have an opinion about, so the POST is not a quiet write.
    $this->actingAs($user)
        ->post(route('client.payment-methods.auto-charge'), ['auto_charge' => '0'])
        ->assertNotFound();

    expect($client->fresh()->auto_charge)->toBeTrue();
});

test('a customer cannot switch it off for somebody else', function () {
    dunningOn();
    $mine = dunningClient(['email' => 'mine@example.test']);
    $theirs = dunningClient(['email' => 'theirs@example.test']);

    $this->actingAs(dunningSignIn($mine))
        ->post(route('client.payment-methods.auto-charge'), ['auto_charge' => '0'])
        ->assertRedirect();

    expect($mine->fresh()->auto_charge)->toBeFalse()
        ->and($theirs->fresh()->auto_charge)->toBeTrue();
});
