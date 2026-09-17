<?php

use App\Contracts\GatewayModuleInterface;
use App\Contracts\TokenizableGatewayInterface;
use App\Enums\ChargeAttemptState;
use App\Enums\InvoiceStatus;
use App\Models\CancellationRequest;
use App\Models\Domain;
use App\Models\GatewaySettings;
use App\Models\Invoice;
use App\Models\InvoiceChargeAttempt as Attempt;
use App\Models\InvoiceItem;
use App\Models\PaymentMethod;
use App\Models\Service;
use App\Models\ServiceAddon;
use App\Models\Setting;
use App\Services\AutoChargeService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Sleep;

/*
 * WHAT THE CUSTOMER SAID NO TO.
 *
 * The file beside this one proves the charger cannot take the same money twice.
 * This one is about the other half of the rule, and the harder half: money must
 * not be taken from somebody who said no at all.
 *
 * The generator decides what may be billed, and it refuses on eight grounds
 * spread over three kinds of line — three per service (status, auto_renew, an
 * open cancellation request), one per domain (payment_method = 'none', which is
 * where the customer's own auto-renew switch writes its answer, plus the
 * status pair it selects on) and the strictest set of all per addon
 * (AddonService::dueQuery). The charger mirrored two of them, for Hosting lines
 * only. Everything else — a service whose owner turned renewal off, any domain
 * line at all, any addon line at all — matched no clause in the candidate SQL
 * or in the fresh re-read, and the card was presented.
 *
 * That gap is not academic, because the two are fourteen days apart: the
 * generator raises a renewal up to a fortnight before it falls due and this
 * collects it three days before. The customer has a fortnight in which to go
 * into the panel and switch the renewal off, watch the panel confirm it, and be
 * charged for it anyway.
 *
 * Every test here is one of the three reviewers' own probes. Each failed before
 * the change it is attached to, and each has a control beside it, because a
 * charger that refuses everything is not a fix.
 */

beforeEach(function () {
    Sleep::fake();
});

afterEach(function () {
    Sleep::fake(false);
});

function consentOn(array $settings = []): void
{
    Setting::set('AutoChargeEnabled', '1');

    foreach ($settings as $key => $value) {
        Setting::set($key, (string) $value);
    }
}

/** @param  callable|null  $responder  fn (Invoice, PaymentMethod, float, int $call): array */
function consentGateway(?callable $responder = null): ArrayObject
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
    app(App\Services\Module\ModuleRegistry::class)->registerGateway('stripe', GatewayModuleInterface::class);

    GatewaySettings::updateOrCreate(['gateway' => 'stripe', 'setting' => 'active'], ['value' => '1']);

    return $calls;
}

function consentCard(Invoice $invoice, array $attributes = []): PaymentMethod
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

/** A due invoice with one stored card. @return array{0: Invoice, 1: PaymentMethod} */
function consentReady(float $total = 100.0, array $attributes = []): array
{
    $invoice = Invoice::factory()->create($attributes + [
        'status' => InvoiceStatus::Unpaid->value,
        'subtotal' => $total,
        'total' => $total,
        'due_date' => now()->addDay(),
    ]);

    return [$invoice, consentCard($invoice)];
}

function consentLine(Invoice $invoice, string $type, int $relId, float $amount = 100.0): void
{
    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'client_id' => $invoice->client_id,
        'type' => $type,
        'rel_id' => $relId,
        'description' => $type.' renewal',
        'amount' => $amount,
        'taxed' => false,
    ]);
}

function consentRun(array $arguments = []): void
{
    Artisan::call('pnlcs:auto-charge', $arguments);
}

/** An attempt a killed run left behind, claimed $minutes ago. */
function consentAbandoned(Invoice $invoice, PaymentMethod $card, float $amount, int $minutes): Attempt
{
    return Attempt::create([
        'invoice_id' => $invoice->id,
        'payment_method_id' => $card->id,
        'state' => ChargeAttemptState::InFlight,
        'attempts' => 1,
        'amount' => $amount,
        'currency' => shop_currency_code(),
        'claimed_at' => now()->subMinutes($minutes),
    ]);
}

/** An invoice billing one hosting service. @return array{0: Invoice, 1: PaymentMethod, 2: Service} */
function consentServiceInvoice(array $serviceAttributes = []): array
{
    [$invoice, $card] = consentReady(100.0);

    $service = Service::factory()->create($serviceAttributes + ['client_id' => $invoice->client_id]);

    consentLine($invoice, 'Hosting', $service->id);

    return [$invoice, $card, $service];
}

// ---------------------------------------------------------------------------
// N2 (a)  services.auto_renew = false is an opt-out, and the charger has to
//         honour it — the generator refuses to raise the line for it
// ---------------------------------------------------------------------------

test('a service whose owner switched auto renewal off is not collected for', function () {
    consentOn();
    $calls = consentGateway();

    // InvoiceGenerationService refuses to RAISE a line for this service, with
    // the comment 'The customer turned renewal off; billing them anyway is what
    // got the account suspended for an invoice they never wanted'. The invoice
    // was raised up to eleven days before the customer clicked, and
    // Client\ServiceController::toggleAutoRenew writes nothing but the flag —
    // no observer, listener or command cancels the invoice already standing.
    [$invoice, , $service] = consentServiceInvoice(['status' => 'active']);
    $service->update(['auto_renew' => false]);

    consentRun();

    expect($calls->count())->toBe(0)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Unpaid->value)
        // Not claimed either: an invoice that was never chargeable must not
        // burn one of the customer's attempts.
        ->and(Attempt::forInvoice($invoice))->toBeNull();
});

test('a service that is still renewing is still collected for', function () {
    consentOn();
    $calls = consentGateway();

    [$invoice] = consentServiceInvoice(['status' => 'active']);

    consentRun();

    expect($calls->count())->toBe(1)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Paid->value);
});

test('a suspended service is still collected for, because paying is what lifts the suspension', function () {
    consentOn();
    $calls = consentGateway();

    // The one place the charger is deliberately less strict than the generator,
    // which bills active services only. Suspension is almost always
    // non-payment; refusing to collect here would leave the feature unable to
    // fix the very thing it exists to prevent, and AddonService::dueQuery takes
    // the same position in as many words.
    [$invoice] = consentServiceInvoice(['status' => 'suspended']);

    consentRun();

    expect($calls->count())->toBe(1)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Paid->value);
});

// ---------------------------------------------------------------------------
// N2 (d)  and the same answer through the rescue sweep, so it is not one
//         missing clause in one query
// ---------------------------------------------------------------------------

test('a crashed charge is not replayed for a service whose owner has since switched renewal off', function () {
    consentOn();
    $calls = consentGateway();

    [$invoice, $card, $service] = consentServiceInvoice(['status' => 'active']);

    // A run died with the charge in flight twenty minutes ago; the lease is
    // cold and the replay window is wide open. Then the customer switched the
    // renewal off.
    consentAbandoned($invoice, $card, 100.0, 20);
    $service->update(['auto_renew' => false]);

    consentRun(['--rescue' => true]);

    // The replay is not free: if the original request never reached the
    // gateway, the replay IS the first charge — on somebody who has since said
    // no. So it goes to a person instead, which is the same answer the sweep
    // already gave for a cancelled invoice.
    expect($calls->count())->toBe(0)
        ->and(Attempt::forInvoice($invoice)->state)->toBe(ChargeAttemptState::NeedsReview)
        ->and(Artisan::output())->toContain('NEED A PERSON');
});

// ---------------------------------------------------------------------------
// N2 (b)  domain lines were invisible to every consent check
// ---------------------------------------------------------------------------

/** An invoice billing one domain. @return array{0: Invoice, 1: PaymentMethod, 2: Domain} */
function consentDomainInvoice(array $domainAttributes = []): array
{
    [$invoice, $card] = consentReady(100.0);

    $domain = Domain::factory()->create($domainAttributes + ['client_id' => $invoice->client_id]);

    consentLine($invoice, 'Domain', $domain->id);

    return [$invoice, $card, $domain];
}

test('a domain whose owner switched auto renewal off is not collected for', function () {
    consentOn();
    $calls = consentGateway();

    // 'A domain has no auto-renew column: the customer's switch flips
    // payment_method to none, which is the signal to leave it alone' —
    // InvoiceGenerationService's own comment, and
    // Client\DomainController::toggleAutoRenew is the switch that writes it.
    [$invoice] = consentDomainInvoice(['payment_method' => 'none']);

    consentRun();

    expect($calls->count())->toBe(0)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Unpaid->value)
        ->and(Attempt::forInvoice($invoice))->toBeNull();
});

test('a cancelled domain is not collected for', function () {
    consentOn();
    $calls = consentGateway();

    [$invoice] = consentDomainInvoice(['status' => 'cancelled']);

    consentRun();

    expect($calls->count())->toBe(0)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Unpaid->value);
});

test('a domain that is still renewing is still collected for, in grace as well as active', function () {
    consentOn();
    $calls = consentGateway();

    [$active] = consentDomainInvoice(['status' => 'active']);
    // 'Grace counts as billable: the registry still renews at the ordinary
    // price and the customer can still keep the domain.'
    [$grace] = consentDomainInvoice(['status' => 'grace']);

    consentRun();

    expect($calls->count())->toBe(2)
        ->and($active->fresh()->status)->toBe(InvoiceStatus::Paid->value)
        ->and($grace->fresh()->status)->toBe(InvoiceStatus::Paid->value);
});

test('a domain with no payment method recorded is still collected for, because a null is not a no', function () {
    consentOn();
    $calls = consentGateway();

    // The generator says this in as many words: 'A null is not a no: SQL would
    // drop those rows from the comparison and quietly stop billing them.'
    [$invoice] = consentDomainInvoice(['payment_method' => null]);

    consentRun();

    expect($calls->count())->toBe(1)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Paid->value);
});

// ---------------------------------------------------------------------------
// N2 (c)  addon lines were invisible too, and the generator's rules for them
//         are the strictest of the three
// ---------------------------------------------------------------------------

/** An invoice billing one addon. @return array{0: Invoice, 1: PaymentMethod, 2: Service, 3: ServiceAddon} */
function consentAddonInvoice(array $serviceAttributes = [], array $addonAttributes = []): array
{
    [$invoice, $card] = consentReady(100.0);

    $service = Service::factory()->create($serviceAttributes + ['client_id' => $invoice->client_id]);

    $addon = ServiceAddon::create($addonAttributes + [
        'service_id' => $service->id,
        'client_id' => $invoice->client_id,
        'qty' => 1,
        'amount' => 100.0,
        'billing_cycle' => 'Monthly',
        'next_due_date' => now()->addMonth(),
        'status' => 'active',
    ]);

    consentLine($invoice, 'Addon', $addon->id);

    return [$invoice, $card, $service, $addon];
}

test('an addon on a service the customer cancelled is not collected for', function () {
    consentOn();
    $calls = consentGateway();

    [$invoice] = consentAddonInvoice(['status' => 'cancelled']);

    // The near-miss this replaces is worth naming: the old predicate joined
    // services.id = invoice_items.rel_id, and an Addon line's rel_id is a
    // service_addons id. Only the `type = 'Hosting'` filter stopped an
    // unrelated service's status deciding the charge. Each type now joins its
    // own table inside its own branch.
    consentRun();

    expect($calls->count())->toBe(0)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Unpaid->value);
});

test('an addon on a service with an open cancellation request is not collected for', function () {
    consentOn();
    $calls = consentGateway();

    [$invoice, , $service] = consentAddonInvoice();

    CancellationRequest::create([
        'service_id' => $service->id,
        'type' => 'End of Billing Period',
        'reason' => 'Not needed any more',
        'processed_at' => null,
    ]);

    consentRun();

    expect($calls->count())->toBe(0)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Unpaid->value);
});

test('an addon on a service whose owner switched renewal off is not collected for', function () {
    consentOn();
    $calls = consentGateway();

    [$invoice, , $service] = consentAddonInvoice();
    $service->update(['auto_renew' => false]);

    consentRun();

    expect($calls->count())->toBe(0);
});

test('an addon the customer cancelled is not collected for', function () {
    consentOn();
    $calls = consentGateway();

    // AddonService::cancel writes exactly this.
    [$invoice] = consentAddonInvoice([], ['status' => 'cancelled']);

    consentRun();

    expect($calls->count())->toBe(0);
});

test('an addon a customer has just bought is still collected for', function () {
    consentOn();
    $calls = consentGateway();

    // AddonService::purchaseForService writes the row as 'pending' and raises
    // its invoice in the same breath. A whitelist of 'active' — which is what
    // the generator's renewal query uses — would refuse the money the customer
    // has just asked to spend.
    [$invoice] = consentAddonInvoice([], ['status' => 'pending']);

    consentRun();

    expect($calls->count())->toBe(1)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Paid->value);
});

// ---------------------------------------------------------------------------
// One definition, two readers
// ---------------------------------------------------------------------------

test('a renewal switched off while the run is in progress is caught by the fresh re-read, not only by the candidate query', function () {
    consentOn();

    [$first, $card] = consentReady(100.0);
    $firstService = Service::factory()->create(['client_id' => $first->client_id]);
    consentLine($first, 'Hosting', $firstService->id);

    // A second invoice for the same client, which the candidate query loads in
    // the same window as the first.
    $second = Invoice::factory()->create([
        'client_id' => $first->client_id,
        'status' => InvoiceStatus::Unpaid->value,
        'subtotal' => 60.0,
        'total' => 60.0,
        'due_date' => now()->addDay(),
    ]);
    $secondService = Service::factory()->create(['client_id' => $first->client_id]);
    consentLine($second, 'Hosting', $secondService->id, 60.0);

    // The customer switches the second service's renewal off while the first
    // card is at the gateway. At a thousand invoices and 500ms apiece the last
    // one is acted on eight minutes after the window was read, so this is not a
    // contrived moment.
    $calls = consentGateway(function ($invoice, $method, $amount) use ($secondService) {
        $secondService->update(['auto_renew' => false]);

        return ['success' => true, 'status' => 'succeeded', 'transaction_id' => 'pi_'.$invoice->id, 'amount' => $amount];
    });

    consentRun();

    // If the refusal lived only in the candidate SQL, the second invoice would
    // have been charged off the stale snapshot. The per-invoice re-read and the
    // candidate SQL are the same predicate — endedItems() — asked twice.
    expect($calls->count())->toBe(1)
        ->and($calls[0]['invoice'])->toBe($first->id)
        ->and($second->fresh()->status)->toBe(InvoiceStatus::Unpaid->value)
        ->and(Attempt::forInvoice($second))->toBeNull();
});

test('the charger refuses every line the generator would refuse to raise', function () {
    consentOn();
    consentGateway();

    // The claim this round is aimed at, asked of the service directly rather
    // than through the command: for each of the eight refusals the generator
    // applies, the charger says no as well.
    $charger = app(AutoChargeService::class);
    $refusedNow = new ReflectionMethod($charger, 'refusedNow');
    $refusedNow->setAccessible(true);

    $hosting = function (array $attributes, ?callable $after = null) use ($charger, $refusedNow) {
        [$invoice, , $service] = consentServiceInvoice($attributes);

        if ($after) {
            $after($service);
        }

        return $refusedNow->invoke($charger, Invoice::with('client')->find($invoice->id));
    };

    expect($hosting(['status' => 'cancelled']))->toBe('service_the_customer_ended')
        ->and($hosting(['status' => 'terminated']))->toBe('service_the_customer_ended')
        ->and($hosting(['status' => 'fraud']))->toBe('service_the_customer_ended')
        ->and($hosting(['status' => 'pending']))->toBe('service_the_customer_ended')
        ->and($hosting([], fn ($s) => $s->update(['auto_renew' => false])))->toBe('service_the_customer_ended')
        ->and($hosting([], fn ($s) => CancellationRequest::create([
            'service_id' => $s->id, 'type' => 'Immediate', 'reason' => 'x', 'processed_at' => null,
        ])))->toBe('service_the_customer_ended')
        // And the two it must NOT refuse.
        ->and($hosting(['status' => 'active']))->toBeNull()
        ->and($hosting(['status' => 'suspended']))->toBeNull();

    $domain = function (array $attributes) use ($charger, $refusedNow) {
        [$invoice] = consentDomainInvoice($attributes);

        return $refusedNow->invoke($charger, Invoice::with('client')->find($invoice->id));
    };

    expect($domain(['payment_method' => 'none']))->toBe('domain_the_customer_ended')
        ->and($domain(['status' => 'cancelled']))->toBe('domain_the_customer_ended')
        ->and($domain(['status' => 'expired']))->toBe('domain_the_customer_ended')
        ->and($domain(['status' => 'active']))->toBeNull()
        ->and($domain(['status' => 'grace']))->toBeNull();

    $addon = function (array $serviceAttributes, array $addonAttributes = []) use ($charger, $refusedNow) {
        [$invoice] = consentAddonInvoice($serviceAttributes, $addonAttributes);

        return $refusedNow->invoke($charger, Invoice::with('client')->find($invoice->id));
    };

    expect($addon(['status' => 'cancelled']))->toBe('addon_the_customer_ended')
        ->and($addon(['status' => 'terminated']))->toBe('addon_the_customer_ended')
        ->and($addon(['status' => 'pending']))->toBe('addon_the_customer_ended')
        ->and($addon([], ['status' => 'cancelled']))->toBe('addon_the_customer_ended')
        ->and($addon([]))->toBeNull();
});

// ---------------------------------------------------------------------------
// N3  A charge whose outcome was never heard
// ---------------------------------------------------------------------------

test('a charge that was sent with no answer coming back is not scheduled as a fresh charge', function () {
    consentOn();

    // The connection died mid-POST. StripeModule reports outcome_unknown: the
    // request may well have reached the gateway and the money may be gone.
    // retryable is deliberately TRUE here, which is what StripeModule used to
    // return for this case and is the reason the defect existed: it made the
    // charger schedule a fresh attempt. outcome_unknown has to override it —
    // the flag says money may already have moved, and that outranks any opinion
    // about whether trying again is worthwhile.
    $calls = consentGateway(fn () => [
        'success' => false,
        'status' => 'failed',
        'outcome_unknown' => true,
        'message' => 'Stripe could not be reached: cURL error 28',
        'retryable' => true,
    ]);

    [$invoice] = consentReady(100.0);

    consentRun();

    $row = Attempt::forInvoice($invoice);

    expect($calls->count())->toBe(1)
        // NOT 'scheduled'. recordFailure would have sent this to
        // nextAttemptMoment, which deliberately lands a retry at least a day
        // out so that an ordinary decline is a NEW request rather than an
        // answer out of the gateway's saved copy — and a new request is exactly
        // what must not happen when the money may already have moved.
        ->and($row->state)->toBe(ChargeAttemptState::InFlight)
        ->and($row->next_attempt_at)->toBeNull()
        ->and($row->attempts)->toBe(1)
        ->and($row->last_transaction_id)->toBeNull()
        // Said out loud, and not as a decline.
        ->and(Artisan::output())->toContain('no answer came back');
});

test('a charge whose answer never came back is replayed inside the window, not charged again', function () {
    consentOn();

    // Unreachable on the first call, reachable on the second — where the
    // gateway answers out of its record of the first request, because the
    // idempotency key is built from the invoice, the card, the amount and the
    // currency, none of which has changed.
    $calls = consentGateway(fn ($invoice, $method, $amount, $call) => $call === 1
        ? ['success' => false, 'status' => 'failed', 'outcome_unknown' => true, 'message' => 'timed out', 'retryable' => true]
        : ['success' => true, 'status' => 'succeeded', 'transaction_id' => 'pi_'.$invoice->id, 'amount' => $amount]);

    [$invoice] = consentReady(100.0);

    consentRun();

    // The lease goes cold at fifteen minutes and the rescue sweep runs every
    // fifteen, well inside the twenty-three-hour replay window.
    $this->travel(16)->minutes();
    consentRun(['--rescue' => true]);

    $row = Attempt::forInvoice($invoice);

    expect($calls->count())->toBe(2)
        // Two presentations of one request, not two requests: a replay does not
        // increment the count, because at the gateway it is the charge that was
        // already made.
        ->and($row->attempts)->toBe(1)
        ->and($calls[1]['amount'])->toBe($calls[0]['amount'])
        ->and($row->state)->toBe(ChargeAttemptState::Succeeded)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Paid->value)
        ->and(App\Models\Transaction::where('invoice_id', $invoice->id)->count())->toBe(1);
});

test('a charge whose answer never came back is never repeated once the gateway has forgotten the request', function () {
    consentOn();

    // Every presentation is recorded with the moment it happened, because the
    // thing that must not occur is not "too many calls" but "a call after the
    // gateway stopped holding the key".
    $times = [];
    $calls = consentGateway(function () use (&$times) {
        $times[] = now()->copy();

        return [
            'success' => false,
            'status' => 'failed',
            'outcome_unknown' => true,
            'message' => 'timed out',
            // As the module used to answer. Read as an ordinary retryable
            // failure this schedules a second attempt two mornings later —
            // outside the twenty-four hours the gateway keeps the idempotency
            // key, so a second real charge — which is precisely what this test
            // watches for.
            'retryable' => true,
        ];
    });

    [$invoice] = consentReady(100.0);

    consentRun();

    expect($calls->count())->toBe(1);

    // THREE DAYS WALKED, NOT JUMPED, AND THAT IS THE WHOLE POINT OF THE LOOP.
    //
    // This was one travel(3)->days() followed by a single sweep. A row three
    // days cold parks under any reading of the deadline, so the test passed
    // against a build in which the deadline never arrived at all: it skipped
    // the two hundred and eighty-eight sweeps routes/console.php:47 would
    // really have run in between, and every one of them refreshed the lease the
    // deadline was being measured from. A test that only passes by missing the
    // runs that would break it is worse than no test.
    foreach (range(1, 3 * 24 * 4) as $ignored) {
        $this->travel(15)->minutes();
        consentRun(['--rescue' => true]);
    }

    // And the daily charger, which reaches the same claim by the other door.
    consentRun();

    $row = Attempt::forInvoice($invoice);

    expect($row->state)->toBe(ChargeAttemptState::NeedsReview)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Unpaid->value)
        // NOTHING WAS PRESENTED AFTER THE KEY COULD HAVE BEEN PRUNED. Measured
        // from the first send, which is the clock Stripe's retention runs on —
        // not from the lease, which every replay resets.
        //
        // WRITTEN EARLIEST-FIRST, and it has to be. Carbon 3's diff methods are
        // SIGNED — $later->diffInSeconds($earlier) is negative — so the
        // arrangement this started life as compared a negative number against
        // the window and passed against any build whatsoever, including one
        // that presented the charge on the third day. The assertion below fails
        // if the last presentation is late; the one it replaces could not fail
        // at all.
        ->and($times[0]->diffInSeconds(end($times)))->toBeLessThan(Attempt::REPLAY_WINDOW_SECONDS)
        // And said in the terms the defect is actually in, so that the shape of
        // the reading cannot hide it: not one presentation past the deadline.
        ->and(array_values(array_filter(
            $times,
            fn ($at) => $times[0]->diffInSeconds($at) >= Attempt::REPLAY_WINDOW_SECONDS
        )))->toBeEmpty();
});

test('the fifteen-minute sweep presents an unanswerable charge a handful of times, not hundreds', function () {
    consentOn();

    // A gateway that never answers. Every presentation comes back
    // outcome_unknown, which is the one reply that leaves the row in flight for
    // the sweep to find again — so nothing but the deadline and the cap can
    // ever stop this.
    $calls = consentGateway(fn () => [
        'success' => false,
        'status' => 'failed',
        'outcome_unknown' => true,
        'message' => 'timed out',
        'retryable' => true,
    ]);

    [$invoice] = consentReady(100.0);

    $sent = 0;
    Illuminate\Support\Facades\Event::listen(Illuminate\Mail\Events\MessageSending::class, function () use (&$sent) { $sent++; });
    Setting::set('SystemEmailAddress', 'ops@example.test');

    consentRun();

    expect($calls->count())->toBe(1);

    // THE SCHEDULE AS IT IS ACTUALLY WRITTEN: routes/console.php:47 runs
    // `pnlcs:auto-charge --rescue` everyFifteenMinutes(). Twenty-six hours of
    // it, which is past the lease, past the cap and past Stripe's retention.
    //
    // Against the build this test was written for, this loop presented the same
    // charge a hundred and five times: each replay wrote a fresh claimed_at,
    // the window was measured from claimed_at, and so the row was for ever
    // exactly fifteen minutes old. The sweep built to rescue the row was the
    // thing preventing it from ever resting, and past the twenty-fourth hour
    // the gateway had pruned the key and the presentations were second charges
    // with a familiar name.
    foreach (range(1, 26 * 4) as $ignored) {
        $this->travel(15)->minutes();
        consentRun(['--rescue' => true]);
    }

    $row = Attempt::forInvoice($invoice);

    expect($calls->count())->toBe(1 + Attempt::MAX_REPLAYS)
        // Parked, not merely left alone. A row that quietly stops being
        // replayed is the black hole the rescue sweep's own N4 branch closed,
        // coming back in a different doorway.
        ->and($row->state)->toBe(ChargeAttemptState::NeedsReview)
        ->and($row->next_attempt_at)->toBeNull()
        // At the gateway this was one request throughout, and the row says so.
        ->and($row->attempts)->toBe(1)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Unpaid->value)
        ->and(App\Models\Transaction::where('invoice_id', $invoice->id)->count())->toBe(0)
        // One person fetched, once — not one alert every fifteen minutes.
        ->and($sent)->toBe(1)
        // And it has left the sweep's queue, so it cannot starve the rows
        // behind it.
        ->and(Attempt::query()->stuck()->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// N4  An abandoned charge whose card is no longer chargeable
// ---------------------------------------------------------------------------

test('an abandoned charge whose card the customer has removed is left for a person, not skipped for ever', function () {
    consentOn();
    $calls = consentGateway();
    [$invoice, $card] = consentReady(100.0);

    // A run was killed with the charge in flight. Then the customer removed the
    // card through the panel's own Remove button.
    consentAbandoned($invoice, $card, 100.0, 20);
    $card->delete();

    consentRun(['--rescue' => true]);

    $output = Artisan::output();
    $row = Attempt::forInvoice($invoice);

    // It used to increment a 'skipped' counter and return — with no log line of
    // any severity — leaving the row in flight with a claim that only got
    // colder, and doing the same nothing every fifteen minutes for ever. The
    // money may have been on the customer's statement the whole time while the
    // invoice was reminded, fee'd and suspended.
    expect($calls->count())->toBe(0)
        ->and($row->state)->toBe(ChargeAttemptState::NeedsReview)
        ->and($output)->toContain('NEED A PERSON')
        ->and($output)->toContain('#'.$invoice->id);
});

test('an abandoned charge whose card the issuer has ended is left for a person', function () {
    consentOn();
    $calls = consentGateway();
    [$invoice, $card] = consentReady(100.0);

    // The reachable version of this: the charge was refused with a card-ending
    // code, Stripe's payment_intent.payment_failed webhook marked the card
    // requires_update independently, and the process was killed before the
    // answer was written down — which is exactly the crash the in_flight design
    // exists for.
    consentAbandoned($invoice, $card, 100.0, 20);
    $card->update(['status' => PaymentMethod::STATUS_REQUIRES_UPDATE]);

    consentRun(['--rescue' => true]);

    expect($calls->count())->toBe(0)
        ->and(Attempt::forInvoice($invoice)->state)->toBe(ChargeAttemptState::NeedsReview);
});

test('an abandoned charge with two cards and no default is left for a person', function () {
    consentOn();
    $calls = consentGateway();
    [$invoice, $card] = consentReady(100.0);

    consentAbandoned($invoice, $card, 100.0, 20);
    // A second card arrived and neither is marked. The charger will not guess
    // between two cards with somebody's money, so there is nothing to replay
    // with — and a replay is only ever provable against the card the attempt
    // was made on in any case.
    consentCard($invoice);

    consentRun(['--rescue' => true]);

    expect($calls->count())->toBe(0)
        ->and(Attempt::forInvoice($invoice)->state)->toBe(ChargeAttemptState::NeedsReview);
});

test('an unreplayable abandoned charge is announced once and then stops filling the sweep', function () {
    consentOn();
    $calls = consentGateway();
    [$invoice, $card] = consentReady(100.0);

    consentAbandoned($invoice, $card, 100.0, 20);
    $card->delete();

    $sent = 0;
    Illuminate\Support\Facades\Event::listen(Illuminate\Mail\Events\MessageSending::class, function () use (&$sent) { $sent++; });
    Setting::set('SystemEmailAddress', 'ops@example.test');

    foreach (range(1, 6) as $ignored) {
        consentRun(['--rescue' => true]);
        $this->travel(15)->minutes();
    }

    // One alert, not one every fifteen minutes — and the row has left stuck(),
    // so it no longer sorts to the front of a sweep that had no answer for it.
    expect($sent)->toBe(1)
        ->and($calls->count())->toBe(0)
        ->and(Attempt::query()->stuck()->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// The operator's stop button
// ---------------------------------------------------------------------------

test('a run stops presenting cards the moment the operator switches the feature off', function () {
    consentOn();

    $client = App\Models\Client::factory()->create();
    $invoices = [];

    foreach ([100.0, 90.0, 80.0] as $total) {
        $invoices[] = Invoice::factory()->create([
            'client_id' => $client->id,
            'status' => InvoiceStatus::Unpaid->value,
            'subtotal' => $total,
            'total' => $total,
            'due_date' => now()->addDay(),
        ]);
    }

    consentCard($invoices[0]);

    // Thrown from inside the first gateway call, which is the shape of an
    // operator watching something go wrong and reaching for the switch.
    $calls = consentGateway(function ($invoice, $method, $amount) {
        Setting::set('AutoChargeEnabled', '0');

        return ['success' => true, 'status' => 'succeeded', 'transaction_id' => 'pi_'.$invoice->id, 'amount' => $amount];
    });

    consentRun();

    // run() asked the switch once, before anything was read, and never again —
    // so the rest of the morning's cards were presented anyway, for up to eight
    // minutes and twenty seconds at the shipped limit and pacing. All three
    // were charged. Now the first one is, and the switch is believed.
    expect($calls->count())->toBe(1)
        ->and($invoices[1]->fresh()->status)->toBe(InvoiceStatus::Unpaid->value)
        ->and($invoices[2]->fresh()->status)->toBe(InvoiceStatus::Unpaid->value)
        ->and(Attempt::forInvoice($invoices[1]))->toBeNull()
        ->and(Attempt::forInvoice($invoices[2]))->toBeNull();
});

// ---------------------------------------------------------------------------
// Invariant zero, measured again after this round's changes
// ---------------------------------------------------------------------------

test('with the switch off this round changed nothing that runs', function () {
    // Not asserted — measured, with the data that would make every new refusal
    // fire if anything read it: a service with renewal switched off, a domain
    // the customer stopped, an addon on a cancelled service, a stored card, an
    // abandoned in-flight attempt, and a removed card from a gateway that
    // cannot detach.
    consentGateway();

    [$invoice, $card, $service] = consentServiceInvoice();
    $service->update(['auto_renew' => false]);
    consentAbandoned($invoice, $card, 100.0, 60);
    consentDomainInvoice(['payment_method' => 'none']);
    consentAddonInvoice(['status' => 'cancelled']);

    $dead = PaymentMethod::create([
        'client_id' => $invoice->client_id,
        'gateway_name' => 'iyzico',
        'payment_type' => 'cc',
        'remote_token' => 'ct_1',
    ]);
    $dead->requestGatewayDetach();
    $dead->delete();

    $queries = [];
    Illuminate\Support\Facades\DB::listen(function ($query) use (&$queries) {
        $queries[] = $query->sql;
    });

    consentRun();
    consentRun(['--rescue' => true]);

    $reads = array_values(array_filter($queries, fn (string $sql) => str_contains($sql, 'invoices')
        || str_contains($sql, 'invoice_items')
        || str_contains($sql, 'invoice_charge_attempts')
        || str_contains($sql, 'services')
        || str_contains($sql, 'domains')
        || str_contains($sql, 'service_addons')
        || str_contains($sql, 'payment_methods')
        || str_contains($sql, 'gateway_settings')));

    $writes = array_values(array_filter($queries, fn (string $sql) => ! str_starts_with(strtolower($sql), 'select')));

    expect($reads)->toBe([])
        ->and($writes)->toBe([])
        // Both commands ask the master switch and stop. Two runs, two reads.
        ->and(count($queries))->toBe(2)
        ->and(Attempt::forInvoice($invoice)->state)->toBe(ChargeAttemptState::InFlight);
});
