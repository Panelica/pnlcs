<?php

namespace Modules\Gateways\Stripe;

use App\Contracts\GatewayModuleInterface;
use App\Contracts\TokenizableGatewayInterface;
use App\Enums\GatewayEventClaim;
use App\Models\Client;
use App\Models\GatewayCustomer;
use App\Models\GatewayEvent;
use App\Models\GatewaySettings;
use App\Models\Invoice;
use App\Models\PaymentMethod;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class StripeModule implements GatewayModuleInterface, TokenizableGatewayInterface
{
    public function getModuleName(): string
    {
        return "Stripe";
    }

    public function isTokenised(): bool
    {
        return true;
    }

    public function getConfigFields(): array
    {
        return [
            ["name" => "publishable_key", "label" => "Publishable Key",         "type" => "text", "required" => true],
            ["name" => "secret_key",      "label" => "Secret Key",              "type" => "password", "required" => true],
            ["name" => "webhook_secret",  "label" => "Webhook Signing Secret",  "type" => "password"],
        ];
    }

    /** How stale a signed webhook may be, matching Stripe's own libraries. */
    private const WEBHOOK_TOLERANCE_SECONDS = 300;
    private function getSetting(string $key): ?string
    {
        $row = GatewaySettings::where("gateway", "stripe")->where("setting", $key)->first();
        return $row?->value;
    }

    public function capture(Invoice $invoice, float $amount, array $params = []): array
    {
        $secretKey = $this->getSetting("secret_key");
        if (!$secretKey) {
            return ["success" => false, "message" => "Stripe secret key not configured."];
        }

        $currency = strtolower($params["currency"] ?? shop_currency_code());
        $amountCents = (int) round($amount * 100);

        // No idempotency key here, deliberately. This endpoint is reached from
        // the customer's own browser every time the pay form is opened, and a
        // key would have Stripe replay the first intent's client_secret for the
        // next twenty-four hours. That is usually harmless — the customer is
        // retrying the same payment with another card — but an intent that has
        // since succeeded or been cancelled cannot be confirmed again, and the
        // browser would be handed a secret that can no longer take the money.
        // Nothing is lost without one: an unconfirmed payment intent costs
        // nothing and expires on its own.
        $response = Http::asForm()
            ->withToken($secretKey)
            ->post("https://api.stripe.com/v1/payment_intents", [
                "amount"                      => $amountCents,
                "currency"                    => $currency,
                "payment_method_types[]"      => "card",
                "description"                 => "Invoice #" . ($invoice->invoice_num ?? $invoice->id),
                "metadata[invoice_id]"        => $invoice->id,
                "metadata[invoice_num]"       => $invoice->invoice_num ?? $invoice->id,
            ]);

        if (!$response->successful()) {
            $error = $response->json("error.message", "Unknown error");
            Log::error("Stripe: create payment intent failed", [
                "invoice" => $invoice->id,
                "status"  => $response->status(),
                "error"   => $error,
            ]);
            return ["success" => false, "message" => "Stripe error: " . $error];
        }

        $intent = $response->json();
        return [
            "success"       => true,
            "client_secret" => $intent["client_secret"] ?? null,
            "intent_id"     => $intent["id"] ?? null,
        ];
    }

    public function refund(string $transactionId, float $amount): array
    {
        $secretKey = $this->getSetting("secret_key");
        if (!$secretKey) {
            return ["success" => false, "message" => "Stripe secret key not configured."];
        }

        $amountCents = (int) round($amount * 100);

        // No idempotency key on a refund, and this is the one place where going
        // without is safer than guessing at one.
        //
        // A key has to be stable across a retry of the same refund and
        // different for a new one. Everything this method is given fails that
        // test: PaymentService::refundInvoice() always refunds against the same
        // settling transaction id, and the admin refund form takes a free-text
        // amount, so two genuine partial refunds of the same size against the
        // same payment are indistinguishable here. Keyed on those two, Stripe
        // would replay the first refund and return its id
        // (https://docs.stripe.com/api/idempotent_requests — "Subsequent
        // requests with the same key return the same result"), this method
        // would report success, and PNLCS would write a second refund
        // transaction and hand the customer credit for money that never left.
        // Books saying fifty while Stripe moved twenty-five is worse than the
        // duplicate a key would have prevented.
        //
        // The identity a key needs does not exist yet: the refund transaction
        // row is written after this call returns, and the gateway contract has
        // no slot for a caller-supplied key. Adding one is a change to
        // PaymentService and to every other gateway, and belongs in its own
        // piece of work rather than smuggled in behind a header.
        $response = Http::asForm()
            ->withToken($secretKey)
            ->post("https://api.stripe.com/v1/refunds", [
                "payment_intent" => $transactionId,
                "amount"         => $amountCents,
            ]);

        if (!$response->successful()) {
            $error = $response->json("error.message", "Unknown error");
            Log::error("Stripe: refund failed", [
                "transaction" => $transactionId,
                "status"      => $response->status(),
                "error"       => $error,
            ]);
            return ["success" => false, "message" => "Stripe refund error: " . $error];
        }

        $data = $response->json();
        return [
            "success"        => true,
            "refund_id"      => $data["id"] ?? null,
            "status"         => $data["status"] ?? "succeeded",
            "transaction_id" => $transactionId,
        ];
    }

    /**
     * A name Stripe can recognise a repeat of this exact request by, WORKED OUT
     * FROM THE REQUEST ITSELF — which is the fallback, not the main road.
     *
     * Stripe keeps the answer it gave the first time a key was used and hands
     * that same answer back for the next twenty-four hours instead of doing
     * the work again. So a POST that timed out on the network and was sent
     * again creates one customer and takes one payment, not two.
     *
     * A caller with a memory of its own supplies the key instead, and
     * chargeStoredMethod() prefers it: written down before the first POST and
     * handed back unchanged on every repeat, a stored key cannot drift, while
     * a derived one is exactly as stable as its least stable input. THAT IS NOT
     * A THEORETICAL PREFERENCE. This digest is keyed on config('app.key'), and
     * an operator who rotates APP_KEY — documented by Laravel, supported by
     * config/app.php's previous_keys, survived by App\Casts\EncryptedValue
     * whether it is rotated gracefully or not — changed the key underneath an
     * in-flight charge with nothing anywhere signalling it. The replay went out
     * under a key Stripe had never seen, Stripe had no saved result to answer
     * from, and the card was charged a second time for money that had very
     * possibly already left it. InvoiceChargeAttempt::mintIdempotencyKey()
     * carries the repair.
     *
     * What remains here is still right for what it is used for. It is derived
     * rather than random because a caller with nothing written down needs the
     * same figures to give the same key; everything that varies goes into it,
     * the amount above all, because Stripe treats the same key arriving with
     * different parameters as an error rather than as a repeat; and the digest
     * is keyed on the application key so that two installations sharing one
     * Stripe account do not both claim invoice #5 — which holds until one of
     * them is a restored image of the other, when they share that key too. A
     * stored random key needs none of those arguments.
     *
     * Only two calls reach this now, and both are calls no human is watching:
     * the customer record a card hangs off, and an off-session charge whose
     * caller brought no key. The two browser-driven calls — creating an intent
     * to pay, and refunding one — carry no key at all, for reasons written
     * where each of them sends.
     *
     * What a derived key does not do is worth stating plainly. It protects
     * "charge invoice N this exact amount", not "charge invoice N", because the
     * amount is inside it; and Stripe prunes keys after twenty-four hours ("We
     * generate a new request if a key is reused after the original is pruned",
     * https://docs.stripe.com/api/idempotent_requests), so a retry a day later,
     * or after a late fee has moved the balance, is a genuinely new request. A
     * stored key changes none of that: what it changes is that the string is no
     * longer recomputed, so it can no longer come out different.
     */
    private function idempotencyKey(string $scope, array $parts): string
    {
        $digest = hash_hmac("sha256", implode("|", $parts), (string) config("app.key"));

        return "pnlcs-{$scope}-" . substr($digest, 0, 40);
    }

    /**
     * Currencies Stripe takes at face value, with no minor unit under them.
     *
     * "For the following zero-decimal currencies, the charge and the amount are
     * the same, without requiring multiplication. For example, to charge 500
     * JPY, provide an amount value of 500."
     * https://docs.stripe.com/currencies#zero-decimal
     *
     * Copied from that list as it stands, minus UGX, which appears both there
     * and in the special cases below — and the special case is the one that
     * governs a charge.
     */
    private const ZERO_DECIMAL_CURRENCIES = [
        "bif", "clp", "djf", "gnf", "jpy", "kmf", "krw", "mga",
        "pyg", "rwf", "vnd", "vuv", "xaf", "xof", "xpf",
    ];

    /**
     * Currencies that are zero-decimal in fact but two-decimal on the wire.
     *
     * "ISK transitioned to a zero-decimal currency, but backward compatibility
     * requires you to represent it as a two-decimal value, where the decimal
     * amount is always 00. For example, to charge 5 ISK, provide an amount
     * value of 500. You can't charge fractions of ISK." The same is documented
     * word for word for UGX.
     * https://docs.stripe.com/currencies#special-cases
     *
     * HUF and TWD are in that table too and are deliberately absent from here:
     * Stripe treats them as zero-decimal for payouts only, and says you can
     * charge two-decimal amounts, which is what the ordinary path already does.
     */
    private const WHOLE_UNIT_CURRENCIES = ["isk", "ugx"];

    /**
     * An amount in the units Stripe wants to be told about it in.
     *
     * "All API requests expect amount values in the currency's minor unit ...
     * Enter 1099 to charge 10.99 USD (or any other two-decimal currency). Enter
     * 10 to charge 10 JPY (or any other zero-decimal currency)."
     * https://docs.stripe.com/currencies
     *
     * Multiplying by a hundred regardless is how a shop selling in yen charges
     * a hundred times the invoice, and an off-session charge is exactly the
     * place where nobody would notice until the chargebacks arrived. The shop's
     * currency is operator-set free text with no validation behind it, so this
     * has to hold for whatever is typed in.
     *
     * KNOWN DEFECT, LEFT ALONE ON PURPOSE: capture(), refund() and
     * verifyPaymentIntent() still multiply and divide by a hundred
     * unconditionally, and so does webhookPaymentSucceeded(). They are wrong in
     * both directions at once, which is the only reason a zero-decimal shop's
     * books currently agree with themselves: the charge is a hundred times too
     * big and the amount recorded against it is a hundredth of what Stripe
     * took. Correcting only the outbound half would turn a loud disaster into a
     * quiet one, so those four move together, with their return legs, as a
     * deliberate change of their own.
     */
    private function minorUnits(float $amount, string $currency): int
    {
        $currency = strtolower($currency);

        if (in_array($currency, self::ZERO_DECIMAL_CURRENCIES, true)) {
            return (int) round($amount);
        }

        if (in_array($currency, self::WHOLE_UNIT_CURRENCIES, true)) {
            // Fractions cannot be charged at all, so the rounding happens on
            // the whole unit and the two decimal places are always zero.
            return (int) round($amount) * 100;
        }

        return (int) round($amount * 100);
    }

    /**
     * The same conversion read back, so that what is reported is what was taken.
     *
     * Cast rather than left to the division: 5000/100 is the int 50 in PHP
     * while 5050/100 is the float 50.5, and a caller told to expect a float
     * should not have the type depend on whether the amount divided evenly.
     */
    private function majorUnits(int $minorAmount, string $currency): float
    {
        $currency = strtolower($currency);

        if (in_array($currency, self::ZERO_DECIMAL_CURRENCIES, true)) {
            return (float) $minorAmount;
        }

        return (float) ($minorAmount / 100);
    }

    public function getPaymentForm(Invoice $invoice): string
    {
        $publishableKey = $this->getSetting("publishable_key") ?? "";
        $amount         = number_format($invoice->amountDue(), 2, ".", "");
        $invoiceId      = (int) $invoice->id;

        if (!$publishableKey) {
            return "<div class=\"alert alert-danger\">Stripe is not configured. Please contact support.</div>";
        }

        $safeKey      = htmlspecialchars($publishableKey, ENT_QUOTES, "UTF-8");
        $intentUrl    = url("/gateway/stripe/intent/{$invoiceId}");
        $confirmUrl   = url("/gateway/stripe/confirm/{$invoiceId}");

        return <<<HTML
<div class="my-3">
    <div id="stripe-card-element" class="form-control p-3" style="min-height:42px;"></div>
    <div id="stripe-card-errors" class="text-danger mt-1 small"></div>
    <button id="stripe-submit-btn" class="btn btn-primary mt-3 w-100" type="button">
        Pay {$amount}
    </button>
    <div id="stripe-message" class="mt-2"></div>
</div>
<script src="https://js.stripe.com/v3/"></script>
<script>
(function() {
    var stripe  = Stripe("{$safeKey}");
    var elements = stripe.elements();
    var card    = elements.create("card", { style: { base: { fontSize: "16px" } } });
    card.mount("#stripe-card-element");

    card.addEventListener("change", function(e) {
        document.getElementById("stripe-card-errors").textContent = e.error ? e.error.message : "";
    });

    document.getElementById("stripe-submit-btn").addEventListener("click", function() {
        var btn = this;
        btn.disabled = true;
        btn.textContent = "Processing...";

        fetch("{$intentUrl}", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": document.querySelector("meta[name=csrf-token]") ? document.querySelector("meta[name=csrf-token]").content : ""
            }
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success) {
                document.getElementById("stripe-card-errors").textContent = data.message || "Setup failed";
                btn.disabled = false;
                btn.textContent = "Pay {$amount}";
                return;
            }
            return stripe.confirmCardPayment(data.client_secret, {
                payment_method: { card: card }
            });
        })
        .then(function(result) {
            if (!result) return;
            if (result.error) {
                document.getElementById("stripe-card-errors").textContent = result.error.message;
                btn.disabled = false;
                btn.textContent = "Pay {$amount}";
            } else if (result.paymentIntent.status === "succeeded") {
                fetch("{$confirmUrl}", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": document.querySelector("meta[name=csrf-token]") ? document.querySelector("meta[name=csrf-token]").content : ""
                    },
                    body: JSON.stringify({ payment_intent_id: result.paymentIntent.id })
                })
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    if (res.success) {
                        window.location.href = res.redirect_url || "/client/invoices/{$invoiceId}?payment=success";
                    } else {
                        document.getElementById("stripe-message").innerHTML = "<div class=\"alert alert-danger\">" + (res.message || "Confirmation failed") + "</div>";
                        btn.disabled = false;
                        btn.textContent = "Pay {$amount}";
                    }
                });
            }
        })
        .catch(function(err) {
            document.getElementById("stripe-card-errors").textContent = "Network error. Please try again.";
            btn.disabled = false;
            btn.textContent = "Pay {$amount}";
        });
    });
})();
</script>
HTML;
    }

    /**
     * Hand back the secret that lets the cardholder finish an off-session
     * charge their bank stopped.
     *
     * When an unattended charge comes back requires_action the money has not
     * moved and the card is fine: the issuer wants the person. That intent is
     * still sitting at Stripe waiting to be confirmed, and
     * "stripe.handleNextAction ... finish[es] confirmation of a PaymentIntent
     * with the requires_action status" (https://docs.stripe.com/js/
     * payment_intents/handle_next_action, fetched 2026-09-16) — which is what
     * this secret is for.
     *
     * FINISHING THE SAME INTENT IS THE SAFE ROUTE, and not only the cheap one.
     * The alternative is a fresh PaymentIntent from the ordinary pay form,
     * which is a second authorisation for the same invoice sitting beside the
     * first: if both are ever completed the customer has paid twice and the
     * second becomes account credit. Completing this one cannot do that. It
     * carries the invoice in its metadata, so both the webhook and the confirm
     * endpoint credit it by the same transaction id, and PaymentService refuses
     * that pair the second time it sees it.
     *
     * NOTHING IS TAKEN ON THE STRENGTH OF THE CALLER'S WORD. The intent is read
     * from Stripe, it has to name this invoice, and it has to still be waiting
     * for the cardholder. An intent that has since succeeded, been cancelled or
     * fallen back to requires_payment_method is refused — handleNextAction
     * "will throw an error if the PaymentIntent has a different status", and
     * the customer is better served by the ordinary pay form than by a button
     * that throws.
     *
     * The client secret is the browser's to hold: it can confirm this one
     * intent and do nothing else. The secret API key stays here.
     */
    public function resumeAuthentication(Invoice $invoice, string $intentId): array
    {
        $secretKey = $this->getSetting("secret_key");
        if (!$secretKey) {
            return ["success" => false, "message" => "Stripe secret key not configured."];
        }

        try {
            $response = Http::withToken($secretKey)
                ->get("https://api.stripe.com/v1/payment_intents/" . urlencode($intentId));
        } catch (ConnectionException $e) {
            return ["success" => false, "message" => "Stripe could not be reached."];
        }

        if (!$response->successful()) {
            return ["success" => false, "message" => "Stripe: payment intent lookup failed."];
        }

        $intent = (array) $response->json();

        if ((int) ($intent["metadata"]["invoice_id"] ?? 0) !== (int) $invoice->id) {
            Log::warning("Stripe: refused to resume an authentication for another invoice", [
                "invoice" => $invoice->id,
                "intent"  => $intent["id"] ?? $intentId,
            ]);

            return ["success" => false, "message" => "That payment does not belong to this invoice."];
        }

        if (($intent["status"] ?? "") !== "requires_action") {
            return [
                "success" => false,
                "message" => "That payment is no longer waiting to be confirmed (status: " . ($intent["status"] ?? "unknown") . ").",
            ];
        }

        return [
            "success"       => true,
            "client_secret" => $intent["client_secret"] ?? null,
            "intent_id"     => $intent["id"] ?? $intentId,
        ];
    }

    /**
     * Verify a PaymentIntent server-side before crediting an invoice.
     * The intent id arrives from the browser, so confirm with Stripe that it
     * actually succeeded, that it belongs to THIS invoice, and use Stripe's
     * captured amount — never a client-supplied value.
     */
    public function verifyPaymentIntent(string $intentId, int $expectedInvoiceId): array
    {
        $secretKey = $this->getSetting("secret_key");
        if (!$secretKey) {
            return ["success" => false, "message" => "Stripe secret key not configured."];
        }

        $response = Http::withToken($secretKey)
            ->get("https://api.stripe.com/v1/payment_intents/{$intentId}");

        if (!$response->successful()) {
            return ["success" => false, "message" => "Stripe: payment intent lookup failed."];
        }

        $intent = $response->json();

        if (($intent["status"] ?? null) !== "succeeded") {
            return ["success" => false, "message" => "Payment not completed."];
        }

        $intentInvoiceId = (int) ($intent["metadata"]["invoice_id"] ?? 0);
        if ($intentInvoiceId !== $expectedInvoiceId) {
            Log::warning("Stripe: payment intent invoice mismatch", [
                "intent"   => $intentId,
                "expected" => $expectedInvoiceId,
                "actual"   => $intentInvoiceId,
            ]);
            return ["success" => false, "message" => "Payment does not match this invoice."];
        }

        return [
            "success"        => true,
            "transaction_id" => $intent["id"] ?? $intentId,
            "amount"         => (int) ($intent["amount_received"] ?? 0) / 100,
        ];
    }

    /**
     * Open a session in which the customer can store a card.
     *
     * Nothing is charged: a SetupIntent collects and confirms a card so that
     * it can be used later, and the client_secret handed back is what Stripe.js
     * needs to finish the job in the browser. The card number itself never
     * reaches this server, which is the point of doing it this way.
     *
     * The 'usage' parameter is deliberately not sent. It now defaults to
     * off_session, which is exactly what this is for, and naming on_session
     * here would set up the card for the one case it will never be used in.
     */
    public function beginVaulting(Client $client): array
    {
        $secretKey = $this->getSetting("secret_key");
        if (!$secretKey) {
            return ["success" => false, "message" => "Stripe secret key not configured."];
        }

        $customer = $this->resolveCustomer($client, $secretKey);
        if (!($customer["success"] ?? false)) {
            return ["success" => false, "message" => $customer["message"] ?? "Stripe customer could not be created."];
        }

        $customerId = $customer["customer_id"];

        // No idempotency key on this one, and on purpose. A SetupIntent is
        // consumed by the browser that confirms it, so a customer who comes
        // back to the card form an hour later needs a live intent of their
        // own — replaying the spent one would hand them a secret that can no
        // longer store anything. Nothing is charged here, so a second intent
        // left unused costs nothing.
        $response = Http::asForm()
            ->withToken($secretKey)
            ->post("https://api.stripe.com/v1/setup_intents", [
                "customer"               => $customerId,
                // Cards only, and named rather than left to the dashboard.
                // payment_method_types is "the list of payment method types
                // (e.g. card) that this SetupIntent is allowed to set up"
                // (https://docs.stripe.com/api/setup_intents/create), whereas
                // automatic_payment_methods offers whatever is switched on in
                // the dashboard — SEPA debit, Link, Cash App, a bank account.
                // Every one of those would be stored here as a card, described
                // by nothing, and then handed to chargeStoredMethod(), which
                // asks Stripe for a card payment and is refused. Refusing to
                // vault it in the first place is the honest end of that.
                "payment_method_types[]" => "card",
                "metadata[client_id]"    => $client->id,
            ]);

        if (!$response->successful()) {
            $error = $response->json("error.message", "Unknown error");
            Log::error("Stripe: create setup intent failed", [
                "client" => $client->id,
                "status" => $response->status(),
                "error"  => $error,
            ]);
            return ["success" => false, "message" => "Stripe error: " . $error];
        }

        $intent = $response->json();

        return [
            "success"         => true,
            "client_secret"   => $intent["client_secret"] ?? null,
            "customer_id"     => $customerId,
            "setup_intent_id" => $intent["id"] ?? null,
        ];
    }

    /**
     * The browser says the card went in. Ask Stripe, then store it.
     *
     * The webhook is the way a card normally becomes a row here, and on a
     * shop whose endpoint is registered and reachable it will get there on its
     * own. This is the same ending reached from the other side, and it exists
     * because the customer is standing in front of the screen right now: a
     * delivery that is thirty seconds late, an endpoint nobody registered, a
     * local installation that Stripe cannot reach at all, and the customer is
     * looking at a page that does not list the card they just typed in. They
     * type it again. Each attempt leaves another card attached to their Stripe
     * customer, and the shop that cannot receive webhooks never collects a
     * penny.
     *
     * NOTHING FROM THE BROWSER IS TRUSTED EXCEPT AS A QUESTION. The id is used
     * to ask Stripe what happened, and everything written comes out of Stripe's
     * answer: the status, the payment method, the customer, the client the
     * intent was opened for. A confirmation for somebody else's intent is
     * refused before anything is written, which is why the client is a
     * parameter here — checking afterwards would mean checking after the card
     * had already been stored against the other account.
     *
     * AND IT WRITES NOTHING OF ITS OWN. The retrieved object goes to
     * webhookSetupSucceeded(), the same handler the webhook feeds, so there is
     * one definition of what a stored card looks like and one place that
     * creates it. The two paths racing each other is the ordinary case and it
     * is safe: that handler is an updateOrCreate keyed on the token, so
     * whichever arrives second writes the same row again.
     */
    public function confirmVaulting(Client $client, string $sessionId): array
    {
        $secretKey = $this->getSetting("secret_key");
        if (!$secretKey) {
            return ["success" => false, "message" => "Stripe secret key not configured."];
        }

        try {
            $response = Http::withToken($secretKey)
                ->get("https://api.stripe.com/v1/setup_intents/" . urlencode($sessionId));
        } catch (ConnectionException $e) {
            Log::warning("Stripe: setup intent could not be read back", [
                "client"       => $client->id,
                "setup_intent" => $sessionId,
                "error"        => $e->getMessage(),
            ]);

            // Not an error the customer caused and not one they can fix. The
            // webhook is still coming, so the honest answer is "not yet".
            return ["success" => false, "message" => "Stripe could not be reached."];
        }

        if (!$response->successful()) {
            Log::warning("Stripe: setup intent lookup failed", [
                "client"       => $client->id,
                "setup_intent" => $sessionId,
                "status"       => $response->status(),
                "error"        => $response->json("error.message"),
            ]);

            return ["success" => false, "message" => "Stripe did not recognise that card setup."];
        }

        $intent = (array) $response->json();

        // Belongs to somebody else, or to no one. Either way this session is
        // not the caller's to finish, and the card is not written.
        if ((int) ($intent["metadata"]["client_id"] ?? 0) !== (int) $client->id) {
            Log::warning("Stripe: refused to finish a card setup opened for another client", [
                "client"       => $client->id,
                "setup_intent" => $intent["id"] ?? $sessionId,
            ]);

            return ["success" => false, "message" => "That card setup does not belong to this account."];
        }

        // 'succeeded' is the only status that means there is a card to keep.
        // requires_action and requires_payment_method are both live sessions
        // the customer can still finish; processing is Stripe still working.
        // None of them is a stored card, and storing one anyway would put a
        // token in the table that the charger would present and be refused on.
        if (($intent["status"] ?? "") !== "succeeded") {
            return [
                "success" => false,
                "message" => "That card has not finished being stored (status: " . ($intent["status"] ?? "unknown") . ").",
            ];
        }

        return $this->webhookSetupSucceeded($intent);
    }

    /**
     * Stop holding a customer's card.
     *
     * "Detaches a PaymentMethod object from a Customer. Detachment is permanent
     * and irreversible — once detached, a PaymentMethod can no longer be used
     * for payments or re-attached to a Customer."
     * (https://docs.stripe.com/api/payment_methods/detach, fetched 2026-09-16.)
     * That is exactly what a customer who removes their card is asking for, and
     * it is why this is never called from a screen: it cannot be undone, and it
     * must not be attempted on a row the customer has not actually removed.
     *
     * NO IDEMPOTENCY KEY, and none is wanted. The operation is naturally
     * idempotent — a second detach of the same token is answered with
     * resource_missing or payment_method_unexpected_state, both of which mean
     * the card is not attached to anybody, which is the result asked for. A key
     * would replay the first answer for a day and tell the sweep nothing about
     * what is true now.
     */
    public function detachStoredMethod(PaymentMethod $method): array
    {
        if (strtolower((string) $method->gateway_name) !== "stripe") {
            return ["success" => false, "message" => "That payment method was not stored with Stripe.", "retryable" => false];
        }

        $token = $method->remote_token;

        if (!is_string($token) || $token === "") {
            // Nothing was ever stored at Stripe for this row, so there is
            // nothing for Stripe to let go of.
            return ["success" => true, "message" => "No stored token to detach."];
        }

        $secretKey = $this->getSetting("secret_key");
        if (!$secretKey) {
            // The operator will put the key back. Until then the card is still
            // at Stripe and saying otherwise would be a lie in the table.
            return ["success" => false, "message" => "Stripe secret key not configured.", "retryable" => true];
        }

        try {
            $response = Http::asForm()
                ->withToken($secretKey)
                ->post("https://api.stripe.com/v1/payment_methods/" . urlencode($token) . "/detach");
        } catch (ConnectionException $e) {
            return ["success" => false, "message" => "Stripe could not be reached: " . $e->getMessage(), "retryable" => true];
        }

        if ($response->successful()) {
            return ["success" => true, "message" => "Detached at Stripe."];
        }

        $code = (string) $response->json("error.code", "");

        // Gone already. resource_missing is "The ID provided isn't valid.
        // Either the resource doesn't exist, or an ID for a different resource
        // has been provided"; payment_method_unexpected_state is what a
        // PaymentMethod that is attached to nobody answers a detach with
        // (https://docs.stripe.com/error-codes, fetched 2026-09-16). Neither
        // leaves a card attached to this customer, which is the only thing
        // being asked for, so both are finished rather than retried for ever.
        if (in_array($code, ["resource_missing", "payment_method_unexpected_state"], true)) {
            Log::info("Stripe: stored card was already gone", [
                "method" => $method->id,
                "code"   => $code,
            ]);

            return ["success" => true, "message" => "Stripe is no longer holding that card."];
        }

        // Keys for the wrong account, or live keys against a test token. Asking
        // again changes nothing; a person has to look.
        $permanent = $code === "livemode_mismatch";

        return [
            "success"   => false,
            "message"   => "Stripe error: " . $response->json("error.message", "Unknown error"),
            "retryable" => !$permanent,
        ];
    }

    /**
     * The Stripe customer this client's cards hang off, created if need be.
     *
     * Stored cards belong to a customer record at Stripe, and a client who
     * collects a new customer per card ends up with their cards scattered
     * across records that nothing joins back together. So an id already known
     * for this client is reused, and only a client who has never had one gets a
     * customer created.
     *
     * Two places are asked, in order. gateway_customers holds the id from the
     * moment the gateway hands it over; payment_methods holds it from whenever
     * the card was confirmed, which is where every id written before this table
     * existed still lives. A hit on the older place is copied to the newer one
     * on the way past, so each client migrates itself the first time it is
     * asked about.
     *
     * Soft-deleted methods count. A customer who removed their only card still
     * has the customer record at Stripe, and making a second one because ours
     * is in the bin is how the scattering starts.
     *
     * The create carries an idempotency key and its result is written down
     * immediately. The key alone was not enough: it collapses two attempts
     * inside twenty-four hours and nothing after that, so a customer who opened
     * the form, wandered off and came back the next day used to be given a
     * second customer — and two forms open at once raced with nothing but the
     * key between them. Writing the id down as soon as it exists is what makes
     * "one customer per client" true for longer than a day, and the unique key
     * on that table is what settles the race.
     *
     * @return array{success: bool, customer_id?: string, message?: string}
     */
    private function resolveCustomer(Client $client, string $secretKey): array
    {
        $known = GatewayCustomer::idFor("stripe", (int) $client->id);

        if ($known) {
            return ["success" => true, "customer_id" => $known];
        }

        $stored = PaymentMethod::withTrashed()
            ->where("client_id", $client->id)
            ->where("gateway_name", "stripe")
            ->whereNotNull("gateway_customer_id")
            ->latest("id")
            ->value("gateway_customer_id");

        if ($stored) {
            return [
                "success"     => true,
                "customer_id" => GatewayCustomer::remember("stripe", (int) $client->id, (string) $stored),
            ];
        }

        $fields = ["metadata[client_id]" => $client->id];

        // Sent only when there is something to send: Stripe refuses an empty
        // email outright, and a blank name is worse than no name on a customer
        // record somebody has to recognise in the dashboard.
        foreach (["email" => $client->email, "name" => $client->display_name] as $field => $value) {
            if (trim((string) $value) !== "") {
                $fields[$field] = trim((string) $value);
            }
        }

        $response = Http::asForm()
            ->withToken($secretKey)
            ->withHeaders(["Idempotency-Key" => $this->idempotencyKey("customer", [$client->id])])
            ->post("https://api.stripe.com/v1/customers", $fields);

        if (!$response->successful()) {
            $error = $response->json("error.message", "Unknown error");
            Log::error("Stripe: create customer failed", [
                "client" => $client->id,
                "status" => $response->status(),
                "error"  => $error,
            ]);
            return ["success" => false, "message" => "Stripe error: " . $error];
        }

        $customerId = $response->json("id");
        if (!$customerId) {
            return ["success" => false, "message" => "Stripe returned a customer with no id."];
        }

        return [
            "success"     => true,
            "customer_id" => GatewayCustomer::remember("stripe", (int) $client->id, (string) $customerId),
        ];
    }

    /**
     * Charge a card the customer stored earlier, with nobody watching.
     *
     * off_session tells Stripe the cardholder is not here, which changes what
     * the issuer is asked and how it answers: instead of showing an
     * authentication screen the bank either takes the money or refuses with a
     * reason, and one of those reasons is "bring the cardholder back". That
     * third answer is the one worth keeping apart from a decline, which is
     * what the three statuses in the contract are for.
     *
     * The idempotency key makes a job that crashes between the POST and writing
     * down what happened safe to run again without taking the money twice.
     * $params['idempotency_key'] is where it comes from when the caller keeps
     * one — the attempt row writes it before the first request and hands the
     * same string back for every repeat — and idempotencyKey() works one out
     * from the figures when the caller keeps none. Because Stripe expires a key
     * after a day, a genuine retry tomorrow is a genuine new attempt either way.
     *
     * Nothing is asked of Stripe until the row itself has been read. A card the
     * customer removed and a card already known to need their attention are
     * both refused here, because removing a card at this end does not detach it
     * at Stripe — the token on a deleted row will very likely still be accepted
     * — and because issuers read repeated attempts on a card they have already
     * refused as fraud, which drags down acceptance on the cards that would
     * have worked.
     *
     * $params["replay"] IS THE ONE FACT THIS MODULE CANNOT WORK OUT FOR ITSELF,
     * and the whole of what changes when it is true. This class is stateless by
     * design: it sees one HTTP exchange and has no idea whether the identical
     * POST went out fifteen minutes ago under the identical key. The caller
     * does — it is written in the attempt row, it is the reason the caller was
     * allowed to send at all, and InvoiceChargeAttempt::repeatsARequestAlreadySent()
     * is where it is read. True means: you have already sent this, we never
     * heard what became of it, and that is the question you are now answering.
     * outcomeIsIndeterminate() explains what it does with that.
     */
    public function chargeStoredMethod(Invoice $invoice, PaymentMethod $method, float $amount, array $params = []): array
    {
        $isReplay = ($params["replay"] ?? false) === true;

        $secretKey = $this->getSetting("secret_key");
        if (!$secretKey) {
            // Retryable: nothing was asked of the card, so nothing has been
            // learned about it. Once a key is configured this same attempt
            // works, and refusing to try again would strand the invoice.
            return $this->nothingWasSent($isReplay, "Stripe secret key not configured.", true);
        }

        // The card belongs to a client; the invoice belongs to a client. If
        // those are not the same client somebody has passed the wrong row, and
        // the cost of finding out from the cardholder is far higher than the
        // cost of this comparison.
        if ((int) $method->client_id !== (int) $invoice->client_id) {
            Log::warning("Stripe: refused to charge a stored card belonging to another client", [
                "invoice"        => $invoice->id,
                "invoice_client" => $invoice->client_id,
                "method"         => $method->id,
                "method_client"  => $method->client_id,
            ]);
            return $this->nothingWasSent($isReplay, "Stored payment method does not belong to this invoice's client.");
        }

        if ($method->gateway_name !== "stripe") {
            return $this->nothingWasSent($isReplay, "Stored payment method was not stored with Stripe.");
        }

        // A card in the bin is a card the customer has told us to stop using.
        // Removing one does not detach it at Stripe, so the token in this row
        // very probably still works — which is exactly why this has to refuse
        // rather than rely on Stripe to. Not retryable: nothing about tomorrow
        // undeletes it.
        if ($method->trashed()) {
            Log::warning("Stripe: refused to charge a stored card the customer had removed", [
                "invoice" => $invoice->id,
                "method"  => $method->id,
            ]);

            return $this->nothingWasSent($isReplay, "Stored payment method has been removed.");
        }

        // And a card already known to need the customer's attention is not
        // worth asking the issuer about again; that is what the status is for.
        if ($method->status !== PaymentMethod::STATUS_ACTIVE) {
            Log::warning("Stripe: refused to charge a stored card that is not active", [
                "invoice" => $invoice->id,
                "method"  => $method->id,
                "status"  => $method->status,
            ]);

            return $this->nothingWasSent($isReplay, "Stored payment method needs the customer to update it.");
        }

        $paymentMethodId = $method->remote_token;
        $customerId      = $method->gateway_customer_id;

        if (!$paymentMethodId || !$customerId) {
            return $this->nothingWasSent($isReplay, "Stored payment method is missing its Stripe customer or token.");
        }

        $currency     = strtolower($params["currency"] ?? shop_currency_code());
        $minorAmount  = $this->minorUnits($amount, $currency);

        if ($minorAmount <= 0) {
            return $this->nothingWasSent($isReplay, "Nothing left to charge on this invoice.");
        }

        // THE NAME THIS REQUEST GOES OUT UNDER, AND WHO OWNS IT.
        //
        // The caller's, whenever the caller has one. It writes the key down
        // before the first POST and hands the same string back on every repeat
        // of it, which is the only way a repeat can be proved to be a repeat:
        // this module is stateless across calls and can do nothing but work the
        // key out again from what it has in front of it. Working it out again
        // is what used to break — idempotencyKey() hashes the figures under
        // config('app.key'), so an application key rotated between a send and
        // its replay produced a different key, Stripe had no saved result to
        // answer from, and the "replay" charged the card a second time.
        //
        // The derivation stays for callers with no memory of their own: the
        // same figures give the same key, which is still right for a job that
        // is simply run twice. It is only ever a fallback now.
        $suppliedKey = $params["idempotency_key"] ?? null;
        $suppliedKey = is_string($suppliedKey) && trim($suppliedKey) !== "" ? trim($suppliedKey) : null;

        // AND ON A REPLAY THERE IS NO FALLBACK, because a derived key is
        // exactly the thing that cannot be trusted to be the one the first
        // request carried. A caller that says "you have already sent this" and
        // cannot say what it was sent as is asking for a charge nobody can
        // prove is a repeat, and the answer to that is to send nothing: the row
        // stays in flight, the bounded machinery asks a few more times and then
        // fetches a person, and no card is touched on a guess. Unreachable
        // through AutoChargeService, which always carries the row's key, and
        // written anyway for the same reason nothingWasSent() is: a module has
        // to be right about its own answers whoever is asking.
        if ($isReplay && $suppliedKey === null) {
            return $this->nothingWasSent(
                $isReplay,
                "This end cannot name the idempotency key the charge in flight was sent under."
            );
        }

        // Both of these are the same fact in two shapes: what came back, and
        // why nothing did. They are held side by side rather than answered in
        // two separate branches because the question that matters next — was
        // the card charged? — is one question, and it is asked once, below.
        $response          = null;
        $connectionFailure = null;

        try {
            $response = Http::asForm()
                ->withToken($secretKey)
                ->withHeaders(["Idempotency-Key" => $suppliedKey
                    ?? $this->idempotencyKey("offsession", [$invoice->id, $method->id, $minorAmount, $currency])])
                ->post("https://api.stripe.com/v1/payment_intents", [
                    "amount"                 => $minorAmount,
                    "currency"               => $currency,
                    "customer"               => $customerId,
                    "payment_method"         => $paymentMethodId,
                    // A stored card is a card. Naming the type stops Stripe
                    // reaching for a method that needs the customer in front of
                    // a browser, which is the one thing there is not here.
                    "payment_method_types[]" => "card",
                    "off_session"            => "true",
                    "confirm"                => "true",
                    "description"            => "Invoice #" . ($invoice->invoice_num ?? $invoice->id),
                    "metadata[invoice_id]"   => $invoice->id,
                    "metadata[invoice_num]"  => $invoice->invoice_num ?? $invoice->id,
                ]);
        } catch (ConnectionException $e) {
            // Nothing is decided here. A dropped connection is one of the
            // shapes of "we do not know", not a second kind of answer, and the
            // moment it was allowed to decide for itself was the moment the
            // three branches of this method started disagreeing about the same
            // customer's money.
            $connectionFailure = $e->getMessage();
        }

        // =====================================================================
        // THE ONE QUESTION: DO WE KNOW WHETHER THE CARD WAS CHARGED?
        //
        // Asked once, of one predicate, before anything else about this
        // response is read — before the body, before the decline codes, before
        // the card is written to and before a retry is scheduled. Every branch
        // below it may assume the answer is yes.
        //
        // IT USED TO BE THREE DECISIONS IN THREE PLACES AND THEY DID NOT AGREE.
        // A dropped connection returned outcome_unknown here; a 2xx carrying an
        // unfinished intent returned outcome_unknown a few lines down; and an
        // HTTP 500 — the case Stripe documents in as many words as
        // indeterminate — fell through to the refusal branch and was recorded
        // as an ordinary retryable decline. All three are the same fact about
        // the same customer's money, and the odd one out was the one that
        // scheduled a second real debit for it three days later, outside the
        // twenty-four hours Stripe keeps the idempotency key that would have
        // made the repeat a replay rather than a charge.
        //
        // outcomeIsIndeterminate() carries the evidence for which HTTP answers
        // belong in here and which do not.
        // =====================================================================
        if ($this->outcomeIsIndeterminate($response?->status(), $isReplay)) {
            // AT ERROR LEVEL FOR BOTH SHAPES. This is the only outcome this
            // module produces that can leave a customer's money somewhere
            // nothing has written down; it costs an operator one log line in a
            // rare case, and it is the last channel standing when the others
            // are ignored.
            Log::error("Stripe: an off-session charge was sent and no answer came back that says what became of it", [
                "invoice"      => $invoice->id,
                "method"       => $method->id,
                "status"       => $response?->status(),
                "error"        => $connectionFailure ?? $response?->json("error.message"),
                "code"         => $response?->json("error.code"),
                // WHICH QUESTION WAS BEING ASKED. A 429 or a 401 in the status
                // above reads as a plain refusal until you know this was a
                // repeat, at which point it reads as what it is: an answer that
                // never looked at the charge we are asking about. It is the
                // second thing a person opening the parked row needs.
                "replay"       => $isReplay,
                // RECORDED, NEVER OBEYED. outcomeIsIndeterminate() explains why
                // this module does not let Stripe-Should-Retry answer the
                // question above; it is logged because it is the first thing a
                // person opening the parked row will want to see.
                "should_retry" => ($response?->header("Stripe-Should-Retry") ?: null),
            ]);

            // The caller has exactly the machinery for this: the attempt row
            // stays in flight and the fifteen-minute rescue sweep replays it
            // INSIDE the window, where the same key returns the first
            // request's own result — "Subsequent requests with the same key
            // return the same result, including 500 errors"
            // (https://docs.stripe.com/api/idempotent_requests, fetched
            // 2026-09-17) — so we learn what became of it without the card
            // being asked twice. Bounded by InvoiceChargeAttempt's
            // REPLAY_WINDOW_SECONDS, measured from the FIRST send, and by
            // MAX_REPLAYS; when either runs out the row goes to a person
            // rather than to a card.
            return $this->outcomeUnknown($connectionFailure !== null
                ? "Stripe could not be reached: " . $connectionFailure
                : "Stripe answered " . $response->status() . " and did not say whether the charge went through.");
        }

        if ($response->successful()) {
            $intent = $response->json();
            $status = (string) ($intent["status"] ?? "");

            if ($status === "succeeded") {
                return [
                    "success"        => true,
                    "status"         => "succeeded",
                    "transaction_id" => $intent["id"] ?? null,
                    // Read back through the same conversion it was sent
                    // through, so that what is reported is what was taken
                    // whatever the shop sells in.
                    "amount"         => $this->majorUnits((int) ($intent["amount_received"] ?? $minorAmount), $currency),
                ];
            }

            if ($status === "requires_action") {
                return [
                    "success"        => false,
                    "status"         => "requires_action",
                    "message"        => "The cardholder's bank wants them to authenticate this payment.",
                    "transaction_id" => $intent["id"] ?? null,
                    "decline_code"   => "authentication_required",
                    // Nothing an unattended retry can do: the same intent comes
                    // back asking for the same person.
                    "retryable"      => false,
                ];
            }

            // Anything else — 'processing' above all — means Stripe has the
            // request but has not said the money is there. Not a success: the
            // invoice must not be credited on a maybe. And not a failure
            // either, which is what it used to be reported as: a retryable
            // failure is scheduled for the day after next, by which time the
            // idempotency key has been pruned and the retry is a brand new
            // charge for a payment that was very likely completing while we
            // called it failed.
            //
            // The intent id is deliberately NOT returned. On an in-flight row
            // last_transaction_id means one thing only — "the gateway has
            // answered and the ledger owes an entry" — and AutoChargeService's
            // rescue sweep credits such a row from our own records without
            // asking anybody. Handing it the id of a payment that is merely
            // processing would credit an invoice against money that may never
            // arrive. What resolves this is the replay inside the window, or
            // the payment_intent webhook, or a person.
            Log::info("Stripe: off-session charge is not finished", [
                "invoice" => $invoice->id,
                "intent"  => $intent["id"] ?? null,
                "status"  => $status,
            ]);

            return $this->outcomeUnknown("Stripe returned an unfinished payment (status: " . ($status ?: "unknown") . ").");
        }

        $error       = (array) $response->json("error", []);
        $code        = $error["code"] ?? null;
        $declineCode = $error["decline_code"] ?? null;
        $message     = $error["message"] ?? "Unknown error";

        Log::warning("Stripe: off-session charge refused", [
            "invoice"      => $invoice->id,
            "method"       => $method->id,
            "status"       => $response->status(),
            "code"         => $code,
            "decline_code" => $declineCode,
        ]);

        // The bank will take the money, but only once the cardholder has said
        // so. Documented behaviour for an off-session charge is a 402 carrying
        // authentication_required, with the intent to bring them back to
        // travelling inside the error itself.
        // https://docs.stripe.com/declines/codes (authentication_required)
        //
        // The 402 is kept as the shape this arrives in, but it is no longer the
        // only thing standing between an authentication and a decline: the
        // codes it tests for are the same ones the retry list reads, so a
        // refusal that slips past this branch is still refused a retry rather
        // than being handed to a dunning loop that can never satisfy it.
        if ($response->status() === self::CARD_DECLINED && $this->cardholderMustAuthenticate($error)) {
            return [
                "success"        => false,
                "status"         => "requires_action",
                "message"        => $message,
                "transaction_id" => $error["payment_intent"]["id"] ?? null,
                "decline_code"   => $declineCode ?: $code,
                "retryable"      => false,
            ];
        }

        // A refusal the customer has to act on is written on the card, because
        // that is what the column is for and because charging it again
        // tomorrow only teaches the issuer to distrust us. An authentication is
        // pointedly not one of those: that card works perfectly well with its
        // owner in front of it, and telling them to replace it would be wrong.
        if ($this->refusalEndsTheCard($error)) {
            $method->update(["status" => PaymentMethod::STATUS_REQUIRES_UPDATE]);

            Log::info("Stripe: stored card marked as needing the customer's attention", [
                "method"       => $method->id,
                "decline_code" => $declineCode ?: $code,
            ]);
        }

        return $this->chargeFailed(
            "Stripe error: " . $message,
            $declineCode ?: $code,
            $this->declineIsRetryable($error, $response->status(), $isReplay)
        );
    }

    /**
     * The contract's failed branch, written in one place.
     *
     * Every caller gets the same keys whether the card was declined, the
     * gateway was unreachable or the row it was handed made no sense — a
     * caller that has to remember which failures carry a decline_code will
     * eventually forget.
     */
    private function chargeFailed(string $message, ?string $declineCode = null, bool $retryable = false): array
    {
        return [
            "success"      => false,
            "status"       => "failed",
            "message"      => $message,
            "decline_code" => $declineCode,
            "retryable"    => $retryable,
        ];
    }

    /**
     * The charge was sent and what became of it is not known.
     *
     * Kept apart from chargeFailed() because the two mean opposite things about
     * the customer's money: a failure says nothing was taken, and this says
     * something may have been. retryable is false so that a caller which has
     * never heard of outcome_unknown cannot schedule a fresh charge on the
     * strength of it — the safe reading for an old caller is "do not try
     * again", not "try again tomorrow".
     *
     * WHICH ANSWERS ARRIVE HERE IS outcomeIsIndeterminate()'S QUESTION AND
     * NOTHING ELSE'S. Three doors lead in: that predicate, asked once of the
     * HTTP exchange (no answer, 5xx, 424, and on a replay everything that is
     * not the gateway's own word on the charge); the unfinished-intent branch,
     * where a 2xx says the gateway has the payment but not that the money is
     * there; and nothingWasSent() on a replay, where this end refused before
     * the POST and so learned nothing at all. The second is a different kind of
     * evidence rather than a second opinion — it reads the body, not the status
     * — and it is deliberately not folded into the predicate, which would then
     * be answering two questions with one name.
     */
    private function outcomeUnknown(string $message): array
    {
        return [
            "success"         => false,
            "status"          => "failed",
            "outcome_unknown" => true,
            "message"         => $message,
            "decline_code"    => null,
            "retryable"       => false,
        ];
    }

    /**
     * This end refused before anything went out — and on a replay that is not
     * an answer about the charge that DID go out.
     *
     * Every refusal above the POST is a fact about the state of things right
     * now: no secret key, a card the customer has since removed, a card the
     * issuer has already finished with. On a first send that is the whole
     * truth, and chargeFailed() is the honest report of it: nothing was taken,
     * because nothing was sent.
     *
     * ON A REPLAY THE SAME SENTENCE IS TRUE AND IRRELEVANT. A replay only
     * happens because a charge WAS sent, minutes ago, and nobody heard what
     * became of it; that is the open question, and "we declined to send
     * anything this time" says nothing whatever about it. Reported as a failure
     * it closes the question with an answer to a different one — and the
     * closing is not harmless either way round. A refusal carrying
     * retryable => true (a missing secret key is one) schedules a fresh charge
     * three days out, against a key Stripe pruned two days earlier, which is a
     * second real debit; a refusal carrying retryable => false writes the row
     * off as exhausted, where none of the operator's channels fires and the
     * first charge is never looked at by anybody.
     *
     * So on a replay these all become outcomeUnknown(), and the row stays in
     * flight for the bounded machinery that already owns unknown outcomes: a
     * few more replays, then the deadline or the cap, then a person.
     *
     * The caller's own guards make this belt rather than braces — AutoCharge
     * resolves a chargeable card before it claims anything, so most of these
     * cannot be reached on a replay through it. It is written anyway, because
     * a module must be right about what its own answers mean whoever is asking.
     */
    private function nothingWasSent(bool $isReplay, string $message, bool $retryable = false): array
    {
        if ($isReplay) {
            return $this->outcomeUnknown(
                $message . " Nothing was sent this time, so what became of the charge already sent is still unknown."
            );
        }

        return $this->chargeFailed($message, null, $retryable);
    }

    /**
     * DOES THIS ANSWER LEAVE IT UNKNOWN WHETHER THE CARD WAS CHARGED?
     *
     * AND IT IS NOT THE SAME QUESTION ON A FIRST SEND AS ON A REPLAY, which is
     * the distinction $isReplay carries in and the one this predicate was
     * missing. On a first send the question is "did THIS request take money?".
     * On a replay the money at stake was put at stake by a request that went
     * out minutes ago and was never heard about, so the question is "does this
     * answer tell me what became of THAT one?" — and most answers do not, while
     * reading exactly like answers that do.
     *
     * The single place that question is decided in this module, and the reason
     * it is a named predicate rather than a condition inside whichever branch
     * happened to need it: "we do not know" is one concept, every path has to
     * mean the same thing by it, and every path that answers yes has to end up
     * in the same machinery. Answering yes routes the charge to
     * outcomeUnknown(), which the caller holds in flight, replays inside the
     * gateway's idempotency window a bounded number of times, and finally hands
     * to a person. Answering no says the card's fate is known and licenses
     * everything the refusal branch does with it: marking the card, telling the
     * customer their payment failed, and scheduling a fresh charge days later.
     *
     * NULL IS NO ANSWER AT ALL — a dropped connection, a read timeout. The
     * request may have reached Stripe and Stripe may have reached the payment
     * network; nothing this end can see which. It is passed in as null rather
     * than handled in its own branch so that the transport case and the HTTP
     * case cannot drift apart again.
     *
     * 5xx IS INDETERMINATE, AND STRIPE SAYS SO WITHOUT QUALIFICATION. "You
     * should treat the result of a `500` request as indeterminate." — "Treat
     * requests that return `500` errors as indeterminate." — "if creating a
     * charge returns a `500` error but we detect that the information has gone
     * out to a payment network, we'll try to roll it forward. If not, we'll try
     * to roll it back. If this doesn't resolve the issue, you may still see
     * requests with a `500` error that produce user-visible side effects."
     * (https://docs.stripe.com/error-low-level, fetched 2026-09-17.) The table
     * on that same page groups 500, 502, 503 and 504 together as "Server
     * Errors"; the ones that are not 500 are, if anything, less determinate
     * still, because a 502 or a 504 is typically written by something in front
     * of the API that has no idea what the API did. So the whole range is in.
     *
     * 424 IS INDETERMINATE TOO, and this is a judgement rather than a quotation
     * so it is worth the paragraph. Stripe documents it as "External Dependency
     * Failed: The request couldn't be completed due to a failure in a
     * dependency external to Stripe" and says nothing else about it. Three
     * things decide it. First, for a card charge the external dependency IS the
     * payment network, which is precisely the place Stripe's own 500 wording
     * says a charge may have reached before the failure. Second, "couldn't be
     * completed" describes the request, not the money: nowhere does the
     * documentation say the operation did not execute — contrast the sentence
     * quoted under 429 below, which says exactly that and is why 429 is treated
     * as the opposite. Third, Stripe's rule for 4xx is that "as long as an API
     * method began execution, Stripe's API servers will cache the results of
     * the request regardless of what they were", and a dependency failure is by
     * definition something that happened after execution began — so a 424 is
     * cached like any other answer, and a repeat sent after the key is pruned
     * is a brand-new charge in exactly the way a repeated 500 is. With no
     * evidence that the card was not charged, the only honest answer is that we
     * do not know.
     *
     * 429 ON A FIRST SEND IS NOT INDETERMINATE, and it deliberately keeps its
     * ordinary retryable-decline treatment. "a request that's rate limited with
     * a `429` can produce a different result with the same idempotency key
     * because rate limiters run before the API's idempotency layer" (same
     * page). Running before the idempotency layer means running before the API
     * method: the request never executed, no card was touched, and nothing was
     * cached — so a fresh request days later is a first charge, which is what
     * the retry schedule is for. Treating it as indeterminate everywhere would
     * park a perfectly ordinary "come back later" for a human, and turn a
     * throttled morning into a queue of invoices nobody may collect.
     *
     * 429 ON A REPLAY IS THE SAME SENTENCE READ THE OTHER WAY ROUND, and this
     * is the whole of what $isReplay changes. "Rate limiters run before the
     * API's idempotency layer" means the rate limiter answered WITHOUT the
     * saved record of the first request ever being looked at. On a first send
     * that is reassuring — nothing ran. On a replay it is the opposite: this
     * answer is silent about the one thing we are asking, and the first
     * request's own outcome is exactly as unknown after it as before it.
     * Believed as an ordinary retryable decline it scheduled a fresh charge
     * three days out, forty-eight hours after Stripe pruned the key, on top of
     * a charge the 500 above forbids us to assume failed. Measured on the real
     * crontab: two real requests at Stripe, seventy-two hours apart, one
     * idempotency key, and an invoice the panel reports as paid once.
     *
     * 4xx OTHERWISE, ON A FIRST SEND, IS NOT INDETERMINATE. A 402 is the
     * issuer's answer, a 400 is our own request being wrong, a 401 never
     * reached the account, and a 409 is the idempotency layer refusing THIS
     * request outright — in every one of them the API has told us what happened
     * to this charge, which on a first send is the only charge there is.
     *
     * ON A REPLAY THE LIST IS INVERTED, AND DELIBERATELY SO. Asking which
     * statuses are produced before the idempotency layer means keeping a
     * running list of Stripe's pre-execution failures and being wrong the day
     * they add one — the mistake that put the 5xx test in declineIsRetryable()
     * and left 429 out of this one. The question is asked from the other end
     * instead: which answers can ONLY have come from the record of the original
     * request? Two. A 2xx, which is the saved PaymentIntent (or, inside the
     * window with the key still held, a fresh one that is the same thing), and
     * a 402, which is the issuer's verdict on this charge — Stripe "saves the
     * resulting status code and body of the first request made for any given
     * idempotency key, regardless of whether it succeeds or fails", so a 402
     * arriving on a replay is the first request's own decline being read back.
     * Everything else — 429, 401, 403, 404, 409, 400, one Stripe has not
     * invented yet — is treated as silence about the original, and silence is
     * what the in-flight machinery is for. It costs a replay, and past the cap
     * or the deadline it costs a person's attention; it cannot cost a second
     * debit.
     *
     * AND THIS MODULE DOES NOT READ Stripe-Should-Retry, which is a deliberate
     * refusal rather than an oversight. Stripe documents it as "`true`
     * indicates that a client should retry the request", "`false` means that a
     * client should *not* retry the request because it won't have an additional
     * effect", and absent means "the API can't determine whether or not it can
     * retry the request. Clients should fall back to other properties of the
     * response (like the status code)". Three reasons not to consult it here:
     *
     *  - IT ANSWERS A DIFFERENT QUESTION. Every value of it is about whether to
     *    send this request AGAIN; none of them is about whether the FIRST one
     *    reached a payment network. Stripe's indeterminacy sentence about 500s
     *    is not qualified by the header anywhere on the page.
     *  - IT IS ON A DIFFERENT CLOCK. The header is written for a client library
     *    repeating the request in milliseconds with the same key, inside the
     *    retention window. "Retryable" in the engine that reads this module
     *    means "raise a fresh charge three days from now, against a key Stripe
     *    will have pruned". Letting a header that means "try again in 200ms"
     *    authorise that is the very confusion this whole class of defect is
     *    made of.
     *  - THE THING A `true` WOULD BUY IS ALREADY BUILT. An immediate repeat
     *    with the same key is what the rescue sweep does — bounded, recorded,
     *    and with a person at the end of it. A second retry mechanism inside
     *    this method would be another place deciding the same thing, which is
     *    what this predicate exists to stop.
     *
     * The fallback the header's absence prescribes — judge by the status code —
     * is therefore what this module does in every case. The value is logged
     * where an indeterminate answer is raised, so an operator investigating one
     * can see what Stripe thought, without it ever deciding anything.
     */
    private function outcomeIsIndeterminate(?int $status, bool $isReplay = false): bool
    {
        // No answer arrived, so there is nothing to read and nothing to know.
        if ($status === null) {
            return true;
        }

        if ($status >= 500 || $status === self::EXTERNAL_DEPENDENCY_FAILED) {
            return true;
        }

        // A first send: the answer is about the only request there is.
        if (!$isReplay) {
            return false;
        }

        return !$this->answersForTheOriginalRequest($status);
    }

    /**
     * Could this answer only have come from the gateway's record of the request
     * we are asking about?
     *
     * An allow-list, and small on purpose. Its job is to be wrong in the
     * direction of a person rather than in the direction of a card: a status
     * left out of it that really was the saved result costs one review, and a
     * status wrongly let in costs a customer a second debit.
     *
     * 2xx is the PaymentIntent — succeeded, requires_action, or still
     * processing, which the branch below the predicate turns back into an
     * unknown outcome on its own evidence. 402 is the issuer's verdict, and
     * Stripe only sets a decline_code "for card errors resulting from a card
     * issuer decline" (https://docs.stripe.com/api/errors), so it is the one
     * refusal that cannot have been written before the charge was attempted.
     *
     * Both are reachable on a replay only because the caller proved the key is
     * still held before it sent — InvoiceChargeAttempt::REPLAY_WINDOW_SECONDS —
     * so an answer arriving here has either been served out of the saved record
     * or been produced by the one request that record belongs to.
     */
    private function answersForTheOriginalRequest(int $status): bool
    {
        return ($status >= 200 && $status < 300) || $status === self::CARD_DECLINED;
    }

    /**
     * "External Dependency Failed", the one 4xx that does not tell us what
     * became of the charge. Named rather than written as a bare 424 at the
     * point of use, because a bare number in a condition is how the 5xx test
     * came to be sitting in declineIsRetryable() answering the wrong question.
     * https://docs.stripe.com/error-low-level (HTTP status code reference)
     */
    private const EXTERNAL_DEPENDENCY_FAILED = 424;

    /** Too many requests: refused before the API method ran, so nothing happened. */
    private const RATE_LIMITED = 429;

    /**
     * The issuer answered. Named for the same reason 424 is: it is the one
     * refusal that can only have been written after the charge was put to a
     * card, which is what makes it safe to resolve a replay with.
     * https://docs.stripe.com/error-low-level (HTTP status code reference)
     */
    private const CARD_DECLINED = 402;

    /**
     * Declines that will not change their mind.
     *
     * Retrying one of these collects nothing and costs something: card
     * networks cap how often a single charge may be reattempted, and issuers
     * read repeated attempts on a dead card as fraud, which drags down
     * acceptance on the cards that would otherwise have worked.
     *
     * Taken from the decline-code table at https://docs.stripe.com/declines/codes,
     * grouped by the reason Stripe gives. Every code here has a documented
     * next step that only the customer can carry out — use another card,
     * correct card data this end does not hold, or talk to their issuer.
     *
     * What is deliberately absent matters as much. Codes whose documented next
     * step is "attempt the payment again" (processing_error,
     * issuer_not_available, reenter_transaction, approve_with_id) stay
     * retryable, as do the limit-based ones that come right on their own
     * (insufficient_funds, card_velocity_exceeded,
     * withdrawal_count_limit_exceeded) and the vague ones that are most often
     * temporary (generic_decline, do_not_honor, call_issuer, no_action_taken).
     */
    /**
     * The issuer wants the cardholder, not another attempt.
     *
     * Both are documented decline codes: authentication_required is "the card
     * was declined because the transaction requires authentication such as 3D
     * Secure ... In some cases, such as off-session payments, you might need to
     * request the customer to retry", and
     * mobile_device_authentication_required is "the card was declined because
     * the transaction requires authentication. Retry attempts by tapping your
     * mobile device again." https://docs.stripe.com/declines/codes
     *
     * Kept apart from the rest of the hard list, and folded into it below,
     * because the same fact is needed twice and must not be written twice: the
     * charge path reads this to answer requires_action, and the retry list
     * reads it to refuse another unattended attempt. Written out separately,
     * the two drifted — a refusal the charge called final came back from the
     * failure webhook as worth retrying tomorrow.
     */
    private const CARDHOLDER_AUTHENTICATION_CODES = [
        "authentication_required",
        "mobile_device_authentication_required",
    ];

    private const HARD_DECLINE_CODES = [
        // The card is gone, or the issuer has withdrawn it.
        "lost_card", "stolen_card", "lost_or_stolen_card", "pickup_card", "restricted_card",
        "revocation_of_authorization", "revocation_of_all_authorizations", "stop_payment_order",
        "invalid_account", "new_account_information_available", "pin_try_exceeded",

        // The details are wrong, and an unattended retry sends the same wrong
        // details again: there is nobody here to correct them.
        "incorrect_number", "invalid_number", "expired_card",
        "invalid_expiry_month", "invalid_expiry_year",
        "incorrect_cvc", "invalid_cvc", "incorrect_zip", "incorrect_address",
        "incorrect_pin", "invalid_pin", "offline_pin_required", "online_or_offline_pin_required",

        // This card cannot buy this, here, in this currency, at this amount.
        "card_not_supported", "currency_not_supported", "not_permitted",
        "transaction_not_allowed", "service_not_allowed", "invalid_amount",

        // Stopped deliberately, by Stripe or by the issuer.
        "fraudulent", "merchant_blacklist", "security_violation", "testmode_decline",
        "do_not_try_again",

        // The cardholder has to authenticate, which they cannot do while
        // absent. Charging again unattended repeats the same refusal.
        "authentication_not_handled",
        ...self::CARDHOLDER_AUTHENTICATION_CODES,

        // Already taken: retrying is how a customer gets charged twice.
        "duplicate_transaction",
    ];

    /**
     * Invalid requests worth sending again exactly as they were.
     *
     * Being asked something invalid is not normally worth repeating, but three
     * of Stripe's error codes describe a moment rather than a mistake, and each
     * one's documented next step is to try again: lock_timeout ("If you see this
     * error intermittently, retry the request"), idempotency_key_in_use ("the
     * idempotency key provided is currently being used in another request") and
     * rate_limit ("we recommend an exponential backoff of your requests").
     * https://docs.stripe.com/error-codes
     */
    private const RETRYABLE_REQUEST_CODES = ["lock_timeout", "idempotency_key_in_use", "rate_limit"];

    /**
     * Is this refusal the bank asking for the cardholder rather than saying no?
     */
    private function cardholderMustAuthenticate(array $error): bool
    {
        foreach ([$error["decline_code"] ?? null, $error["code"] ?? null] as $reason) {
            if ($reason !== null && in_array($reason, self::CARDHOLDER_AUTHENTICATION_CODES, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Is this card finished, as far as unattended charging is concerned?
     *
     * A narrower question than whether the attempt is worth repeating, and
     * asked of different evidence on purpose. declineIsRetryable() answers
     * "again, now?", and an issuer's advice_code can overrule it because that
     * advice is about this transaction — do_not_try_again is documented as "you
     * shouldn't use it again for the same transaction"
     * (https://docs.stripe.com/declines/card), which says nothing about the card
     * itself. This one answers "ever, unattended?", and only the decline code
     * carries that: a lost card is lost whatever the issuer advises about
     * today's attempt.
     *
     * Only an issuer decline counts. An invalid_request_error is this end
     * getting something wrong — a detached token, an amount below Stripe's
     * minimum — and marking the customer's card as needing their attention for
     * our own mistake would send them to their bank over nothing. Neither does
     * an authentication request: that card works perfectly well with its owner
     * in front of it.
     */
    private function refusalEndsTheCard(array $error): bool
    {
        $declineCode = $error["decline_code"] ?? null;

        if ($declineCode === null || $this->cardholderMustAuthenticate($error)) {
            return false;
        }

        return in_array($declineCode, self::HARD_DECLINE_CODES, true);
    }

    /**
     * Is another unattended attempt at this card worth making?
     *
     * Stripe's own advice comes first where it exists. An advice_code of
     * do_not_try_again is the issuer saying so in as many words, and it is
     * more current than any list kept in this file. The documented vocabulary
     * is exactly three words — do_not_try_again, try_again_later and
     * confirm_card_data, the last of them meaning the card details we hold are
     * wrong, which an unattended retry would simply send again.
     * https://docs.stripe.com/declines/card (Declined card retries)
     *
     * network_advice_code is deliberately not consulted alongside it. Stripe
     * documents that one as "a 2 digit code" (https://docs.stripe.com/api/errors),
     * so reading it here could only ever have matched nothing.
     *
     * After that it is the error's TYPE that decides, and only then its code.
     * Reading the code first was the bug worth naming: every Stripe error of
     * any kind carries one, so a test meant for card declines swallowed the
     * lot, and being told there is no such payment method came back as worth
     * trying again tomorrow — forever, on a card that had been detached.
     *
     * A decline code is treated as a card decline whatever the type says,
     * because Stripe only ever sets one "for card errors resulting from a card
     * issuer decline" (https://docs.stripe.com/api/errors) — a refusal that
     * carries one is an issuer's answer by definition.
     *
     * The rest divides the way the documentation does: being asked to slow down
     * is worth repeating, an invalid request is not unless it is one of the
     * three that describe a moment, and an idempotency error means the same key
     * was sent with different parameters, which will be just as true tomorrow.
     *
     * WHAT IS NO LONGER HERE IS THE 5xx. "A bad minute at Stripe is worth
     * repeating" reads as common sense and was the whole defect: repeating it
     * meant a fresh charge three days later, by which time Stripe has pruned
     * the idempotency key that would have made the repeat a replay, for a
     * charge Stripe's own documentation forbids us to assume failed. That
     * question belongs to outcomeIsIndeterminate() and is settled before this
     * method is reached.
     */
    private function declineIsRetryable(array $error, ?int $status = null, bool $isReplay = false): bool
    {
        // NOBODY MAY ASK THIS ABOUT AN OUTCOME THAT IS NOT KNOWN, and asking it
        // anyway is the defect this guard closes. "Is another attempt worth
        // making?" presupposes that this attempt is over and took no money;
        // where that is not established the question has no true answer, and
        // the answer it used to be given — yes, on the strength of a 5xx —
        // scheduled a fresh charge three days out for a charge that may already
        // have gone through.
        //
        // The charge path settles indeterminacy before it gets here, so this is
        // belt rather than braces. It is written anyway because this method has
        // a second caller and will acquire more, and because false is the safe
        // reading for one that forgets: it stops the dunning cycle rather than
        // authorising a debit.
        //
        // $status === null is excluded on purpose. It means "no HTTP answer at
        // all" on the charge path — where outcomeIsIndeterminate() has already
        // caught it — but webhookPaymentFailed() passes no status because there
        // was no request: the event IS Stripe telling us the payment failed,
        // which is as determinate as an answer gets. Reading its null as an
        // unknown outcome would mark every failed-payment webhook unretryable.
        //
        // $isReplay defaults to false for the same caller. A webhook is not a
        // presentation of anything, so there is no earlier request of ours for
        // its verdict to be silent about; false is both the true value and the
        // one that leaves that path untouched.
        if ($status !== null && $this->outcomeIsIndeterminate($status, $isReplay)) {
            return false;
        }

        $advice = $error["advice_code"] ?? null;

        if ($advice === "do_not_try_again" || $advice === "confirm_card_data") {
            return false;
        }

        if ($advice === "try_again_later") {
            return true;
        }

        // Asked to slow down, on a request that is the first of its kind. The
        // card was never reached: "a request that's rate limited with a `429`
        // can produce a different result with the same idempotency key because
        // rate limiters run before the API's idempotency layer"
        // (https://docs.stripe.com/error-low-level, fetched 2026-09-17), and
        // running before the idempotency layer means running before the API
        // method. Nothing executed, nothing was cached, and the next attempt is
        // a first attempt — so this is an ordinary retry and not one of
        // outcomeIsIndeterminate()'s cases, which is why the 5xx test that used
        // to share this line is no longer on it.
        //
        // A REPLAY NEVER REACHES THIS LINE, and the same sentence is why. An
        // answer the rate limiter wrote is an answer the record of the original
        // request was never consulted for, so on a replay it is silence rather
        // than a verdict; the guard above has already sent it to
        // outcomeUnknown(). Written as a first-send rule and left here because
        // that is what it is, rather than deleted and rediscovered later.
        if ($status === self::RATE_LIMITED) {
            return true;
        }

        $type        = $error["type"] ?? null;
        $declineCode = $error["decline_code"] ?? null;

        if ($type === "card_error" || $declineCode !== null) {
            return !in_array($declineCode ?? ($error["code"] ?? null), self::HARD_DECLINE_CODES, true);
        }

        return match ($type) {
            "api_error"             => true,
            "invalid_request_error" => in_array($error["code"] ?? null, self::RETRYABLE_REQUEST_CODES, true),
            "idempotency_error"     => false,
            // A refusal that named no type at all, from a status that says the
            // charge is over. The only reading left is the webhook's: no HTTP
            // exchange, nothing learned about the card, worth another go. The
            // "or the status was 5xx" half this arm used to carry is gone — a
            // 5xx never reaches this method now, and leaving the claim in place
            // would be a second opinion about indeterminacy sitting in the one
            // file that must only have one.
            default                 => $status === null,
        };
    }

    public function processWebhook(array $data): array
    {
        $webhookSecret = $this->getSetting("webhook_secret");
        $payload       = $data["_raw_payload"] ?? "";
        $sigHeader     = $data["_signature_header"] ?? "";

        // r170-unsigned: prove who sent this before acting on it.
        //
        // The check used to run only when a secret, a signature header and a raw
        // body all happened to be present. With any of them missing it fell
        // through to the ordinary processing, so an unsigned POST to the public
        // webhook URL naming an invoice id in its metadata marked that invoice
        // paid - and payment then does everything payment does. The
        // Authorize.net module already refuses in exactly this case.
        if (!$webhookSecret || !$sigHeader || !$payload) {
            Log::warning("Stripe: webhook refused - no signature to check against");
            return ["success" => false, "message" => "Unsigned webhook."];
        }

        // Verify Stripe webhook signature
        {
            $parts    = [];
            $elements = explode(",", $sigHeader);
            foreach ($elements as $element) {
                $kv = explode("=", $element, 2);
                if (count($kv) === 2) {
                    $parts[$kv[0]] = $kv[1];
                }
            }

            $timestamp    = $parts["t"] ?? null;
            $sigReceived  = $parts["v1"] ?? null;
            $signedPayload = $timestamp . "." . $payload;
            $sigExpected  = hash_hmac("sha256", $signedPayload, $webhookSecret);

            if (!$timestamp || !$sigReceived || !hash_equals($sigExpected, $sigReceived)) {
                Log::warning("Stripe: webhook signature verification failed");
                return ["success" => false, "message" => "Invalid webhook signature."];
            }

            // The timestamp is signed too, so a caller cannot alter it — but a
            // signature stays valid forever unless we refuse stale ones.
            // Stripe's own libraries allow five minutes.
            if (abs(time() - (int) $timestamp) > self::WEBHOOK_TOLERANCE_SECONDS) {
                Log::warning("Stripe: webhook timestamp outside tolerance", ["timestamp" => $timestamp]);
                return ["success" => false, "message" => "Webhook timestamp is too old."];
            }
        }

        $eventType = $data["type"] ?? "";

        // Anything this module does not act on gets the answer it always got,
        // and leaves no trace behind: recording an event that is going to be
        // ignored anyway only fills a table nobody will read.
        if (!in_array($eventType, self::HANDLED_WEBHOOK_EVENTS, true)) {
            return ["success" => true, "message" => "Event ignored: " . $eventType];
        }

        $object  = $data["data"]["object"] ?? [];
        $eventId = (string) ($data["id"] ?? "");

        // Stripe delivers until it gets a 2xx and redelivers an event that was
        // handled but answered too slowly, so the same payment can arrive more
        // than once. A delivery takes the event before doing the work and lets
        // go of it once the work is finished; a repeat does nothing, which is
        // what stops a second delivery being treated as a second payment.
        //
        // What a repeat is told depends on which repeat it is, and getting that
        // wrong is worth a payment. An event that is finished is over, and
        // saying so ends the redeliveries. An event another delivery is holding
        // is not finished and may never be: the holder may be the request that
        // died, in which case the delivery being answered here is the rescue
        // Stripe is sending precisely because it never got its 2xx. Telling
        // that one the work was done is the last anybody hears of the payment.
        // So it is asked to come back instead, and it finds the lease cold when
        // it does. GatewayEvent::claim() holds the rules and the lease.
        //
        // Only when the event names itself. An event id is what makes two
        // deliveries recognisable as one event, and with none there is nothing
        // to compare — so a caller that sends no id is left exactly as it was.
        //
        // The object is given as a second way to recognise a repeat, but only
        // for the events that can happen to it once and once only. A payment
        // intent can fail on Monday and fail again on Tuesday, and a card can
        // be reissued twice in its life; recording the object against those
        // would make the second real event look like a redelivery of the
        // first and quietly drop it.
        $objectId = in_array($eventType, self::SINGLE_OCCURRENCE_EVENTS, true)
            ? ($object["id"] ?? null)
            : null;

        if ($eventId !== "") {
            $claim = GatewayEvent::claim("stripe", $eventId, $eventType, $objectId);

            if (!$claim->mayProceed()) {
                return $this->answerToARepeat($claim, $eventId, $eventType);
            }
        }

        $result = match ($eventType) {
            "payment_intent.succeeded"             => $this->webhookPaymentSucceeded($object),
            "payment_intent.payment_failed"        => $this->webhookPaymentFailed($object),
            "setup_intent.succeeded"               => $this->webhookSetupSucceeded($object),
            "payment_method.automatically_updated" => $this->webhookCardUpdated($object),
            default                                => ["success" => true, "message" => "Event ignored: " . $eventType],
        };

        if ($eventId !== "" && $this->eventIsFinished($result)) {
            GatewayEvent::markProcessed("stripe", $eventId, $eventType, $objectId);
        }

        return $result;
    }

    /**
     * What to say to a delivery of an event this one may not act on.
     *
     * Finished means the work behind the event is done and will not be done
     * again, so the delivery is acknowledged and Stripe stops sending it. Held
     * means another delivery has the event and has not finished with it, which
     * is a different thing entirely: nothing has been settled, and the holder
     * may be a request that died on its way to the ledger. The delivery being
     * answered here is then the rescue — Stripe sends it because it never got
     * its 2xx — and acknowledging it would end the retries with the payment
     * recorded nowhere.
     *
     * So it is asked to be sent again. The lease is five minutes and Stripe
     * knocks for three days, so the retry after this one either finds the event
     * finished (200, and it stops) or finds the lease cold and does the work
     * itself. Repeating it costs nothing even when the holder did finish:
     * everything behind here refuses work it has already done — a payment by
     * its transaction id, a stored card by its token.
     *
     * The key is read by the webhook controller and by nothing else. No other
     * gateway module sets it, so no other gateway's answers change.
     *
     * @return array<string, mixed>
     */
    private function answerToARepeat(GatewayEventClaim $claim, string $eventId, string $eventType): array
    {
        if ($claim === GatewayEventClaim::Finished) {
            Log::info("Stripe webhook: event already handled", ["event" => $eventId, "type" => $eventType]);

            return ["success" => true, "message" => "Event already handled: " . $eventId];
        }

        Log::info("Stripe webhook: event is in hand elsewhere, asking for a redelivery", [
            "event" => $eventId,
            "type"  => $eventType,
        ]);

        return [
            "success"        => false,
            "retry_delivery" => true,
            "message"        => "Event is being handled by another delivery: " . $eventId,
        ];
    }

    /**
     * Is this event finished, or has it only reached the end of this module?
     *
     * The distinction is the whole reason the claim is a lease. The webhook
     * controller records a payment the moment it sees an invoice_id and a
     * transaction_id in the same array, and it does that after this method has
     * returned and outside the try/catch that guards it
     * (GatewayWebhookController::handle). So an event carrying those two keys
     * is not finished when this module lets go of it — it is finished when the
     * invoice has been credited, which is something that happens out of sight
     * from here and can still fail on a deadlock or a lost connection.
     *
     * Stamping it anyway is how a crashed request became a lost payment:
     * Stripe's retry found the event marked done and did nothing. Leaving it
     * unstamped costs nothing, because the crediting behind it refuses a
     * transaction id it has already recorded (PaymentService::applyPayment), so
     * a redelivery that does reach it a second time changes nothing.
     *
     * A handler that refused is not finished either. It is left claimed and
     * unstamped so that a redelivery can try again, which is what a transient
     * fault deserves.
     */
    private function eventIsFinished(array $result): bool
    {
        if (($result["success"] ?? false) !== true) {
            return false;
        }

        return !isset($result["invoice_id"], $result["transaction_id"]);
    }

    /** The events this module acts on. Everything else is ignored, as it always was. */
    private const HANDLED_WEBHOOK_EVENTS = [
        "payment_intent.succeeded",
        "payment_intent.payment_failed",
        "setup_intent.succeeded",
        "payment_method.automatically_updated",
    ];

    /**
     * Events that can only ever happen once to the object they name.
     *
     * An intent succeeds once and a setup intent is confirmed once, so two of
     * these naming the same object are the same event however many event ids
     * Stripe put on them — worth catching, because that is the redelivery an
     * event id alone would miss. Nothing else on the handled list has that
     * property, and pretending otherwise would throw away real events.
     */
    private const SINGLE_OCCURRENCE_EVENTS = [
        "payment_intent.succeeded",
        "setup_intent.succeeded",
    ];

    /**
     * Money arrived.
     *
     * Unchanged from the day this module was written: the webhook controller
     * credits the invoice when it sees an invoice_id and a transaction_id
     * together, and PaymentService refuses the same transaction twice.
     */
    private function webhookPaymentSucceeded(array $intentObj): array
    {
        $intentId    = $intentObj["id"] ?? null;
        $invoiceId   = $intentObj["metadata"]["invoice_id"] ?? null;
        $amountCents = $intentObj["amount_received"] ?? 0;

        Log::info("Stripe webhook: payment_intent.succeeded", [
            "intent_id"  => $intentId,
            "invoice_id" => $invoiceId,
            "amount"     => $amountCents / 100,
        ]);

        return [
            "success"        => true,
            "transaction_id" => $intentId,
            "invoice_id"     => $invoiceId,
            "amount"         => $amountCents / 100,
            "gateway"        => "stripe",
        ];
    }

    /**
     * A charge that did not go through.
     *
     * The intent id deliberately does not travel home as transaction_id. The
     * webhook controller records a payment the moment it sees an invoice_id
     * and a transaction_id in the same array, and a failed payment that paid
     * an invoice would be the worst bug in this file. It goes back under its
     * own name instead, with the reason beside it, so that whatever comes to
     * chase this invoice later knows what it is chasing.
     *
     * success is true because the event was handled: Stripe is being told the
     * delivery landed, not that the payment worked.
     */
    private function webhookPaymentFailed(array $intentObj): array
    {
        $error       = (array) ($intentObj["last_payment_error"] ?? []);
        $declineCode = $error["decline_code"] ?? ($error["code"] ?? null);
        $reason      = $error["message"] ?? "no reason given";

        Log::warning("Stripe webhook: payment_intent.payment_failed", [
            "intent_id"    => $intentObj["id"] ?? null,
            "invoice_id"   => $intentObj["metadata"]["invoice_id"] ?? null,
            "decline_code" => $declineCode,
            "message"      => $reason,
        ]);

        // A payment can fail here that this module never sent — a browser
        // payment, a card typed in once and gone — so the stored card is only
        // marked when the event names one we hold. The same rule as on the
        // charge path decides, from the same list, so that a refusal cannot be
        // final in one place and worth retrying in the other.
        $this->markStoredCardRefused($intentObj, $error);

        return [
            "success"           => true,
            "message"           => "Payment failed: " . $reason,
            "gateway"           => "stripe",
            "payment_failed"    => true,
            "payment_intent_id" => $intentObj["id"] ?? null,
            "invoice_id"        => $intentObj["metadata"]["invoice_id"] ?? null,
            "decline_code"      => $declineCode,
            "retryable"         => $this->declineIsRetryable($error),
        ];
    }

    /**
     * Write "the customer has to sort this card out" on the card it happened to.
     *
     * The event names the payment method it was trying to use, which is the
     * same token a stored card is charged by, so the row is findable. Nothing
     * happens when it is not one of ours, and nothing happens for a refusal the
     * cardholder cannot do anything about.
     */
    private function markStoredCardRefused(array $intentObj, array $error): void
    {
        if (!$this->refusalEndsTheCard($error)) {
            return;
        }

        $token = $intentObj["payment_method"] ?? ($error["payment_method"]["id"] ?? null);

        if (!is_string($token) || $token === "") {
            return;
        }

        $marked = PaymentMethod::where("gateway_name", "stripe")
            ->where("remote_token", $token)
            ->update(["status" => PaymentMethod::STATUS_REQUIRES_UPDATE]);

        if ($marked > 0) {
            Log::info("Stripe webhook: stored card marked as needing the customer's attention", [
                "payment_method" => $token,
                "decline_code"   => $error["decline_code"] ?? null,
            ]);
        }
    }

    /**
     * A card finished being stored.
     *
     * The SetupIntent is the only thing that knows the card was accepted, so
     * this is where the stored method becomes real. The card's own detail is
     * not in the event — a SetupIntent carries the id of the payment method
     * and nothing about it — so it is asked for separately, and a card that
     * cannot be described is still a card that can be charged.
     */
    private function webhookSetupSucceeded(array $setupIntent): array
    {
        $clientId        = (int) ($setupIntent["metadata"]["client_id"] ?? 0);
        $paymentMethodId = $setupIntent["payment_method"] ?? null;
        $customerId      = $setupIntent["customer"] ?? null;

        if (!$clientId || !$paymentMethodId) {
            Log::warning("Stripe webhook: setup_intent.succeeded named no client or no card", [
                "setup_intent" => $setupIntent["id"] ?? null,
                "client_id"    => $clientId ?: null,
            ]);

            return ["success" => true, "message" => "Setup intent carried nothing to store."];
        }

        $card    = $this->fetchCardDetails((string) $paymentMethodId);
        $columns = $this->cardColumns($card) + [
            // 'cc' rather than 'card': it is what the monthly expiry alert
            // looks for, and a stored card whose owner is never warned it is
            // about to die is how a renewal fails silently.
            "payment_type"        => "cc",
            "gateway_customer_id" => $customerId,
            "status"              => PaymentMethod::STATUS_ACTIVE,
        ];

        if (!empty($card["brand"])) {
            $columns["description"] = trim("Stripe " . ucfirst((string) $card["brand"]));
        }

        // Nothing is caught around this on purpose. Stripe has the card either
        // way, and swallowing a failed write leaves the customer with a card at
        // Stripe that this system has no row for — the renewal it was stored
        // for still fails, and nothing anywhere says why. A deadlock or a lost
        // connection is a transient fault, so the honest answer is to let the
        // delivery fail: the event stays claimed and unstamped, Stripe
        // redelivers for up to three days (https://docs.stripe.com/webhooks),
        // and the next delivery writes the row the first one could not.
        $stored = PaymentMethod::updateOrCreate(
            [
                "client_id"    => $clientId,
                "gateway_name" => "stripe",
                "remote_token" => $paymentMethodId,
            ],
            $columns
        );

        // A stored card nothing points at is a stored card the charger will not
        // use. AutoChargeService refuses to choose between several cards when
        // none is marked, so without this the first card a client stores leaves
        // them with automatic payment switched on, a card on file, and a
        // collection that stops at the second card with nothing said to anybody
        // but the log. The model holds the rule, including its refusal to
        // demote a default the customer picked themselves.
        $stored->becomeDefaultIfClientHasNone();

        // The customer this card hangs off, in the place that is asked first
        // next time. Normally it is already there, written when the customer
        // was created; this catches a card stored against a customer made
        // before that table existed.
        if (is_string($customerId) && $customerId !== "") {
            GatewayCustomer::remember("stripe", $clientId, $customerId);
        }

        Log::info("Stripe webhook: card stored", [
            "client"       => $clientId,
            "setup_intent" => $setupIntent["id"] ?? null,
        ]);

        return [
            "success"     => true,
            "message"     => "Stored card confirmed.",
            "gateway"     => "stripe",
            "client_id"   => $clientId,
            "customer_id" => $customerId,
        ];
    }

    /**
     * Stripe's card updater replaced the card behind a stored token.
     *
     * The issuer reissued the card — new number, new expiry, same customer —
     * and Stripe swapped it in behind the token we already hold. Nothing needs
     * re-vaulting; what is stale is the four digits and the expiry shown to
     * the customer and mailed at them a month before it runs out.
     */
    private function webhookCardUpdated(array $paymentMethod): array
    {
        $token = $paymentMethod["id"] ?? null;
        $card  = $this->cardDetails($paymentMethod);

        if (!$token || $card === []) {
            return ["success" => true, "message" => "Card update carried nothing to refresh."];
        }

        $stored = PaymentMethod::where("gateway_name", "stripe")
            ->where("remote_token", $token)
            ->get();

        if ($stored->isEmpty()) {
            // A card belonging to this Stripe account but not to this
            // installation — two systems on one account, or a card removed
            // here and still live there. Nothing to do, and not an error.
            Log::info("Stripe webhook: card updater refreshed a card that is not stored here", [
                "payment_method" => $token,
            ]);

            return ["success" => true, "message" => "Card is not stored here."];
        }

        // Back to active: a card the updater has just replaced is a card the
        // issuer expects to be charged again.
        $columns = $this->cardColumns($card) + ["status" => PaymentMethod::STATUS_ACTIVE];

        foreach ($stored as $method) {
            $method->update($columns);
        }

        Log::info("Stripe webhook: stored card refreshed by the card updater", [
            "payment_method" => $token,
            "methods"        => $stored->count(),
        ]);

        return [
            "success" => true,
            "message" => "Stored card refreshed.",
            "gateway" => "stripe",
        ];
    }

    /**
     * Ask Stripe what the card behind a token looks like.
     *
     * The token is what charges the card; the brand and the four digits are
     * only what the customer recognises it by on a list. But they are also what
     * the monthly expiry alert reads — it looks for a 'cc' method with a
     * last_four and an expiry_date, and a card missing either is a card whose
     * owner is never warned it is about to die, which is the silent renewal
     * failure the whole vaulting exercise exists to prevent.
     *
     * So the two kinds of failure are told apart. Stripe having a bad minute is
     * temporary, and the answer is to let the delivery fail so that the same
     * event arrives again, for up to three days, until one of those attempts
     * describes the card (https://docs.stripe.com/webhooks). Stripe saying it
     * has never heard of this payment method will still be true tomorrow, so
     * the card is stored with what little is known rather than being lost over
     * four digits — and said out loud in the log, because that customer is the
     * one the expiry alert will skip.
     *
     * @return array{brand?: string, last4?: string, exp_month?: int, exp_year?: int}
     *
     * @throws ConnectionException|\RuntimeException when the lookup is worth repeating
     */
    private function fetchCardDetails(string $paymentMethodId): array
    {
        $secretKey = $this->getSetting("secret_key");
        if (!$secretKey) {
            return [];
        }

        // ConnectionException is deliberately not caught: it is the most
        // temporary failure there is, and it belongs with the ones below.
        $response = Http::withToken($secretKey)
            ->get("https://api.stripe.com/v1/payment_methods/{$paymentMethodId}");

        if ($response->status() >= 500 || $response->status() === 429) {
            Log::warning("Stripe: card lookup could not be answered, asking for a redelivery", [
                "payment_method" => $paymentMethodId,
                "status"         => $response->status(),
            ]);

            throw new \RuntimeException("Stripe could not describe payment method {$paymentMethodId} (HTTP {$response->status()}).");
        }

        if (!$response->successful()) {
            Log::warning("Stripe: card lookup refused, storing the card undescribed", [
                "payment_method" => $paymentMethodId,
                "status"         => $response->status(),
                "consequence"    => "no last_four or expiry_date, so the expiry alert will not warn this customer",
            ]);

            return [];
        }

        return $this->cardDetails((array) $response->json());
    }

    /**
     * The card fields of a Stripe payment method object, where there are any.
     *
     * @return array{brand?: string, last4?: string, exp_month?: int, exp_year?: int}
     */
    private function cardDetails(array $paymentMethod): array
    {
        $card = $paymentMethod["card"] ?? null;

        if (!is_array($card)) {
            return [];
        }

        return array_filter([
            "brand"     => $card["brand"] ?? null,
            "last4"     => $card["last4"] ?? null,
            "exp_month" => isset($card["exp_month"]) ? (int) $card["exp_month"] : null,
            "exp_year"  => isset($card["exp_year"]) ? (int) $card["exp_year"] : null,
        ], fn ($value) => $value !== null && $value !== "");
    }

    /**
     * Card detail written the way payment_methods stores it.
     *
     * Only what Stripe actually said is written, so a lookup that came back
     * empty leaves what is already on the row alone rather than blanking it.
     *
     * expiry_date gets the same 'Y-m' text the expiry alert compares against,
     * so a vaulted card warns its owner exactly as a hand-typed one does;
     * exp_month and exp_year hold the same fact in a form that can be shown
     * and sorted.
     */
    private function cardColumns(array $card): array
    {
        $columns = [];

        foreach (["brand" => "card_brand", "last4" => "last_four", "exp_month" => "exp_month", "exp_year" => "exp_year"] as $field => $column) {
            if (isset($card[$field])) {
                $columns[$column] = $card[$field];
            }
        }

        if (isset($card["exp_month"], $card["exp_year"])) {
            $columns["expiry_date"] = sprintf("%04d-%02d", $card["exp_year"], $card["exp_month"]);
        }

        return $columns;
    }
}
