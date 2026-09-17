<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesClient;
use App\Models\ActivityLog;
use App\Models\PaymentMethod;
use App\Services\Module\ModuleRegistry;
use App\Support\AutoCharge;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentMethodController extends Controller
{
    use ResolvesClient;

    public function index()
    {
        $methods = PaymentMethod::where('client_id', $this->getClientId())
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get();

        // Only shown where it means something. With the shop's own switch off
        // nothing charges a card at all, and a toggle for a thing that does not
        // happen is worse than no toggle: the customer who switches it off
        // believes they have stopped something, and the customer who leaves it
        // on believes they have agreed to something.
        $autoChargeOffered = AutoCharge::enabled();
        $autoCharge = $autoChargeOffered && (bool) $this->currentClient()?->auto_charge;

        // Storing a card is offered only where one could be charged: the shop
        // has switched card collection on AND has a gateway that can take a
        // payment with the customer absent. Collecting card details for a
        // gateway nothing will ever present them to is worse than not offering
        // it, and the customer cannot tell the difference from the outside.
        $cardStorageOffered = $autoChargeOffered && $this->vaultingGateway() !== null;

        return view('client.payment-methods.index', compact('methods', 'autoChargeOffered', 'autoCharge', 'cardStorageOffered'));
    }

    /**
     * The customer's own answer to automatic card payment.
     *
     * An operator-level switch is not consent, and this is the other half of
     * it: one click, on the page where their cards already live, and the
     * charger's candidate query stops loading their invoices altogether.
     *
     * It takes effect immediately and it reaches a dunning cycle that has
     * already started — a card that was declined yesterday has a scheduled
     * retry sitting on invoice_charge_attempts, and that row is only ever acted
     * on by way of the candidate query this switch removes them from. There is
     * nothing to unwind and nothing that keeps running.
     *
     * Refused outright while the shop is not collecting by card, for the same
     * reason the card is not rendered: there is no state to have an opinion
     * about, and a POST that quietly wrote one would be a promise the panel
     * cannot keep. A saved preference survives the shop switching off and on
     * again untouched.
     */
    public function autoCharge(Request $request)
    {
        abort_unless(AutoCharge::enabled(), 404);

        // currentClient(), not a lookup by id: the trait is what ties the
        // signed-in login to the account it is allowed to act on, and a login
        // that belongs to two accounts must change the one it is looking at.
        $client = $this->currentClient();

        abort_if($client === null, 403);

        $client->update(['auto_charge' => $request->boolean('auto_charge')]);

        return back()->with('success', __('client.payment_methods.auto_charge_updated'));
    }

    /**
     * Store a bank account reference (no secrets — account holder + masked
     * digits only). Card storage requires a tokenising gateway and is added
     * per-gateway.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'description'  => ['required', 'string', 'max:255'],
            'payment_type' => ['required', 'in:BankAccount'],
            'last_four'    => ['nullable', 'digits:4'],
        ]);

        PaymentMethod::create([
            'client_id'    => $this->getClientId(),
            'description'  => $validated['description'],
            'gateway_name' => 'banktransfer',
            'payment_type' => $validated['payment_type'],
            'last_four'    => $validated['last_four'] ?? null,
        ]);

        return back()->with('success', __('client.payment_methods.added'));
    }

    public function setDefault(PaymentMethod $paymentMethod)
    {
        abort_if($paymentMethod->client_id !== $this->getClientId(), 403);

        PaymentMethod::where('client_id', $this->getClientId())->update(['is_default' => false]);
        $paymentMethod->update(['is_default' => true]);

        return back()->with('success', __('client.payment_methods.default_updated'));
    }

    /**
     * Remove a stored method.
     *
     * TWO HALVES, AND ONLY ONE OF THEM HAPPENS HERE. The row is deleted, which
     * is what stops PNLCS using it — the charger never loads a trashed row and
     * StripeModule refuses one outright — and that half is immediate,
     * unconditional and cannot fail on anything outside this server.
     *
     * The other half is the gateway, which is still holding the card. It is
     * recorded rather than done, because doing it here would put a live HTTP
     * request to a third party inside a customer's own click: a gateway having
     * a slow morning would hang the page, and a gateway that answered with a
     * 500 would leave the customer looking at an error over a card that is in
     * fact still stored — or, worse, a deleted row and a live token, with
     * nothing anywhere that would ever try again. pnlcs:detach-payment-methods
     * carries it out on the scheduler, retries until the gateway confirms, and
     * tells the operator about anything it cannot finish.
     *
     * Bank-account references are untouched by any of that: they have no token,
     * so nothing is recorded and no sweep ever looks at them.
     */
    public function destroy(PaymentMethod $paymentMethod)
    {
        abort_if($paymentMethod->client_id !== $this->getClientId(), 403);

        $paymentMethod->requestGatewayDetach();
        $paymentMethod->delete();

        return back()->with('success', __('client.payment_methods.removed'));
    }

    /**
     * The screen where a customer stores a card.
     *
     * Gated twice over, and the two gates are different questions. The shop has
     * to be collecting by card at all (AutoChargeEnabled), and there has to be
     * a gateway that can store one and charge it later. Either one missing and
     * this page does not exist — not a disabled button, not an explanation: a
     * 404, because a form that cannot store a card must not be reachable with
     * a card number in front of it.
     *
     * The session is opened before the page is rendered rather than by a second
     * request from the browser, because there is nothing to show without it:
     * the form cannot be built without the gateway's client secret, and an
     * error is better delivered as a page than as a form that fails on submit.
     */
    public function createCard()
    {
        abort_unless(AutoCharge::enabled(), 404);

        $gateway = $this->vaultingGateway();

        abort_if($gateway === null, 404);

        $client = $this->currentClient();

        abort_if($client === null, 403);

        [$name, $module] = $gateway;

        $session = $module->beginVaulting($client);

        if (! ($session['success'] ?? false)) {
            // Whatever the gateway said goes to the log, not to the customer:
            // "Stripe secret key not configured" is an operator's sentence and
            // telling a customer their card was refused when the shop has not
            // finished setting itself up is worse than telling them nothing.
            Log::error('Card vaulting: the gateway would not open a session', [
                'client' => $client->id,
                'gateway' => $name,
                'error' => $session['message'] ?? null,
            ]);

            return redirect()->route('client.payment-methods.index')
                ->with('error', __('client.payment_methods.card_unavailable'));
        }

        // The page needs three things and is given nothing else. What storing
        // a card means for this customer is said in the consent text on the
        // page itself rather than inferred from their current setting, because
        // the tick is what they are agreeing to and it has to read the same way
        // whichever state they arrived in.
        return view('client.payment-methods.add-card', [
            'gateway' => $name,
            'publishableKey' => $this->publishableKey($name),
            'clientSecret' => $session['client_secret'] ?? null,
        ]);
    }

    /**
     * The browser has finished with the gateway. Store the card.
     *
     * What arrives is an id and a tick in a consent box, and neither is taken
     * at face value. The id is handed to the gateway module, which reads the
     * session back from the gateway and refuses one that is unfinished or that
     * belongs to another account; the card itself is written by the same code
     * the gateway's own webhook runs, so the two cannot disagree and whichever
     * arrives second changes nothing.
     *
     * The consent is required and recorded. Stripe's own rule for charging a
     * card while the customer is away is explicit: "make sure that you
     * explicitly collect consent from the customer for this specific use...
     * Make sure you keep a record of your customer's written agreement to these
     * terms" (https://docs.stripe.com/payments/save-and-reuse?payment-ui=elements,
     * fetched 2026-09-16). The tick is what is collected and ActivityLog is
     * where the record goes, with the moment and the address it came from,
     * beside every other thing this customer has done.
     */
    public function storeCard(Request $request)
    {
        abort_unless(AutoCharge::enabled(), 404);

        $gateway = $this->vaultingGateway();

        abort_if($gateway === null, 404);

        $client = $this->currentClient();

        abort_if($client === null, 403);

        $validated = $request->validate([
            'session_id' => ['required', 'string', 'max:255'],
            // Not a checkbox that may be absent: the page will not submit
            // without it, and a POST that arrives without it is not a customer
            // who has agreed to anything.
            'consent' => ['accepted'],
        ]);

        [$name, $module] = $gateway;

        $result = $module->confirmVaulting($client, $validated['session_id']);

        if (! ($result['success'] ?? false)) {
            Log::warning('Card vaulting: the gateway would not confirm the card', [
                'client' => $client->id,
                'gateway' => $name,
                'error' => $result['message'] ?? null,
            ]);

            return redirect()->route('client.payment-methods.index')
                ->with('error', __('client.payment_methods.card_not_stored'));
        }

        // The agreement, in the customer's own file, with the moment and the
        // address it was given from. ActivityLog::log() records the IP itself.
        ActivityLog::log(
            "Client #{$client->id} stored a card with {$name} and agreed to automatic payment of their invoices",
            $client->display_name ?: ('client #'.$client->id),
            $client->id,
        );

        // Consent given here is consent, and it must not be filed against an
        // account that has previously switched automatic payment off — that
        // customer has just changed their mind, in writing, on this page. The
        // switch on the payment methods page remains the way back out.
        if (! $client->auto_charge) {
            $client->update(['auto_charge' => true]);
        }

        return redirect()->route('client.payment-methods.index')
            ->with('success', __('client.payment_methods.card_stored'));
    }

    /**
     * The gateway this shop stores cards with, or null if there is none.
     *
     * The registry's list is the charger's list — one definition, so the offer
     * to store a card and the willingness to charge it can never disagree. The
     * first is taken when there are several, which today cannot happen: Stripe
     * is the only module implementing the capability. A second one would need a
     * choice on the page, and a choice is what the customer would then be given
     * rather than what this method would guess.
     *
     * @return array{0: string, 1: \App\Contracts\TokenizableGatewayInterface}|null
     */
    private function vaultingGateway(): ?array
    {
        $gateways = app(ModuleRegistry::class)->tokenisedGateways();

        if ($gateways === []) {
            return null;
        }

        $name = (string) array_key_first($gateways);

        return [$name, $gateways[$name]];
    }

    /**
     * The key the browser is allowed to have.
     *
     * Publishable is the whole point of it: it identifies the account to
     * Stripe.js and can do nothing on its own. The secret key never leaves the
     * server, is never passed to a view, and is read only by the module.
     */
    private function publishableKey(string $gateway): ?string
    {
        $row = \App\Models\GatewaySettings::where('gateway', $gateway)
            ->where('setting', 'publishable_key')
            ->first();

        $key = trim((string) $row?->value);

        return $key === '' ? null : $key;
    }

}
