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
use App\Models\Transaction;
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

        // The invoice's own currency, not the shop's current one. The two are
        // the same until an operator changes the shop currency, and after that
        // the shop's answer reinterprets an old invoice at the new sign - the
        // exact failure add_source_currency_to_invoices was written to stop
        // ("a 264.89 lira invoice reprinted as 264.89 dollars"). Charging is
        // where that misreading costs money rather than ink.
        //
        // It also has to agree with refund(): that leg converts with
        // refundCurrency(), which reads the same column. Two legs reading two
        // different sources is a hundredfold refund error the moment one of
        // them is zero-decimal.
        //
        // Invoices raised before that column existed carry null and fall back
        // to the shop, which is exactly what this line did for everyone before.
        $currency = strtolower($params["currency"] ?? ($invoice->source_currency ?: shop_currency_code()));

        // Converted with the very currency this request carries, three lines
        // down, so the figure and the unit it is counted in cannot come apart.
        // A hundred yen is a hundred, not ten thousand.
        $minorAmount = $this->minorUnits($amount, $currency);

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
                "amount"                      => $minorAmount,
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

        $minorAmount = $this->minorUnits($amount, $this->refundCurrency($transactionId));

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
                "amount"         => $minorAmount,
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
     * A name Stripe can recognise a repeat of this exact request by.
     *
     * Stripe keeps the answer it gave the first time a key was used and hands
     * that same answer back for the next twenty-four hours instead of doing
     * the work again. So a POST that timed out on the network and was sent
     * again creates one customer and takes one payment, not two.
     *
     * Two things about how the key is built matter. It is derived rather than
     * random: a fresh random key on the retry would be a different request to
     * Stripe and would defeat the whole mechanism. And everything that varies
     * goes into it, the amount above all, because Stripe treats the same key
     * arriving with different parameters as an error rather than as a repeat.
     * Deriving the key from the very figures being sent is what keeps those
     * two frozen together: a different amount is a different key describing a
     * genuinely different request, so the error case cannot arise.
     *
     * The digest is keyed on the application key so that two installations
     * sharing one Stripe account cannot both claim invoice #5.
     *
     * Only two calls carry one, and both are calls no human is watching: the
     * customer record a card hangs off, and the off-session charge. The two
     * browser-driven calls — creating an intent to pay, and refunding one — do
     * not, for reasons written where each of them sends.
     *
     * What the key does not do is worth stating plainly. It protects "charge
     * invoice N this exact amount", not "charge invoice N", because the amount
     * is inside it; and Stripe prunes keys after twenty-four hours ("We
     * generate a new request if a key is reused after the original is pruned",
     * https://docs.stripe.com/api/idempotent_requests). A retry a day later, or
     * after a late fee has moved the balance, is therefore a genuinely new
     * request. Closing that gap needs the attempt written down before the POST
     * — invoice, amount, key, resulting intent — which is work for whichever
     * dunning run first calls chargeStoredMethod(), since nothing calls it yet.
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
     * Every path that names an amount to Stripe now comes through here:
     * capture(), refund() and chargeStoredMethod() on the way out, with
     * verifyPaymentIntent() and webhookPaymentSucceeded() reading Stripe's
     * figures back through reportedMajorUnits() on the way in. They moved
     * together on purpose. Until they did, the four were wrong in both
     * directions at once, and that is the only reason a zero-decimal shop's
     * books agreed with themselves: the charge was a hundred times the invoice
     * and the amount recorded against it was a hundredth of what Stripe took,
     * so the ledger balanced while the customer was out a hundredfold.
     * Correcting the outbound half alone would have turned a loud disaster into
     * a quiet one.
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

    /**
     * A figure Stripe has reported, read back into the units the shop counts in.
     *
     * Stripe says what currency its own objects are in — "currency (enum):
     * Three-letter ISO currency code, in lowercase"
     * (https://docs.stripe.com/api/payment_intents/object) — so neither of the
     * two inbound paths has to guess. Where that currency is zero-decimal the
     * division by a hundred is simply wrong and majorUnits() is asked instead.
     *
     * Everywhere else the expression these paths have always used is kept
     * exactly, down to its type: 2500/100 is the int 25 in PHP, 5050/100 is the
     * float 50.5, and a test holds that int in place deliberately
     * (tests/Feature/StripeWebhookDeduplicationTest.php, "a successful payment
     * still answers exactly as it did"). Handing majorUnits() the two-decimal
     * case as well would read better and would change the answer a two-decimal
     * shop gets — 25 becoming 25.0 — for no gain: both callers' figures are
     * cast to float before anything is written down
     * (GatewayWebhookController::handle and ::stripeConfirm), so the change
     * would be invisible everywhere except in that assertion. Invisible is not
     * the same as absent, and the shops that are fine today get to stay
     * byte-identical.
     *
     * A currency that is missing, or is not a string, falls back to that same
     * division — which is what both paths did for every currency until now, so
     * an unrecognisable payload behaves exactly as it always has rather than
     * newly refusing. Stripe puts the field on every PaymentIntent it sends;
     * in practice this fallback is reached only by hand-written payloads.
     */
    private function reportedMajorUnits(mixed $minorAmount, mixed $currency): int|float
    {
        if (is_string($currency) && in_array(strtolower($currency), self::ZERO_DECIMAL_CURRENCIES, true)) {
            return $this->majorUnits((int) $minorAmount, $currency);
        }

        return $minorAmount / 100;
    }

    /**
     * The currency an amount about to be refunded is counted in.
     *
     * A refund request carries no currency of its own — it names the payment
     * intent and an amount, and Stripe reads the currency off the intent — and
     * the gateway contract hands this method nothing but an id and a number.
     * So the unit has to be recovered, and the honest question is what unit the
     * number is already in rather than what Stripe will read it as.
     *
     * It comes from PaymentService::refundInvoice(), which works it out from
     * the payments recorded against one invoice (app/Services/PaymentService.php:224-243)
     * and then picks the settling transaction to refund against
     * (PaymentService.php:245-249). So the number is in that invoice's own
     * currency — the one stamped on the row the day it was raised, in
     * Invoice::booted() (app/Models/Invoice.php:43-45) — and that same row is
     * findable here by the id being refunded against.
     *
     * The shop's current currency is the fallback, for an id with no row behind
     * it and for an invoice raised before that column existed. It is also what
     * this method used implicitly until now, so nothing moves for a shop that
     * has always sold in one currency: source_currency and shop_currency_code()
     * are then the same three letters.
     *
     * Stripe is the authority on what currency the intent is in and is
     * deliberately not asked. That would be a second API call on every refund,
     * including the two-decimal refunds that are nearly all of them, and this
     * repair is not allowed to add a request to a path that works today.
     *
     * The lookup does not filter on the gateway. The id is a Stripe id already,
     * and a row written before this module settled on a lower-case gateway name
     * would be missed by such a filter and silently fall back.
     */
    private function refundCurrency(string $transactionId): string
    {
        $invoice = Transaction::query()
            ->where("transaction_id", $transactionId)
            ->whereNotNull("invoice_id")
            ->latest("id")
            ->first()?->invoice;

        return strtolower($invoice?->source_currency ?: shop_currency_code());
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
            // Stripe's own figure, in Stripe's own units, read back through the
            // currency Stripe put on the intent it came from.
            "amount"         => $this->reportedMajorUnits((int) ($intent["amount_received"] ?? 0), $intent["currency"] ?? null),
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
     * The idempotency key covers the invoice, the card and the amount, so a
     * job that crashes between the POST and writing down what happened can be
     * run again without taking the money twice. Because the key expires after
     * a day, a genuine retry tomorrow is a genuine new attempt.
     *
     * Nothing is asked of Stripe until the row itself has been read. A card the
     * customer removed and a card already known to need their attention are
     * both refused here, because removing a card at this end does not detach it
     * at Stripe — the token on a deleted row will very likely still be accepted
     * — and because issuers read repeated attempts on a card they have already
     * refused as fraud, which drags down acceptance on the cards that would
     * have worked.
     */
    public function chargeStoredMethod(Invoice $invoice, PaymentMethod $method, float $amount, array $params = []): array
    {
        $secretKey = $this->getSetting("secret_key");
        if (!$secretKey) {
            // Retryable: nothing was asked of the card, so nothing has been
            // learned about it. Once a key is configured this same attempt
            // works, and refusing to try again would strand the invoice.
            return $this->chargeFailed("Stripe secret key not configured.", null, true);
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
            return $this->chargeFailed("Stored payment method does not belong to this invoice's client.");
        }

        if ($method->gateway_name !== "stripe") {
            return $this->chargeFailed("Stored payment method was not stored with Stripe.");
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

            return $this->chargeFailed("Stored payment method has been removed.");
        }

        // And a card already known to need the customer's attention is not
        // worth asking the issuer about again; that is what the status is for.
        if ($method->status !== PaymentMethod::STATUS_ACTIVE) {
            Log::warning("Stripe: refused to charge a stored card that is not active", [
                "invoice" => $invoice->id,
                "method"  => $method->id,
                "status"  => $method->status,
            ]);

            return $this->chargeFailed("Stored payment method needs the customer to update it.");
        }

        $paymentMethodId = $method->remote_token;
        $customerId      = $method->gateway_customer_id;

        if (!$paymentMethodId || !$customerId) {
            return $this->chargeFailed("Stored payment method is missing its Stripe customer or token.");
        }

        $currency     = strtolower($params["currency"] ?? shop_currency_code());
        $minorAmount  = $this->minorUnits($amount, $currency);

        if ($minorAmount <= 0) {
            return $this->chargeFailed("Nothing left to charge on this invoice.");
        }

        try {
            $response = Http::asForm()
                ->withToken($secretKey)
                ->withHeaders(["Idempotency-Key" => $this->idempotencyKey("offsession", [$invoice->id, $method->id, $minorAmount, $currency])])
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
            // The request may well have reached Stripe; we simply never heard
            // the answer. Saying so honestly and asking for another attempt is
            // safe precisely because the key above makes the second attempt
            // the same request rather than a second charge.
            Log::warning("Stripe: off-session charge could not be sent", [
                "invoice" => $invoice->id,
                "error"   => $e->getMessage(),
            ]);
            return $this->chargeFailed("Stripe could not be reached: " . $e->getMessage(), null, true);
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
            // invoice must not be credited on a maybe. Not a dead end either,
            // so another look later is worth having, and the webhook will say
            // what became of it in the meantime.
            Log::info("Stripe: off-session charge is not finished", [
                "invoice" => $invoice->id,
                "intent"  => $intent["id"] ?? null,
                "status"  => $status,
            ]);

            return $this->chargeFailed("Stripe returned an unfinished payment (status: " . ($status ?: "unknown") . ").", null, true);
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
        if ($response->status() === 402 && $this->cardholderMustAuthenticate($error)) {
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
            $this->declineIsRetryable($error, $response->status())
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
     * The rest divides the way the documentation does: a bad minute at Stripe
     * or too many requests is worth repeating, an invalid request is not unless
     * it is one of the three that describe a moment, and an idempotency error
     * means the same key was sent with different parameters, which will be just
     * as true tomorrow.
     */
    private function declineIsRetryable(array $error, ?int $status = null): bool
    {
        $advice = $error["advice_code"] ?? null;

        if ($advice === "do_not_try_again" || $advice === "confirm_card_data") {
            return false;
        }

        if ($advice === "try_again_later") {
            return true;
        }

        // Stripe having trouble, or asking us to slow down. Both are about the
        // connection rather than the card, so they are answered before anything
        // in the body is read. https://docs.stripe.com/api/errors
        if ($status !== null && ($status >= 500 || $status === 429)) {
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
            // A refusal that named no type at all. Nothing has been learned
            // about the card, so the only honest reading is the transport's:
            // worth another go unless the status said otherwise.
            default                 => $status === null || $status >= 500,
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
     * The shape of the answer is unchanged from the day this module was
     * written: the webhook controller credits the invoice when it sees an
     * invoice_id and a transaction_id together, and PaymentService refuses the
     * same transaction twice. Only the reading of the amount has moved, onto
     * the currency the event itself names.
     */
    private function webhookPaymentSucceeded(array $intentObj): array
    {
        $intentId  = $intentObj["id"] ?? null;
        $invoiceId = $intentObj["metadata"]["invoice_id"] ?? null;

        // What the event says was collected, in the currency the same event
        // says it was collected in. A yen payment reports 5000 and means 5000;
        // dividing it by a hundred credited the invoice with fifty and left the
        // books agreeing with a charge that was a hundred times too big.
        $amount = $this->reportedMajorUnits($intentObj["amount_received"] ?? 0, $intentObj["currency"] ?? null);

        Log::info("Stripe webhook: payment_intent.succeeded", [
            "intent_id"  => $intentId,
            "invoice_id" => $invoiceId,
            "amount"     => $amount,
        ]);

        return [
            "success"        => true,
            "transaction_id" => $intentId,
            "invoice_id"     => $invoiceId,
            "amount"         => $amount,
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
        PaymentMethod::updateOrCreate(
            [
                "client_id"    => $clientId,
                "gateway_name" => "stripe",
                "remote_token" => $paymentMethodId,
            ],
            $columns
        );

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
