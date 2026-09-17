<?php

namespace Modules\Gateways\PayPal;

use App\Contracts\GatewayModuleInterface;
use App\Contracts\TokenizableGatewayInterface;
use App\Models\Client;
use App\Models\GatewayCustomer;
use App\Models\GatewaySettings;
use App\Models\GatewayVaultSession;
use App\Models\Invoice;
use App\Models\PaymentMethod;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PayPalModule implements GatewayModuleInterface, TokenizableGatewayInterface
{
    public function getModuleName(): string
    {
        return "PayPal";
    }

    /**
     * PayPal holds a handle to a payment method for this shop.
     *
     * True since the vault was implemented below, and it is only a statement of
     * fact: nothing in PNLCS branches on it. The question that decides whether
     * a stored method may be charged with nobody watching is whether this class
     * implements TokenizableGatewayInterface, which ModuleRegistry asks and this
     * flag deliberately does not answer.
     */
    public function isTokenised(): bool
    {
        return true;
    }
    public function getConfigFields(): array
    {
        return [
            ["name" => "email",         "label" => "PayPal Email",   "type" => "text"],
            ["name" => "client_id",     "label" => "Client ID",      "type" => "text", "required" => true],
            ["name" => "client_secret", "label" => "Client Secret",  "type" => "password", "required" => true],
            ["name" => "sandbox",       "label" => "Sandbox Mode",   "type" => "select", "options" => ["0" => "Live", "1" => "Sandbox"]],
        ];
    }

    private function getSetting(string $key): ?string
    {
        $row = GatewaySettings::where("gateway", "paypal")->where("setting", $key)->first();
        return $row?->value;
    }

    private function getBaseUrl(): string
    {
        return ($this->getSetting("sandbox") === "1")
            ? "https://api-m.sandbox.paypal.com"
            : "https://api-m.paypal.com";
    }

    /**
     * Currencies PayPal will not accept a decimal amount in.
     *
     * "This currency does not support decimals. If you pass a decimal amount,
     * an error occurs." — said of the Hungarian forint, the Japanese yen and
     * the New Taiwan dollar, and of nothing else, on PayPal's list of the
     * twenty-five currencies it takes
     * (https://developer.paypal.com/api/rest/reference/currency-codes/,
     * fetched 2026-09-17).
     *
     * STRIPE'S TABLE IS NOT THIS TABLE AND MUST NEVER BE COPIED INTO IT.
     * Stripe's zero-decimal list is fifteen currencies long and contains BIF,
     * CLP, KRW, VND and ten others PayPal does not accept at all; it contains
     * neither HUF nor TWD, which Stripe explicitly charges in hundredths and
     * PayPal explicitly refuses to. A shop selling in forint that was handed
     * Stripe's answer here would send PayPal "1990.00" and be refused, and a
     * shop selling in won would send a hundredth of the invoice. Every
     * processor gets its own table, read off its own documentation.
     */
    private const NON_DECIMAL_CURRENCIES = ["HUF", "JPY", "TWD"];

    /**
     * An amount in the form PayPal wants to be told about it in.
     *
     * PAYPAL IS NOT A MINOR-UNIT API AND MULTIPLYING BY A HUNDRED HERE WOULD
     * CHARGE A HUNDRED TIMES THE INVOICE. Its money object carries a decimal
     * string in the currency's ordinary units — "value": "100.00" for a hundred
     * dollars, not 10000 (https://developer.paypal.com/api/payment-tokens/save-without-purchase/paypal,
     * fetched 2026-09-17). So the only thing this has to get right is how many
     * decimal places the string may carry, and for three currencies the answer
     * is none at all.
     *
     * Rounded to whole units for those three rather than truncated: an invoice
     * of ¥1990.40 is a bill for ¥1990, and sending "1990.40" is not a smaller
     * charge, it is an error and no charge at all.
     *
     * The shop's currency is operator-set free text with no validation behind
     * it, so this holds for whatever is typed in: anything not on the list gets
     * the two decimal places every other PayPal currency takes.
     */
    private function amountValue(float $amount, string $currency): string
    {
        if (in_array($this->currencyCode($currency), self::NON_DECIMAL_CURRENCIES, true)) {
            return number_format(round($amount), 0, ".", "");
        }

        return number_format($amount, 2, ".", "");
    }

    /**
     * The same conversion read back.
     *
     * There is nothing to undo — PayPal answers in the same ordinary units it
     * was asked in — and that is the whole reason this exists as a named
     * method: the one line of arithmetic that must never appear on this leg is
     * a division by a hundred, and a reader looking for it finds this instead.
     */
    private function amountFromValue(mixed $value): float
    {
        return (float) $value;
    }

    /** PayPal takes ISO-4217 in upper case; the shop's setting is free text. */
    private function currencyCode(?string $currency): string
    {
        return strtoupper(trim((string) $currency));
    }

    /**
     * A NAME PAYPAL CAN RECOGNISE A REPEAT OF THIS EXACT REQUEST BY — the
     * fallback, and never the main road.
     *
     * "To enforce idempotency on REST API POST calls, use the PayPal-Request-Id
     * request header, which contains a unique user-generated ID that the server
     * stores for a period of time." — "When you include a previously specified
     * PayPal-Request-Id header in a request, PayPal returns the latest status
     * of the previous request that used that same header. Conversely, when you
     * omit the PayPal-Request-Id header from a request, PayPal duplicates the
     * request." (https://developer.paypal.com/reference/guidelines/idempotency/
     * and https://developer.paypal.com/api/rest/reference/idempotency/, both
     * fetched 2026-09-17.)
     *
     * A CALLER WITH A MEMORY SUPPLIES THE KEY INSTEAD, AND IS ALWAYS PREFERRED.
     * InvoiceChargeAttempt mints a random key, writes it down with the row
     * before the first POST and hands the same string back on every repeat, so
     * it cannot drift. This digest can: it is keyed on config('app.key'), and
     * an operator who rotates APP_KEY between a send and its replay changes the
     * name the charge went out under with nothing anywhere signalling it — the
     * repeat arrives under a name PayPal has never seen, PayPal has no saved
     * result to answer from, and it charges again. That is not hypothetical;
     * it is the defect InvoiceChargeAttempt::mintIdempotencyKey() was written
     * to repair, and the reason this method is reachable from exactly one place
     * that moves money, and only when the caller brought nothing.
     *
     * It is derived rather than random because a caller with nothing written
     * down needs the same figures to give the same name; everything that varies
     * goes into it, the amount above all; and the digest is keyed on the
     * application key so that two installations sharing one PayPal account do
     * not both claim invoice #5 — which holds until one of them is a restored
     * image of the other.
     *
     * The scope is part of the string on purpose. "The PayPal-Request-Id header
     * value must be unique for both each request and an API call type" (same
     * page), so the name a vault exchange goes out under can never collide with
     * the name a charge goes out under.
     */
    private function requestId(string $scope, array $parts): string
    {
        $digest = hash_hmac("sha256", implode("|", $parts), (string) config("app.key"));

        return "pnlcs-{$scope}-" . substr($digest, 0, 40);
    }

    /**
     * NO PayPal-Request-Id ON THE TOKEN GRANT, and none is wanted.
     *
     * The header is documented for "REST API POST calls" whose repeat would
     * "create or complete an action on a resource more than once"
     * (https://developer.paypal.com/reference/guidelines/idempotency/, fetched
     * 2026-09-17). A client-credentials grant creates no resource anybody acts
     * on and moves no money; duplicating it costs a second short-lived bearer
     * token, which expires on its own. PayPal documents no idempotency support
     * for this endpoint and there is nothing here for it to protect.
     */
    private function getAccessToken(): ?string
    {
        $clientId     = $this->getSetting("client_id");
        $clientSecret = $this->getSetting("client_secret");

        if (!$clientId || !$clientSecret) {
            return null;
        }

        $response = Http::asForm()
            ->withBasicAuth($clientId, $clientSecret)
            ->post($this->getBaseUrl() . "/v1/oauth2/token", [
                "grant_type" => "client_credentials",
            ]);

        if (!$response->successful()) {
            Log::error("PayPal: failed to obtain access token", [
                "status" => $response->status(),
                "body"   => $response->body(),
            ]);
            return null;
        }

        return $response->json("access_token");
    }

    /**
     * Open an order for the customer's own browser to approve and pay.
     *
     * NO PayPal-Request-Id HERE, DELIBERATELY, and the reason is PayPal's own
     * description of what one does: "When you include a previously specified
     * PayPal-Request-Id header in a request, PayPal returns the latest status
     * of the previous request that used that same header"
     * (https://developer.paypal.com/reference/guidelines/idempotency/, fetched
     * 2026-09-17). This endpoint is reached from the pay form every time a
     * customer opens it. Under a key derived from the invoice and the amount,
     * the second visit would be answered with the FIRST visit's order — which
     * may by then be COMPLETED, expired, or cancelled — and the customer would
     * be handed an approve link that can no longer take their money. Under a
     * key that varied per visit there would be no idempotency at all, only a
     * header.
     *
     * Nothing is lost by going without. No payment_source is sent, so PayPal
     * neither asks for the header (PAYPAL_REQUEST_ID_REQUIRED is raised only
     * "if you are trying to process payment for an Order",
     * https://developer.paypal.com/api/rest/reference/orders/v2/errors/) nor
     * takes any money on this call: an unapproved duplicate order is an empty
     * record that expires by itself. The money moves when the browser approves
     * and captures, and that capture is settled once by capture id, which
     * GatewayWebhookController and PaymentService dedupe on.
     */
    public function capture(Invoice $invoice, float $amount, array $params = []): array
    {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return ["success" => false, "message" => "PayPal credentials not configured or token request failed."];
        }

        $currency = $this->currencyCode($params["currency"] ?? shop_currency_code());

        $response = Http::withToken($accessToken)
            ->post($this->getBaseUrl() . "/v2/checkout/orders", [
                "intent" => "CAPTURE",
                "purchase_units" => [[
                    "reference_id" => "INV-" . $invoice->id,
                    "description"  => "Invoice #" . ($invoice->invoice_num ?? $invoice->id),
                    "amount"       => [
                        "currency_code" => $currency,
                        "value"         => $this->amountValue($amount, $currency),
                    ],
                ]],
                "application_context" => [
                    "return_url"  => url("/client/invoices/" . $invoice->id . "?payment=success"),
                    "cancel_url"  => url("/client/invoices/" . $invoice->id . "?payment=cancelled"),
                    "brand_name"  => config("app.name", "PNLCS"),
                    "user_action" => "PAY_NOW",
                ],
            ]);

        if (!$response->successful()) {
            Log::error("PayPal: create order failed", [
                "invoice" => $invoice->id,
                "status"  => $response->status(),
                "body"    => $response->body(),
            ]);
            return [
                "success" => false,
                "message" => "PayPal order creation failed: " . $response->json("message", "Unknown error"),
            ];
        }

        $order       = $response->json();
        $orderId     = $order["id"] ?? null;
        $approveLink = null;

        foreach ($order["links"] ?? [] as $link) {
            if ($link["rel"] === "approve") {
                $approveLink = $link["href"];
                break;
            }
        }

        return [
            "success"      => true,
            "order_id"     => $orderId,
            "approve_url"  => $approveLink,
            "redirect"     => true,
            "redirect_url" => $approveLink,
        ];
    }

    /**
     * Hand money back, and do it WITHOUT a PayPal-Request-Id.
     *
     * THIS IS THE ONE CALL IN THE MODULE THAT MOVES MONEY IN THE WRONG
     * DIRECTION, AND THE ONE PLACE WHERE GOING WITHOUT A KEY IS SAFER THAN
     * GUESSING AT ONE. PayPal documents this very endpoint as the example of
     * what the header buys — "a user calls refund captured payment with the
     * PayPal-Request-Id header that contains a unique user-provided ID. The
     * user can make the call again with the same ID in the PayPal-Request-Id
     * header for up to 45 days ... If the initial call fails with the HTTP 500
     * status code but the server has already refunded the payment, the caller
     * does not need to worry that the server will refund the payment again"
     * (https://developer.paypal.com/api/rest/requests/, fetched 2026-09-17).
     * That is a real protection and this method is deliberately giving it up.
     *
     * A KEY HAS TO BE STABLE ACROSS A RETRY OF THE SAME REFUND AND DIFFERENT
     * FOR A NEW ONE, AND NOTHING HERE CAN TELL THOSE APART. PaymentService::
     * refundInvoice() always refunds against the same settling capture id, and
     * the admin refund form takes a free-text amount, so two genuine partial
     * refunds of the same size against the same payment arrive at this method
     * as identical arguments. Keyed on those two, PayPal would answer the
     * second with the first one's result — "PayPal returns the latest status of
     * the previous request that used that same header"
     * (https://developer.paypal.com/reference/guidelines/idempotency/) — this
     * method would report success, and PaymentService would write a second
     * refund transaction and hand the customer credit for money that never
     * left. Books saying fifty while PayPal moved twenty-five is worse than the
     * duplicate a key would have prevented.
     *
     * AND PAYPAL'S RETENTION MAKES IT WORSE HERE THAN IT WAS FOR STRIPE, not
     * better. Stripe prunes an idempotency key after twenty-four hours, so a
     * wrong key there collides for a day; PayPal honours one on this endpoint
     * for forty-five. A shop refunding the same monthly amount against the same
     * capture would collide for six weeks.
     *
     * THE IDENTITY A KEY NEEDS DOES NOT EXIST AT THIS MOMENT. The refund
     * transaction row — the only thing that could name this refund and not the
     * one before it — is written after this call returns, and the gateway
     * contract has no slot for a caller-supplied key. Minting one inside this
     * method cannot help: the method is stateless and would derive the same
     * string from the same arguments, which is precisely the collision. Giving
     * refunds a durable identity means a change to PaymentService and to the
     * contract every gateway implements, and it belongs in its own piece of
     * work rather than smuggled in behind a header.
     */
    public function refund(string $transactionId, float $amount): array
    {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return ["success" => false, "message" => "PayPal credentials not configured."];
        }

        $currency = $this->currencyCode(shop_currency_code());

        $response = Http::withToken($accessToken)
            ->post($this->getBaseUrl() . "/v2/payments/captures/{$transactionId}/refund", [
                "amount" => [
                    "value"         => $this->amountValue($amount, $currency),
                    "currency_code" => $currency,
                ],
            ]);

        if (!$response->successful()) {
            Log::error("PayPal: refund failed", [
                "transaction" => $transactionId,
                "status"      => $response->status(),
                "body"        => $response->body(),
            ]);
            return [
                "success" => false,
                "message" => "PayPal refund failed: " . $response->json("message", "Unknown error"),
            ];
        }

        $data = $response->json();
        return [
            "success"        => true,
            "refund_id"      => $data["id"] ?? null,
            "status"         => $data["status"] ?? "COMPLETED",
            "transaction_id" => $transactionId,
        ];
    }

    public function getPaymentForm(Invoice $invoice): string
    {
        $clientId  = $this->getSetting("client_id") ?? "";
        $invoiceId = (int) $invoice->id;
        $currency  = $this->currencyCode(shop_currency_code());
        // The buttons build the order in the browser, so the amount goes out
        // shaped the same way it would from this server: PayPal refuses a
        // decimal in HUF, JPY or TWD wherever the order is created.
        $amount    = $this->amountValue((float) $invoice->amountDue(), $currency);
        $display   = money_fmt($invoice->total);

        if (!$clientId) {
            return "<div class=\"alert alert-danger\">PayPal is not configured. Please contact support.</div>";
        }

        $safeClientId = htmlspecialchars($clientId, ENT_QUOTES, "UTF-8");
        $captureUrl   = url("/gateway/paypal/capture/{$invoiceId}");
        $successUrl   = url("/client/invoices/{$invoiceId}?payment=success");

        return <<<HTML
<div id="paypal-button-container" class="my-3"></div>
<div id="paypal-message" class="mt-2"></div>
<script src="https://www.paypal.com/sdk/js?client-id={$safeClientId}&currency={$currency}"></script>
<script>
paypal.Buttons({
    createOrder: function(data, actions) {
        return actions.order.create({
            purchase_units: [{ reference_id: "INV-{$invoiceId}", amount: { value: "{$amount}", currency_code: "{$currency}" } }]
        });
    },
    onApprove: function(data, actions) {
        return actions.order.capture().then(function(details) {
            var capture = details.purchase_units[0].payments.captures[0];
            fetch("{$captureUrl}", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": document.querySelector("meta[name=csrf-token]") ? document.querySelector("meta[name=csrf-token]").content : ""
                },
                body: JSON.stringify({ order_id: data.orderID, capture_id: capture.id })
            })
            .then(function(r) { return r.json(); })
            .then(function(result) {
                if (result.success) {
                    window.location.href = result.redirect_url || "{$successUrl}";
                } else {
                    document.getElementById("paypal-message").innerHTML = "<div class=\"alert alert-danger\">" + (result.message || "Payment failed") + "</div>";
                }
            });
        });
    },
    onError: function(err) {
        document.getElementById("paypal-message").innerHTML = "<div class=\"alert alert-danger\">PayPal error: " + err + "</div>";
    }
}).render("#paypal-button-container");
</script>
HTML;
    }

    /**
     * Verify a capture directly against the PayPal API.
     *
     * The webhook payload and the browser-supplied capture_id are both
     * untrusted — anyone can POST a fake PAYMENT.CAPTURE.COMPLETED. We
     * re-fetch the capture from PayPal and only trust its status/amount.
     *
     * @return array{success: bool, message?: string, amount?: float, currency?: string, invoice_ref?: ?string}
     */
    public function verifyCapture(string $captureId): array
    {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return ["success" => false, "message" => "PayPal credentials not configured."];
        }

        $response = Http::withToken($accessToken)
            ->get($this->getBaseUrl() . "/v2/payments/captures/" . urlencode($captureId));

        if (!$response->successful()) {
            Log::warning("PayPal: capture verification request failed", [
                "capture_id" => $captureId,
                "status"     => $response->status(),
            ]);
            return ["success" => false, "message" => "Could not verify capture with PayPal."];
        }

        $status = $response->json("status");
        if ($status !== "COMPLETED") {
            return ["success" => false, "message" => "Capture status is '{$status}', not COMPLETED."];
        }

        return [
            "success"     => true,
            "amount"      => (float) $response->json("amount.value", 0),
            "currency"    => $response->json("amount.currency_code"),
            "invoice_ref" => $response->json("invoice_id") ?? $response->json("custom_id"),
        ];
    }

    public function processWebhook(array $data): array
    {
        $eventType = $data["event_type"] ?? "";

        if ($eventType !== "PAYMENT.CAPTURE.COMPLETED") {
            return ["success" => true, "message" => "Event ignored: " . $eventType];
        }

        $resource   = $data["resource"] ?? [];
        $captureId  = $resource["id"] ?? null;
        $invoiceRef = $resource["purchase_units"][0]["reference_id"] ?? null;

        if (!$captureId || !$invoiceRef) {
            return ["success" => false, "message" => "Missing capture ID or invoice reference."];
        }

        // Never trust the webhook body — confirm the capture with PayPal.
        $verified = $this->verifyCapture($captureId);
        if (!($verified["success"] ?? false)) {
            Log::warning("PayPal webhook rejected — capture not verified", [
                "capture_id" => $captureId,
                "reason"     => $verified["message"] ?? "unknown",
            ]);
            return ["success" => false, "message" => $verified["message"] ?? "Capture verification failed."];
        }

        $invoiceId = str_replace("INV-", "", (string) $invoiceRef);

        Log::info("PayPal webhook: PAYMENT.CAPTURE.COMPLETED verified", [
            "capture_id" => $captureId,
            "invoice_id" => $invoiceId,
            "amount"     => $verified["amount"],
        ]);

        return [
            "success"        => true,
            "transaction_id" => $captureId,
            "invoice_id"     => $invoiceId,
            "amount"         => $verified["amount"],
            "gateway"        => "paypal",
        ];
    }

    // =====================================================================
    // STORING A PAYMENT METHOD AND CHARGING IT WITH NOBODY WATCHING
    //
    // PayPal's current mechanism is the Payment Method Tokens API v3, and the
    // one it replaces is worth naming so nobody reaches for it: Billing
    // Agreements are listed as "Deprecated" with the recommendation to migrate
    // (https://developer.paypal.com/api/rest/deprecated-resources/, fetched
    // 2026-09-17), and the vault is what the current documentation describes
    // for saving a PayPal account and charging it later.
    //
    // The shape is two-step and deliberately so. POST /v3/vault/setup-tokens
    // opens a session the payer approves at PayPal; POST /v3/vault/payment-tokens
    // exchanges the approved setup token for a permanent payment token; the
    // token's id is then the vault_id an order is charged against. "Payers
    // don't need to be present when charged."
    // (https://developer.paypal.com/docs/checkout/save-payment-methods/purchase-later/payment-tokens-api/paypal/,
    // fetched 2026-09-17.)
    //
    // A VAULTED PAYPAL ACCOUNT — NOT A CARD — IS WHAT THIS STORES, which is the
    // answer to the question of whether an account can be charged unattended:
    // it can, through payment_source.paypal.vault_id on an ordinary Orders v2
    // order, with no billing agreement anywhere in it.
    // =====================================================================

    /**
     * What this shop tells PayPal about the rhythm of an unattended charge.
     *
     * "UNSCHEDULED_PREPAID: Unscheduled card-on-file plan where merchant can
     * bill payer upfront based on agreed logic."
     * (https://developer.paypal.com/docs/checkout/standard/customize/recurring-payments-module/,
     * fetched 2026-09-17.) That is what AutoChargeService does and nothing
     * narrower is true of it: it charges whatever invoice has come due, which
     * may be a renewal, an addon or a one-off, on no fixed amount and no fixed
     * date.
     *
     * NOT SETTLED BY THE DOCUMENTATION, AND FLAGGED RATHER THAN GUESSED OVER.
     * Two PayPal pages give different short lists of what a merchant-initiated
     * charge may carry here — one says "IMMEDIATE, DEFERRED, and
     * RECURRING_POSTPAID", another the same three with RECURRING_PREPAID — and
     * neither list is the full enum above. A value PayPal refuses fails the
     * whole charge with a 400 and takes no money, which is loud and recoverable
     * in one edit; that is the failure worth risking against declaring an
     * unscheduled plan as something it is not. Confirm against a sandbox
     * account before this is wired to a customer-facing screen.
     */
    private const CHARGE_USAGE_PATTERN = "UNSCHEDULED_PREPAID";

    /** HTTP answers this module names rather than writing as bare numbers. */
    private const REQUEST_IN_PROGRESS = 409;
    private const UNPROCESSABLE_ENTITY = 422;
    private const RATE_LIMITED = 429;

    /**
     * The 422 issues that can only have been written after the funding source
     * was actually presented.
     *
     * "INSTRUMENT_DECLINED: The instrument presented was either declined by the
     * processor or bank", "PAYMENT_SOURCE_DECLINED_BY_PROCESSOR: The provided
     * payment source is declined by the processor", "PAYMENT_DENIED: PayPal has
     * declined to process this transaction"
     * (https://developer.paypal.com/api/rest/reference/orders/v2/errors/,
     * fetched 2026-09-17). Each of them is a verdict on a presentation, which
     * is what makes them the only refusals safe to resolve a replay with.
     */
    private const PROCESSOR_VERDICT_ISSUES = [
        "INSTRUMENT_DECLINED",
        "PAYMENT_SOURCE_DECLINED_BY_PROCESSOR",
        "PAYMENT_DENIED",
    ];

    /**
     * The answers that mean the stored token itself is finished.
     *
     * THIS IS THE DISTINCTION THE CONTRACT ASKS FOR, and PayPal draws it more
     * plainly than a card network does: a decline is one of the issues above,
     * about a presentation; these are about the vault entry, and no number of
     * future attempts will make PayPal find it again. "INVALID_VAULT_ID: The
     * specified Vault ID is invalid or could not be found",
     * "PAYMENT_SOURCE_CANNOT_BE_USED: The provided payment source cannot be
     * used to pay for the order", "MISMATCHED_VAULT_ID_TO_PAYMENT_SOURCE: The
     * vault_id does not match the payment_source provided",
     * "INVALID_RESOURCE_ID: Specified resource ID does not exist" (same page).
     *
     * A method answering one of these is written STATUS_REQUIRES_UPDATE, which
     * is the column's meaning exactly: the customer has to come back and store
     * a payment method again, and telling them their payment "failed" and
     * retrying it every three days would never produce anything else.
     */
    private const TOKEN_IS_FINISHED_ISSUES = [
        "INVALID_VAULT_ID",
        "INVALID_RESOURCE_ID",
        "PAYMENT_SOURCE_CANNOT_BE_USED",
        "MISMATCHED_VAULT_ID_TO_PAYMENT_SOURCE",
    ];

    /**
     * Open a session in which the payer agrees to let this shop store them.
     *
     * NOTHING IS CHARGED. A setup token is an empty session: PayPal answers
     * with a status of PAYER_ACTION_REQUIRED and an approve link, the payer
     * signs in at PayPal and agrees, and only then is there anything to keep.
     *
     * usage_type MERCHANT and customer_type CONSUMER are PayPal's own values
     * for "the merchant manages this stored method" and "the payer is a
     * person", and permit_multiple_payment_tokens false stops one approval
     * minting several tokens for the same account
     * (https://developer.paypal.com/api/payment-tokens/save-without-purchase/paypal,
     * fetched 2026-09-17, whose sample body these fields are taken from).
     *
     * NO PayPal-Request-Id, for the same reason StripeModule's SetupIntent
     * carries none. A setup token is consumed by one payer approval, and a key
     * would have PayPal answer a customer who comes back an hour later with the
     * previous session — quite possibly one already spent or expired — handing
     * them an approve link that can store nothing. Nothing is charged here, so
     * a second unused session costs nothing at all.
     *
     * AND THE SESSION IS WRITTEN DOWN AT THIS END BEFORE IT IS HANDED BACK.
     * That row is what confirmVaulting() checks ownership against; the reason
     * it is a local record rather than something read back out of PayPal is
     * written there.
     *
     * @return array{success: bool, message?: string, client_secret?: string|null, customer_id?: string|null, setup_intent_id?: string|null, redirect_url?: string|null}
     */
    public function beginVaulting(Client $client): array
    {
        try {
            $accessToken = $this->getAccessToken();
        } catch (ConnectionException $e) {
            return ["success" => false, "message" => "PayPal could not be reached: " . $e->getMessage()];
        }

        if (!$accessToken) {
            return ["success" => false, "message" => "PayPal credentials not configured or token request failed."];
        }

        try {
            $response = Http::withToken($accessToken)
                ->post($this->getBaseUrl() . "/v3/vault/setup-tokens", [
                    "payment_source" => [
                        "paypal" => [
                            "usage_type"                     => "MERCHANT",
                            "customer_type"                  => "CONSUMER",
                            "permit_multiple_payment_tokens" => false,
                            "usage_pattern"                  => "IMMEDIATE",
                            "experience_context"             => [
                                "brand_name"                => config("app.name", "PNLCS"),
                                "payment_method_preference" => "IMMEDIATE_PAYMENT_REQUIRED",
                                "return_url"                => url("/client/payment-methods?paypal=return"),
                                "cancel_url"                => url("/client/payment-methods?paypal=cancelled"),
                            ],
                        ],
                    ],
                ]);
        } catch (ConnectionException $e) {
            return ["success" => false, "message" => "PayPal could not be reached: " . $e->getMessage()];
        }

        if (!$response->successful()) {
            Log::error("PayPal: create setup token failed", [
                "client" => $client->id,
                "status" => $response->status(),
                "issue"  => $this->issueName($response),
            ]);

            return ["success" => false, "message" => "PayPal error: " . $this->errorMessage($response)];
        }

        $setupToken = (string) ($response->json("id") ?? "");

        if ($setupToken === "") {
            return ["success" => false, "message" => "PayPal returned a card setup with no id."];
        }

        // Claimed before the id is handed to a browser, and the session is not
        // handed back at all if the claim did not stick. remember() answers
        // false only when that id is already written against somebody else,
        // which for a freshly minted PayPal id should be impossible — and an
        // impossible thing happening is exactly when a session must not be
        // opened, because confirmVaulting() would refuse it anyway and the
        // customer would be left at a PayPal screen that leads nowhere.
        if (!GatewayVaultSession::remember("paypal", (int) $client->id, $setupToken)) {
            Log::error("PayPal: a vault session came back under an id that belongs to another client", [
                "client"  => $client->id,
                "session" => $setupToken,
            ]);

            return ["success" => false, "message" => "That payment method setup could not be started."];
        }

        return [
            "success"         => true,
            // PayPal's flow finishes at PayPal, not in this page's JavaScript,
            // so there is no browser secret to hand over. The approve link is
            // what a screen would need, and it is handed back under its own
            // name rather than pretending to be one.
            "client_secret"   => null,
            "customer_id"     => GatewayCustomer::idFor("paypal", (int) $client->id),
            "setup_intent_id" => $setupToken,
            "redirect_url"    => $this->approveLink($response->json("links")),
        ];
    }

    /**
     * The payer says they have agreed at PayPal. Ask PayPal, then store it.
     *
     * NOTHING FROM THE BROWSER IS TRUSTED EXCEPT AS A QUESTION, which is the
     * contract's requirement and this method's whole shape: the id is used to
     * read the setup token back from PayPal and to exchange it, and everything
     * written comes out of PayPal's answers.
     *
     * OWNERSHIP IS PROVED FROM OUR OWN RECORD, NOT FROM PAYPAL'S. StripeModule
     * can ask Stripe whose intent this is because it wrote metadata[client_id]
     * onto it. PayPal's vault has an optional customer.merchant_customer_id
     * that could carry the same fact, but where it belongs in the setup-token
     * request body is not shown in any sample this was written against, and a
     * field in the wrong place is either ignored or rejected — in the first
     * case silently leaving every stored method unowned. So the binding is
     * local: beginVaulting() writes (gateway, client, setup token) down before
     * the id ever reaches a browser, and a confirmation for a session this
     * client did not open is refused before anything is written or exchanged.
     * That is strictly stronger than trusting a field echoed back by a third
     * party, and it cannot be defeated by guessing a PayPal id.
     *
     * THE EXCHANGE CARRIES A KEY, AND THIS IS THE ONE PLACE A DERIVED ONE IS
     * DEFENSIBLE. Its only request-shaped input is the setup token id — minted
     * by PayPal, written down at both ends, and immutable — so two attempts at
     * the same exchange present the same name and produce one payment token
     * rather than two tokens for one agreement. The derivation is still keyed
     * on config('app.key'), so it is no more stable than that key is; what
     * makes that survivable here and not on a charge is the clock. This
     * exchange happens inside one browser request, seconds after the payer
     * approved, and the worst a key rotated in that window can cost is a second
     * payment token for an agreement the payer did give — untidy, chargeable,
     * and detachable. A charge under a drifted name costs a second debit, which
     * is why the charge path refuses to derive one on a replay at all.
     *
     * @return array{success: bool, message?: string, client_id?: int|null, customer_id?: string|null}
     */
    public function confirmVaulting(Client $client, string $sessionId): array
    {
        $sessionId = trim($sessionId);

        if ($sessionId === "") {
            return ["success" => false, "message" => "No card setup was named."];
        }

        // Refused before PayPal is asked anything at all. A session this client
        // did not open is not theirs to finish, and checking afterwards would
        // mean checking after the payment method had been stored against them.
        if (!GatewayVaultSession::wasOpenedBy("paypal", (int) $client->id, $sessionId)) {
            Log::warning("PayPal: refused to finish a vault session opened for another client", [
                "client"  => $client->id,
                "session" => $sessionId,
            ]);

            return ["success" => false, "message" => "That payment method setup does not belong to this account."];
        }

        try {
            $accessToken = $this->getAccessToken();
        } catch (ConnectionException $e) {
            return ["success" => false, "message" => "PayPal could not be reached."];
        }

        if (!$accessToken) {
            return ["success" => false, "message" => "PayPal credentials not configured."];
        }

        try {
            $setup = Http::withToken($accessToken)
                ->get($this->getBaseUrl() . "/v3/vault/setup-tokens/" . urlencode($sessionId));
        } catch (ConnectionException $e) {
            // Not an error the customer caused and not one they can fix.
            return ["success" => false, "message" => "PayPal could not be reached."];
        }

        if (!$setup->successful()) {
            Log::warning("PayPal: vault session lookup failed", [
                "client"  => $client->id,
                "session" => $sessionId,
                "status"  => $setup->status(),
            ]);

            return ["success" => false, "message" => "PayPal did not recognise that payment method setup."];
        }

        // A session the payer has not finished yet. PayPal answers a freshly
        // created setup token with PAYER_ACTION_REQUIRED and an approve link
        // (https://developer.paypal.com/docs/checkout/save-payment-methods/purchase-later/payment-tokens-api/paypal/,
        // fetched 2026-09-17), and CREATED is the status before even that. The
        // statuses that mean "not finished" are refused by name rather than one
        // approved status being allow-listed, because the exchange below is the
        // real gate — PayPal will not turn an unapproved setup token into a
        // payment token — and a guessed spelling of the approved status would
        // refuse every genuine one.
        $status = strtoupper((string) $setup->json("status"));

        if (in_array($status, ["CREATED", "PAYER_ACTION_REQUIRED"], true)) {
            return [
                "success" => false,
                "message" => "That payment method has not finished being stored (status: " . ($status ?: "unknown") . ").",
            ];
        }

        try {
            $exchange = Http::withToken($accessToken)
                ->withHeaders(["PayPal-Request-Id" => $this->requestId("vault-exchange", [$sessionId])])
                ->post($this->getBaseUrl() . "/v3/vault/payment-tokens", [
                    "payment_source" => [
                        "token" => ["id" => $sessionId, "type" => "SETUP_TOKEN"],
                    ],
                ]);
        } catch (ConnectionException $e) {
            return ["success" => false, "message" => "PayPal could not be reached."];
        }

        if (!$exchange->successful()) {
            Log::warning("PayPal: vault session could not be exchanged for a payment token", [
                "client"  => $client->id,
                "session" => $sessionId,
                "status"  => $exchange->status(),
                "issue"   => $this->issueName($exchange),
            ]);

            return ["success" => false, "message" => "PayPal error: " . $this->errorMessage($exchange)];
        }

        $token = (string) ($exchange->json("id") ?? "");

        if ($token === "") {
            return ["success" => false, "message" => "PayPal returned a stored payment method with no id."];
        }

        return $this->storeVaultedMethod($client, $token, (array) $exchange->json());
    }

    /**
     * Stop PayPal holding this payer's account for us.
     *
     * "DELETE /v3/vault/payment-tokens/{id} — Delete payment token"
     * (https://developer.paypal.com/api/payment-tokens/v3/, fetched
     * 2026-09-17). Called from the scheduled sweep and never from a customer's
     * own request, so it may take as long as it takes, and it must be safe to
     * call twice.
     *
     * NO PayPal-Request-Id, and none is wanted — the same reasoning as
     * StripeModule's detach. A DELETE is naturally idempotent: the second one
     * is answered with "the resource does not exist", which is the result being
     * asked for. A key would replay the first answer instead of telling the
     * sweep what is true now.
     *
     * "THE GATEWAY HAS NEVER HEARD OF IT" IS A SUCCESS. There is nothing left
     * to detach, so the row is marked done and stops being asked about;
     * retryable is what decides that, and it is false only where the answer
     * cannot change.
     *
     * @return array{success: bool, message?: string, retryable?: bool}
     */
    public function detachStoredMethod(PaymentMethod $method): array
    {
        if (strtolower((string) $method->gateway_name) !== "paypal") {
            return ["success" => false, "message" => "That payment method was not stored with PayPal.", "retryable" => false];
        }

        $token = $method->remote_token;

        if (!is_string($token) || $token === "") {
            return ["success" => true, "message" => "No stored token to detach."];
        }

        try {
            $accessToken = $this->getAccessToken();
        } catch (ConnectionException $e) {
            return ["success" => false, "message" => "PayPal could not be reached: " . $e->getMessage(), "retryable" => true];
        }

        if (!$accessToken) {
            // The operator will put the credentials back. Until then PayPal is
            // still holding the payer's account and saying otherwise would be a
            // lie in the table.
            return ["success" => false, "message" => "PayPal credentials not configured.", "retryable" => true];
        }

        try {
            $response = Http::withToken($accessToken)
                ->delete($this->getBaseUrl() . "/v3/vault/payment-tokens/" . urlencode($token));
        } catch (ConnectionException $e) {
            return ["success" => false, "message" => "PayPal could not be reached: " . $e->getMessage(), "retryable" => true];
        }

        if ($response->successful()) {
            return ["success" => true, "message" => "Detached at PayPal."];
        }

        // Gone already. "INVALID_RESOURCE_ID: Specified resource ID does not
        // exist" is PayPal's 404 for a token it is not holding
        // (https://developer.paypal.com/api/rest/reference/orders/v2/errors/),
        // and a token PayPal is not holding is the whole of what was asked for.
        if ($response->status() === 404) {
            Log::info("PayPal: stored payment method was already gone", [
                "method" => $method->id,
                "issue"  => $this->issueName($response),
            ]);

            return ["success" => true, "message" => "PayPal is no longer holding that payment method."];
        }

        // Credentials rejected, PayPal having a bad minute, or being asked to
        // slow down: all worth asking again. A refusal PayPal means (403, 422)
        // will still be refused tomorrow, and a person has to look.
        $retryable = $response->status() >= 500
            || $response->status() === 401
            || $response->status() === self::RATE_LIMITED;

        return [
            "success"   => false,
            "message"   => "PayPal error: " . $this->errorMessage($response),
            "retryable" => $retryable,
        ];
    }

    /**
     * Charge a payment method the payer stored earlier, with nobody watching.
     *
     * HOW A MERCHANT-INITIATED CHARGE IS DECLARED, which is the thing that must
     * not be left out. The order carries the vault token and, beside it, the
     * stored_credential block PayPal publishes for exactly this:
     *
     *     "payment_source": { "paypal": {
     *         "vault_id": "PAYMENT-TOKEN-ID",
     *         "stored_credential": {
     *             "payment_initiator": "MERCHANT",
     *             "usage": "SUBSEQUENT",
     *             "usage_pattern": "RECURRING_POSTPAID" } } }
     *
     * (https://developer.paypal.com/docs/checkout/standard/customize/save-payment-methods-for-recurring-payments/,
     * fetched 2026-09-17.) payment_initiator MERCHANT is "The merchant
     * initiates the transaction and has established consent to charge the
     * buyer" and usage SUBSEQUENT is "Previously saved card information or
     * token is used in the transaction"
     * (https://developer.paypal.com/docs/checkout/advanced/customize/sca-payment-indicators,
     * fetched 2026-09-17), which is precisely what this is. That page also says
     * why omitting them is not an option: "pass these payment indicators to
     * avoid rejected transactions." The usage_pattern this shop declares, and
     * what is unsettled about it, is written at CHARGE_USAGE_PATTERN.
     *
     * THE HEADER IS NOT OPTIONAL ON THIS CALL. PayPal raises
     * PAYPAL_REQUEST_ID_REQUIRED — "A PayPal-Request-Id is required if you are
     * trying to process payment for an Order. Please specify a
     * PayPal-Request-Id or Create the Order without a 'payment_source'
     * specified." (https://developer.paypal.com/api/rest/reference/orders/v2/errors/,
     * fetched 2026-09-17) — so an order carrying a payment_source and no header
     * is a 400 and no charge at all. The name it goes out under is the
     * caller's, always, when the caller has one: written down with the attempt
     * row before the first POST and handed back unchanged on every repeat,
     * which is the only way a repeat can be proved to be a repeat. requestId()
     * is the fallback for a caller with no memory, and the hazard it carries is
     * written there.
     *
     * Prefer: return=representation is sent because without it there is nothing
     * to settle the invoice with: "The server returns a minimal response to
     * optimize communication between the API caller and the server. A minimal
     * response includes the id, status and HATEOAS links."
     * (https://developer.paypal.com/api/orders/v2/orders-create, fetched
     * 2026-09-17.) The capture id lives in the full representation, and the
     * capture id is what the webhook and PaymentService dedupe a payment on.
     *
     * Nothing is asked of PayPal until the row itself has been read: a method
     * the customer removed and a method already known to need their attention
     * are both refused here, for the reasons the guards state.
     *
     * $params["replay"] IS THE ONE FACT THIS MODULE CANNOT WORK OUT FOR ITSELF.
     * This class is stateless; the caller knows whether the identical order,
     * under the identical name, went out fifteen minutes ago and was never
     * heard about. outcomeIsIndeterminate() is what it changes.
     *
     * ⚠ HOW LONG PAYPAL REMEMBERS A REQUEST ID ON THIS ENDPOINT IS NOT
     * DOCUMENTED, AND THE REPLAY MACHINERY ASSUMES IT IS. READ THIS BEFORE
     * WIRING THE VAULT TO A CUSTOMER-FACING SCREEN.
     *
     * PayPal is explicit that retention is a per-API fact and refuses to state
     * one centrally: "Not all APIs support this header. To determine whether
     * your API supports it and for information about how long the server stores
     * the ID, see the reference for your API."
     * (https://developer.paypal.com/api/rest/reference/idempotency/, fetched
     * 2026-09-17.) The reference for Orders v2 then does not say. Every
     * statement of the header on the create-order and capture-order pages is
     * "A unique ID identifying the request header for idempotency purposes."
     * and nothing more (https://developer.paypal.com/api/orders/v2/orders-create,
     * fetched 2026-09-17). The only retention figure PayPal publishes anywhere
     * that could be found is for a different endpoint — refund captured
     * payment, "for up to 45 days"
     * (https://developer.paypal.com/api/rest/requests/, fetched 2026-09-17) —
     * and it cannot be carried across.
     *
     * WHY THAT MATTERS HERE RATHER THAN BEING A CURIOSITY. AutoChargeService
     * recovers a charge whose outcome was never heard by REPLAYING it under the
     * same name, inside a window it believes the gateway still holds that name
     * for. That window is InvoiceChargeAttempt::REPLAY_WINDOW_SECONDS — twenty
     * three hours, one constant shared by every gateway, chosen from Stripe's
     * documented "at least 24 hours". If PayPal's window on this endpoint is
     * shorter than twenty-three hours, a replay sent after it closes is not a
     * replay at all: PayPal has no saved result to answer from, so it creates a
     * second order and takes the money again. That is the exact failure this
     * whole class of work exists to prevent, and no test in this repository can
     * detect it, because it depends on a number PayPal has not published.
     *
     * WHAT MAKES IT SURVIVABLE TODAY, AND WHAT MUST HAPPEN BEFORE IT IS NOT.
     * Nothing reaches this method: no screen stores a PayPal method, so no
     * client has one, so the charger never selects one. Before that changes,
     * somebody has to establish PayPal's retention for POST /v2/checkout/orders
     * — from PayPal directly if the documentation still will not say — and
     * either confirm it exceeds the replay window or give the window a
     * per-gateway value that is shorter than it. Until one of those is done,
     * the honest position is that PayPal's crash recovery is unproven.
     */
    public function chargeStoredMethod(Invoice $invoice, PaymentMethod $method, float $amount, array $params = []): array
    {
        $isReplay = ($params["replay"] ?? false) === true;

        // The method belongs to a client; the invoice belongs to a client. If
        // those are not the same client somebody has passed the wrong row, and
        // the cost of finding out from the payer is far higher than the cost of
        // this comparison.
        if ((int) $method->client_id !== (int) $invoice->client_id) {
            Log::warning("PayPal: refused to charge a stored method belonging to another client", [
                "invoice"        => $invoice->id,
                "invoice_client" => $invoice->client_id,
                "method"         => $method->id,
                "method_client"  => $method->client_id,
            ]);

            return $this->nothingWasSent($isReplay, "Stored payment method does not belong to this invoice's client.");
        }

        if (strtolower((string) $method->gateway_name) !== "paypal") {
            return $this->nothingWasSent($isReplay, "Stored payment method was not stored with PayPal.");
        }

        // A method in the bin is one the customer has told us to stop using.
        // Removing it at this end does not delete the token at PayPal, so the
        // token in this row very probably still works — which is exactly why
        // this has to refuse rather than rely on PayPal to.
        if ($method->trashed()) {
            Log::warning("PayPal: refused to charge a stored method the customer had removed", [
                "invoice" => $invoice->id,
                "method"  => $method->id,
            ]);

            return $this->nothingWasSent($isReplay, "Stored payment method has been removed.");
        }

        if ($method->status !== PaymentMethod::STATUS_ACTIVE) {
            Log::warning("PayPal: refused to charge a stored method that is not active", [
                "invoice" => $invoice->id,
                "method"  => $method->id,
                "status"  => $method->status,
            ]);

            return $this->nothingWasSent($isReplay, "Stored payment method needs the customer to store it again.");
        }

        // ONLY A ROW THIS MODULE'S OWN VAULT WROTE, and the reason is a row it
        // did not write. payment_methods has carried gateway_name 'paypal'
        // since long before any of this existed — an import from another
        // billing system, a hand-made record of how a customer pays — and none
        // of those rows is a vault token. Implementing the capability at all
        // puts every one of them inside AutoChargeService's candidate query,
        // because that query selects on gateway_name and nothing else. They
        // cannot take money (PayPal has no such token) but they can put an
        // invoice into dunning and tell a customer their payment failed on
        // something that was never a payment method. This is the narrowest
        // description of what the vault below actually writes.
        if (strtolower((string) $method->payment_type) !== "paypal") {
            Log::warning("PayPal: refused to charge a stored method that did not come from the PayPal vault", [
                "invoice"      => $invoice->id,
                "method"       => $method->id,
                "payment_type" => $method->payment_type,
            ]);

            return $this->nothingWasSent($isReplay, "Stored payment method was not stored by the PayPal vault.");
        }

        $vaultId = $method->remote_token;

        if (!is_string($vaultId) || $vaultId === "") {
            return $this->nothingWasSent($isReplay, "Stored payment method is missing its PayPal vault token.");
        }

        $currency = $this->currencyCode($params["currency"] ?? shop_currency_code());
        $value    = $this->amountValue($amount, $currency);

        if ($this->amountFromValue($value) <= 0) {
            return $this->nothingWasSent($isReplay, "Nothing left to charge on this invoice.");
        }

        $suppliedKey = $params["idempotency_key"] ?? null;
        $suppliedKey = is_string($suppliedKey) && trim($suppliedKey) !== "" ? trim($suppliedKey) : null;

        // ON A REPLAY THERE IS NO FALLBACK. A caller that says "you have
        // already sent this" and cannot say what it was sent as is asking for a
        // charge nobody can prove is a repeat, and a derived name is exactly
        // the thing that cannot be trusted to be the one the first order
        // carried. Send nothing: the row stays in flight, the bounded rescue
        // machinery asks a few more times and then fetches a person, and no
        // payer is charged on a guess.
        if ($isReplay && $suppliedKey === null) {
            return $this->nothingWasSent(
                $isReplay,
                "This end cannot name the PayPal-Request-Id the charge in flight was sent under."
            );
        }

        // THE TOKEN GRANT IS NOT THE CHARGE, and the difference decides what
        // may be said about the money. A connection that drops while fetching a
        // bearer token means the order was never built, let alone sent; on a
        // first send that is a plain retryable failure. A connection that drops
        // while POSTing the order is the opposite, and is handled below.
        try {
            $accessToken = $this->getAccessToken();
        } catch (ConnectionException $e) {
            return $this->nothingWasSent($isReplay, "PayPal could not be reached: " . $e->getMessage(), true);
        }

        if (!$accessToken) {
            // Retryable: nothing was asked of the payer's account, so nothing
            // has been learned about it. Once the credentials work this same
            // attempt works, and refusing to try again would strand the invoice.
            return $this->nothingWasSent($isReplay, "PayPal credentials not configured or token request failed.", true);
        }

        $requestId = $suppliedKey ?? $this->requestId("offsession", [$invoice->id, $method->id, $value, $currency]);

        // Both of these are the same fact in two shapes: what came back, and
        // why nothing did. They are held side by side rather than answered in
        // two separate branches because the question that matters next — was
        // the payer charged? — is one question, and it is asked once, below.
        $response          = null;
        $connectionFailure = null;

        try {
            $response = Http::withToken($accessToken)
                ->withHeaders([
                    "PayPal-Request-Id" => $requestId,
                    "Prefer"            => "return=representation",
                ])
                ->post($this->getBaseUrl() . "/v2/checkout/orders", [
                    "intent"         => "CAPTURE",
                    "purchase_units" => [[
                        "reference_id" => "INV-" . $invoice->id,
                        "description"  => "Invoice #" . ($invoice->invoice_num ?? $invoice->id),
                        "amount"       => [
                            "currency_code" => $currency,
                            "value"         => $value,
                        ],
                    ]],
                    "payment_source" => [
                        "paypal" => [
                            "vault_id"          => $vaultId,
                            "stored_credential" => [
                                "payment_initiator" => "MERCHANT",
                                "usage"             => "SUBSEQUENT",
                                "usage_pattern"     => self::CHARGE_USAGE_PATTERN,
                            ],
                        ],
                    ],
                ]);
        } catch (ConnectionException $e) {
            // Nothing is decided here. A dropped connection is one of the
            // shapes of "we do not know", not a second kind of answer.
            $connectionFailure = $e->getMessage();
        }

        $issue = $response === null ? null : $this->issueName($response);

        // =====================================================================
        // THE ONE QUESTION: DO WE KNOW WHETHER THE PAYER WAS CHARGED?
        //
        // Asked once, of one predicate, before anything else about this
        // response is read — before the body, before the issue names, before
        // the stored method is written to and before a retry is scheduled.
        // Every branch below it may assume the answer is yes.
        // =====================================================================
        if ($this->outcomeIsIndeterminate($response?->status(), $isReplay, $issue)) {
            // AT ERROR LEVEL FOR EVERY SHAPE. This is the only outcome this
            // module produces that can leave a customer's money somewhere
            // nothing has written down.
            Log::error("PayPal: an off-session charge was sent and no answer came back that says what became of it", [
                "invoice" => $invoice->id,
                "method"  => $method->id,
                "status"  => $response?->status(),
                "issue"   => $issue,
                "error"   => $connectionFailure ?? $this->errorMessage($response),
                // WHICH QUESTION WAS BEING ASKED. A 429 or a 401 reads as a
                // plain refusal until you know this was a repeat, at which
                // point it reads as what it is: an answer that never looked at
                // the charge we are asking about.
                "replay"  => $isReplay,
                // PayPal's own correlation id, which is the first thing their
                // support will ask for about a charge nobody can account for.
                "debug_id" => $response?->json("debug_id"),
            ]);

            return $this->outcomeUnknown($connectionFailure !== null
                ? "PayPal could not be reached: " . $connectionFailure
                : "PayPal answered " . $response->status() . " and did not say whether the charge went through.");
        }

        if ($response->successful()) {
            return $this->readOrder($invoice, (array) $response->json(), $currency);
        }

        Log::warning("PayPal: off-session charge refused", [
            "invoice"  => $invoice->id,
            "method"   => $method->id,
            "status"   => $response->status(),
            "issue"    => $issue,
            "debug_id" => $response->json("debug_id"),
        ]);

        // PayPal wants the payer back. "PAYER_ACTION_REQUIRED: Transaction
        // cannot complete successfully, instruct the buyer to return to PayPal"
        // (https://developer.paypal.com/api/rest/reference/orders/v2/errors/,
        // fetched 2026-09-17) — which is not a decline: nothing has been taken
        // and the stored method is fine. Telling a customer their payment
        // failed when PayPal only wanted a word with them is how a renewal
        // turns into a cancellation. No transaction id is handed back, because
        // an error body names no order this end could send them to; the
        // contract's requires_action is still the honest status.
        if ($issue === "PAYER_ACTION_REQUIRED") {
            return [
                "success"        => false,
                "status"         => "requires_action",
                "message"        => "PayPal needs the payer to return and approve this payment.",
                "transaction_id" => null,
                "decline_code"   => $issue,
                // Nothing an unattended retry can do: the same refusal comes
                // back asking for the same person.
                "retryable"      => false,
            ];
        }

        // The vault entry itself is finished, which is a different fact from a
        // decline and is written on the method rather than on the invoice.
        if (in_array($issue, self::TOKEN_IS_FINISHED_ISSUES, true)) {
            $method->update(["status" => PaymentMethod::STATUS_REQUIRES_UPDATE]);

            Log::info("PayPal: stored payment method marked as needing the customer's attention", [
                "method" => $method->id,
                "issue"  => $issue,
            ]);

            return $this->chargeFailed(
                "PayPal error: " . $this->errorMessage($response),
                $issue,
                false
            );
        }

        return $this->chargeFailed(
            "PayPal error: " . $this->errorMessage($response),
            $issue,
            $this->refusalIsRetryable($response->status(), $issue)
        );
    }

    /**
     * What an order PayPal accepted actually says about the money.
     *
     * COMPLETED IS NOT ENOUGH ON ITS OWN. The order status says PayPal finished
     * with the request; the capture inside it says what happened to the money,
     * and the two are separate fields for a reason. A capture that is PENDING
     * is money neither taken nor refused, and crediting the invoice on it would
     * be crediting it on a maybe.
     *
     * AND A COMPLETED ORDER WITH NO CAPTURE ID IS AN UNKNOWN OUTCOME, NOT A
     * SUCCESS. The capture id is the only handle anything has on this payment:
     * PaymentService dedupes on (gateway, transaction id), and the PayPal
     * webhook credits the same invoice under the same capture id. Reporting
     * success with a null id would credit the invoice with nothing to dedupe
     * against, and the webhook arriving a second later would credit it again —
     * the customer's one payment recorded twice. So a nameless success is
     * handed to the machinery that owns unknown outcomes instead.
     */
    private function readOrder(Invoice $invoice, array $order, string $currency): array
    {
        $status = strtoupper((string) ($order["status"] ?? ""));

        if ($status === "PAYER_ACTION_REQUIRED") {
            return [
                "success"        => false,
                "status"         => "requires_action",
                "message"        => "PayPal needs the payer to return and approve this payment.",
                // The order does exist and is waiting, so it can be named.
                "transaction_id" => is_string($order["id"] ?? null) ? $order["id"] : null,
                "decline_code"   => "PAYER_ACTION_REQUIRED",
                "retryable"      => false,
            ];
        }

        if ($status !== "COMPLETED") {
            // CREATED, APPROVED, SAVED, VOIDED, or something PayPal has not
            // invented yet: PayPal has the order but has not said the money is
            // there. Not a success, and not a failure either — a retryable
            // failure is scheduled days out, by which time the request id may
            // no longer be held and the retry is a brand new charge for a
            // payment that was very likely completing while we called it
            // failed. No order id is returned, deliberately: on an in-flight
            // row last_transaction_id means "the gateway has answered and the
            // ledger owes an entry", and the rescue sweep credits such a row
            // from our own records without asking anybody.
            Log::info("PayPal: off-session charge is not finished", [
                "invoice" => $invoice->id,
                "order"   => $order["id"] ?? null,
                "status"  => $status,
            ]);

            return $this->outcomeUnknown("PayPal returned an unfinished order (status: " . ($status ?: "unknown") . ").");
        }

        $capture = $order["purchase_units"][0]["payments"]["captures"][0] ?? null;

        if (!is_array($capture) || !is_string($capture["id"] ?? null) || $capture["id"] === "") {
            Log::error("PayPal: a completed order named no capture, so the payment cannot be settled safely", [
                "invoice" => $invoice->id,
                "order"   => $order["id"] ?? null,
            ]);

            return $this->outcomeUnknown("PayPal completed the order but named no capture to record it under.");
        }

        $captureStatus = strtoupper((string) ($capture["status"] ?? ""));

        if ($captureStatus === "DECLINED" || $captureStatus === "FAILED") {
            return $this->chargeFailed("PayPal declined the capture on this order.", $captureStatus, false);
        }

        if ($captureStatus !== "COMPLETED") {
            // PENDING above all. The money is neither taken nor refused.
            return $this->outcomeUnknown("PayPal has the payment but has not said the money is there (capture status: " . ($captureStatus ?: "unknown") . ").");
        }

        return [
            "success"        => true,
            "status"         => "succeeded",
            "transaction_id" => $capture["id"],
            // Read out of PayPal's own answer rather than out of what was
            // asked for, and through the same conversion it went out under, so
            // that what is reported is what was taken.
            "amount"         => $this->amountFromValue($capture["amount"]["value"] ?? $this->amountValue(0.0, $currency)),
        ];
    }

    /**
     * DOES THIS ANSWER LEAVE IT UNKNOWN WHETHER THE PAYER WAS CHARGED?
     *
     * The single place that question is decided in this module. "We do not
     * know" is one concept, every path has to mean the same thing by it, and
     * every path that answers yes has to end up in the same machinery.
     *
     * NULL IS NO ANSWER AT ALL — a dropped connection, a read timeout. The
     * order may have reached PayPal and PayPal may have reached the payer's
     * funding source; nothing this end can see which.
     *
     * 5xx IS INDETERMINATE. PayPal's own worked example of what the request id
     * is for is a refund where "the initial call fails with the HTTP 500 status
     * code but the server has already refunded the payment"
     * (https://developer.paypal.com/api/rest/requests/, fetched 2026-09-17) —
     * which is PayPal saying, in as many words, that a 500 can sit on top of an
     * operation that completed. A charge is the same shape with the money
     * moving the other way.
     *
     * 409 IS INDETERMINATE ON EVERY SEND, NOT ONLY ON A REPLAY, and this one is
     * PayPal-specific. "When you send two simultaneous API requests with same
     * PayPal-Request-Id header, PayPal processes the first request and might
     * fail the second request"
     * (https://developer.paypal.com/reference/guidelines/idempotency/), and
     * PREVIOUS_REQUEST_IN_PROGRESS is the 409 that says so: "A previous request
     * on this resource is currently in progress"
     * (https://developer.paypal.com/api/rest/reference/orders/v2/errors/). A
     * request of ours is running right now and may be taking the money. There
     * is no reading of that which licenses a decline or a fresh attempt.
     *
     * 4xx OTHERWISE, ON A FIRST SEND, IS NOT INDETERMINATE. A 422 is the
     * processor's or PayPal's verdict on this order, a 400 is our own request
     * being wrong, a 401 never reached the account — in every one of them
     * PayPal has told us what happened to this charge, which on a first send is
     * the only charge there is.
     *
     * ON A REPLAY THE LIST IS INVERTED, AND DELIBERATELY SO. Asking which
     * statuses PayPal produces before consulting its record of the first
     * request means keeping a list of PayPal's pre-execution failures and being
     * wrong the day they add one. The question is asked from the other end
     * instead: which answers can ONLY have come from the record of the original
     * request? Two kinds, and answersForTheOriginalRequest() holds them.
     * Everything else — a rate limit, an expired token, a 404, something PayPal
     * has not invented yet — is treated as silence about the original, and
     * silence is what the in-flight machinery is for. It costs a replay, and
     * past the cap or the deadline it costs a person's attention; it cannot
     * cost a second debit.
     */
    private function outcomeIsIndeterminate(?int $status, bool $isReplay, ?string $issue): bool
    {
        if ($status === null) {
            return true;
        }

        if ($status >= 500 || $status === self::REQUEST_IN_PROGRESS) {
            return true;
        }

        // A first send: the answer is about the only request there is.
        if (!$isReplay) {
            return false;
        }

        return !$this->answersForTheOriginalRequest($status, $issue);
    }

    /**
     * Could this answer only have come from PayPal's record of the request we
     * are asking about?
     *
     * An allow-list, and small on purpose. Its job is to be wrong in the
     * direction of a person rather than in the direction of a payer's money: a
     * status left out of it that really was the saved result costs one review,
     * and a status wrongly let in costs a customer a second debit.
     *
     * 2xx is the order itself — completed, waiting for the payer, or still
     * processing, which readOrder() turns back into an unknown outcome on its
     * own evidence. "When you include a previously specified PayPal-Request-Id
     * header in a request, PayPal returns the latest status of the previous
     * request that used that same header"
     * (https://developer.paypal.com/reference/guidelines/idempotency/, fetched
     * 2026-09-17), so an order arriving on a replay is the first request's own
     * order read back.
     *
     * A 422 IS ONLY ADMITTED FOR THE THREE ISSUES THAT DESCRIBE A PRESENTATION.
     * Those can only have been written after the funding source was put to the
     * processor, so on a replay they are the first order's verdict being read
     * back, and admitting them is what stops crashed attempts that genuinely
     * declined from being parked for a human for ever. The rest of the 422
     * vocabulary — AMOUNT_MISMATCH, INVALID_CURRENCY_CODE, the vault errors —
     * is validation PayPal can produce without ever looking at the saved
     * record, so on a replay it says nothing about the charge in flight.
     */
    private function answersForTheOriginalRequest(int $status, ?string $issue): bool
    {
        if ($status >= 200 && $status < 300) {
            return true;
        }

        return $status === self::UNPROCESSABLE_ENTITY
            && in_array($issue, self::PROCESSOR_VERDICT_ISSUES, true);
    }

    /**
     * Is another unattended attempt worth making?
     *
     * NOBODY MAY ASK THIS ABOUT AN OUTCOME THAT IS NOT KNOWN. "Is another
     * attempt worth making?" presupposes that this attempt is over and took no
     * money; the charge path settles that before this is reached, so this is
     * belt rather than braces — and false is the safe reading for a caller that
     * forgets, because it stops the dunning cycle rather than authorising a
     * debit.
     *
     * Credentials rejected (401) and being asked to slow down (429) both mean
     * the order never reached a funding source, so the next attempt is a first
     * attempt. INSTRUMENT_DECLINED is the one 422 worth repeating: PayPal
     * describes it as a funding source failing because "the transaction exceeds
     * the card limit" among other reasons
     * (https://developer.paypal.com/docs/multiparty/checkout/standard/customize/handle-funding-failures/,
     * fetched 2026-09-17), and a limit clears on its own. Everything else —
     * PAYMENT_DENIED, TRANSACTION_REFUSED, a malformed request of ours, a
     * permission PayPal has not granted — will be just as true in three days.
     */
    private function refusalIsRetryable(int $status, ?string $issue): bool
    {
        if ($issue === "INSTRUMENT_DECLINED") {
            return true;
        }

        return $status === 401 || $status === self::RATE_LIMITED;
    }

    /**
     * The contract's failed branch, written in one place.
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
     * again", not "try again tomorrow". No transaction id is carried either:
     * an id on an unfinished payment invites a caller to credit an invoice
     * against money that may never arrive.
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
     * now: no credentials, a method the customer has since removed, a method
     * PayPal has already finished with. On a first send that is the whole
     * truth, and chargeFailed() is the honest report of it.
     *
     * ON A REPLAY THE SAME SENTENCE IS TRUE AND IRRELEVANT. A replay only
     * happens because a charge WAS sent, minutes ago, and nobody heard what
     * became of it; "we declined to send anything this time" says nothing
     * whatever about that. Reported as a failure it closes the question with an
     * answer to a different one, and the closing is not harmless either way
     * round: a refusal carrying retryable => true schedules a fresh charge days
     * out, against a request id PayPal may no longer hold, which is a second
     * real debit; one carrying retryable => false writes the row off as
     * exhausted, where the first charge is never looked at by anybody.
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
     * Write the vaulted payment method down.
     *
     * ONE DEFINITION OF WHAT A STORED PAYPAL ACCOUNT LOOKS LIKE, and one place
     * that creates it, so that a second door onto this — a webhook, an admin
     * action — cannot disagree with this one. updateOrCreate is keyed on the
     * token, so whichever path arrives second writes the same row again.
     *
     * payment_type is 'paypal' and pointedly NOT 'cc'. The monthly expiry alert
     * looks for 'cc' methods with a last_four and an expiry_date and warns
     * their owners; a PayPal account has no expiry and no digits, so describing
     * it as a card would put it in a queue for a warning that can never be
     * written, and would print "Credit Card" on the customer's own list beside
     * something that is not one.
     */
    private function storeVaultedMethod(Client $client, string $token, array $paymentToken): array
    {
        $customerId = $paymentToken["customer"]["id"] ?? null;
        $email      = $paymentToken["payment_source"]["paypal"]["email_address"] ?? null;

        $columns = [
            "payment_type"        => "paypal",
            "gateway_customer_id" => is_string($customerId) ? $customerId : null,
            "status"              => PaymentMethod::STATUS_ACTIVE,
            // What the customer recognises it by. The address is PayPal's own
            // answer about the account that was stored, not anything typed in
            // here, and an account PayPal describes without one is still stored.
            "description"         => is_string($email) && $email !== "" ? "PayPal " . $email : "PayPal",
        ];

        $stored = PaymentMethod::updateOrCreate(
            [
                "client_id"    => (int) $client->id,
                "gateway_name" => "paypal",
                "remote_token" => $token,
            ],
            $columns
        );

        // A stored method nothing points at is one the charger will not use:
        // AutoChargeService refuses to choose between several when none is
        // marked, so without this the first method a client stores leaves them
        // with automatic payment switched on and a collection that stops.
        $stored->becomeDefaultIfClientHasNone();

        if (is_string($customerId) && $customerId !== "") {
            GatewayCustomer::remember("paypal", (int) $client->id, $customerId);
        }

        Log::info("PayPal: payment method stored", [
            "client" => $client->id,
            "method" => $stored->id,
        ]);

        return [
            "success"     => true,
            "message"     => "Stored payment method confirmed.",
            "gateway"     => "paypal",
            "client_id"   => (int) $client->id,
            "customer_id" => is_string($customerId) ? $customerId : null,
        ];
    }

    /**
     * PayPal's machine-readable name for what went wrong.
     *
     * Two shapes, because PayPal uses two. An Orders v2 refusal carries a top
     * level "name" (INVALID_REQUEST, UNPROCESSABLE_ENTITY) with the specific
     * fault in details[0].issue (INSTRUMENT_DECLINED, INVALID_VAULT_ID); the
     * issue is the useful one and is preferred, with the name as the fallback
     * for the refusals that carry no details array at all.
     */
    private function issueName(?\Illuminate\Http\Client\Response $response): ?string
    {
        if ($response === null) {
            return null;
        }

        $issue = $response->json("details.0.issue");

        if (is_string($issue) && $issue !== "") {
            return strtoupper($issue);
        }

        $name = $response->json("name");

        return is_string($name) && $name !== "" ? strtoupper($name) : null;
    }

    /** Whatever PayPal said about it, in the order it is most likely to help. */
    private function errorMessage(?\Illuminate\Http\Client\Response $response): string
    {
        if ($response === null) {
            return "Unknown error";
        }

        foreach (["details.0.description", "message", "error_description", "error"] as $field) {
            $value = $response->json($field);

            if (is_string($value) && $value !== "") {
                return $value;
            }
        }

        return "Unknown error";
    }

    /** The link a payer follows to approve a vault session, if PayPal sent one. */
    private function approveLink(mixed $links): ?string
    {
        foreach ((array) $links as $link) {
            if (is_array($link) && ($link["rel"] ?? null) === "approve" && is_string($link["href"] ?? null)) {
                return $link["href"];
            }
        }

        return null;
    }
}
