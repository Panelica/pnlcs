<?php

namespace Modules\Gateways\Iyzico;

use App\Contracts\GatewayModuleInterface;
use App\Contracts\TokenizableGatewayInterface;
use App\Models\Client;
use App\Models\Currency;
use App\Models\GatewayLog;
use App\Models\GatewaySettings;
use App\Models\Invoice;
use App\Models\GatewayCustomer;
use App\Models\PaymentMethod;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * iyzico - Checkout Form.
 *
 * The card is never collected here: iyzico's own form opens in an iframe and
 * iyzico runs 3D Secure itself. Because the card number never touches this
 * server the PCI obligation stays at SAQ-A; writing our own form would have
 * pulled card data straight through the application.
 *
 * Verification is not left to the browser. When a payment finishes iyzico
 * POSTs nothing but a token to the callback; that token is handed back to
 * iyzico over a request signed with our own keys, and the answer says whether
 * the payment really succeeded, how much was taken and which invoice it
 * belongs to. Same shape as verifyPaymentIntent / verifyCapture in the Stripe
 * and PayPal modules.
 */
class IyzicoModule implements GatewayModuleInterface, TokenizableGatewayInterface
{
    /** The version tag iyzico expects on a signed request. */
    private const AUTH_VERSION = 'IYZWSv2';

    public function getModuleName(): string
    {
        return 'iyzico';
    }

    /**
     * Cards can be stored: when the customer ticks "save my card" iyzico
     * returns a cardUserKey and a cardToken. The number itself stays there.
     */
    public function isTokenised(): bool
    {
        return true;
    }

    public function getConfigFields(): array
    {
        return [
            ['name' => 'api_key', 'label' => 'API key', 'type' => 'text', 'required' => true],
            ['name' => 'secret_key', 'label' => 'Secret key', 'type' => 'password', 'required' => true],
            ['name' => 'sandbox', 'label' => 'Sandbox (test) environment', 'type' => 'yesno', 'default' => '0'],
            [
                'name' => 'installments',
                'label' => 'Instalments offered (comma separated: 1,2,3,6,9)',
                'type' => 'text',
                'default' => '1',
            ],
            [
                'name' => 'payment_group',
                'label' => 'Payment group (PRODUCT unless iyzico says otherwise)',
                'type' => 'select',
                'default' => 'PRODUCT',
                'options' => [
                    'PRODUCT' => 'PRODUCT',
                    'LISTING' => 'LISTING',
                    'SUBSCRIPTION' => 'SUBSCRIPTION',
                    'OTHER' => 'OTHER',
                ],
            ],
            [
                'name' => 'save_cards',
                'label' => 'Let the customer store their card',
                'type' => 'yesno',
                'default' => '1',
            ],
        ];
    }

    private function getSetting(string $key): ?string
    {
        $row = GatewaySettings::where('gateway', 'iyzico')->where('setting', $key)->first();

        return $row?->value;
    }

    private function sandbox(): bool
    {
        return (string) $this->getSetting('sandbox') === '1';
    }

    private function baseUrl(): string
    {
        return $this->sandbox()
            ? 'https://sandbox-api.iyzipay.com'
            : 'https://api.iyzipay.com';
    }

    /**
     * One signed request to iyzico.
     *
     * The signature is an HMAC-SHA256 over a random key, the request path and
     * the body exactly as sent. The body goes into the signature verbatim, so
     * the JSON has to be encoded once and that same string used for both -
     * re-encoding can reorder keys and the signature stops matching.
     *
     * @return array<string, mixed>
     */
    private function request(string $uriPath, array $body): array
    {
        $apiKey = trim((string) $this->getSetting('api_key'));
        $secretKey = trim((string) $this->getSetting('secret_key'));

        if ($apiKey === '' || $secretKey === '') {
            return ['success' => false, 'message' => __('messages.iyzico.not_configured')];
        }

        $payload = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $randomKey = (string) round(microtime(true) * 1000).bin2hex(random_bytes(4));
        $signature = hash_hmac('sha256', $randomKey.$uriPath.$payload, $secretKey);

        $authorization = self::AUTH_VERSION.' '.base64_encode(
            'apiKey:'.$apiKey.'&randomKey:'.$randomKey.'&signature:'.$signature
        );

        try {
            $response = Http::withHeaders([
                'Authorization' => $authorization,
                'x-iyzi-rnd' => $randomKey,
                'Content-Type' => 'application/json',
            ])->withBody($payload, 'application/json')
                ->timeout(30)
                ->post($this->baseUrl().$uriPath);
        } catch (\Throwable $e) {
            Log::error('iyzico: request could not be sent', ['path' => $uriPath, 'error' => $e->getMessage()]);

            return ['success' => false, 'message' => __('messages.iyzico.unreachable')];
        }

        $data = $response->json() ?? [];

        if (($data['status'] ?? null) !== 'success') {
            $message = $data['errorMessage'] ?? __('messages.iyzico.refused');
            Log::warning('iyzico: request refused', [
                'path' => $uriPath,
                'code' => $data['errorCode'] ?? $response->status(),
                'error' => $message,
            ]);

            return ['success' => false, 'message' => $message, 'raw' => $data];
        }

        return ['success' => true] + $data;
    }

    /**
     * What the invoice comes to in lira.
     *
     * An iyzico merchant account settles in TRY. When the shop already prices
     * in TRY the amount passes through untouched; otherwise it is converted
     * with the stored TRY rate. The rate used is returned alongside, because
     * the callback has to turn the amount iyzico actually took back into the
     * shop currency with the same number it started from.
     *
     * @return array{amount: float, rate: float}
     */
    private function tryPrice(float $amount): array
    {
        if (shop_currency_code() === 'TRY') {
            return ['amount' => round($amount, 2), 'rate' => 1.0];
        }

        $rate = (float) (Currency::where('code', 'TRY')->value('rate') ?: 0);

        return [
            'amount' => $rate > 0 ? round($amount * $rate, 2) : round($amount, 2),
            'rate' => $rate > 0 ? $rate : 1.0,
        ];
    }

    /** iyzico wants amounts as text with a dot separator. */
    private function money(float $value): string
    {
        return number_format($value, 2, '.', '');
    }

    /**
     * Who is paying.
     *
     * Read from the invoice's frozen buyer fields first and only then from the
     * live client record, so a customer who moves house afterwards still has a
     * payment recorded against the address the document carries.
     *
     * @return array<string, string>
     */
    private function buyer(Invoice $invoice): array
    {
        $client = $invoice->client;

        $first = trim((string) ($invoice->buyer_first_name ?: $client?->first_name)) ?: 'Customer';
        $last = trim((string) ($invoice->buyer_last_name ?: $client?->last_name)) ?: 'Customer';

        // An identity number is a required field for iyzico. For a company it
        // is the tax number; a private customer in Turkey has a TCKN, which is
        // what the tax id field holds for them. With neither, the filler value
        // iyzico accepts is sent: refusing to take the payment over a missing
        // identity number would lose the customer, and the invoice records who
        // they are either way.
        $identity = trim((string) ($invoice->buyer_tax_id ?: $client?->tax_id));
        if ($identity === '') {
            $identity = '11111111111';
        }

        return [
            'id' => (string) ($invoice->client_id ?: 0),
            'name' => $first,
            'surname' => $last,
            'gsmNumber' => $this->gsm($client?->phone_number, (string) ($client?->country ?: 'TR')),
            'email' => trim((string) ($invoice->buyer_email ?: $client?->email)),
            'identityNumber' => $identity,
            'registrationAddress' => trim((string) ($invoice->buyer_address1 ?: $client?->address1)) ?: '-',
            'ip' => request()?->ip() ?: '127.0.0.1',
            'city' => trim((string) ($invoice->buyer_city ?: $client?->city)) ?: '-',
            'country' => $this->countryName((string) ($invoice->buyer_country ?: $client?->country)),
            'zipCode' => trim((string) ($invoice->buyer_postcode ?: $client?->postcode)),
        ];
    }

    /** @return array<string, string> */
    private function address(Invoice $invoice): array
    {
        $client = $invoice->client;

        $name = trim((string) ($invoice->buyer_company_name
            ?: trim(((string) ($invoice->buyer_first_name ?: $client?->first_name)).' '.((string) ($invoice->buyer_last_name ?: $client?->last_name)))));

        return [
            'contactName' => $name !== '' ? $name : 'Customer',
            'city' => trim((string) ($invoice->buyer_city ?: $client?->city)) ?: '-',
            'country' => $this->countryName((string) ($invoice->buyer_country ?: $client?->country)),
            'address' => trim((string) ($invoice->buyer_address1 ?: $client?->address1)) ?: '-',
            'zipCode' => trim((string) ($invoice->buyer_postcode ?: $client?->postcode)),
        ];
    }

    /**
     * iyzico wants the country spelled out, not as an ISO code.
     *
     * The handful written out here are the spellings iyzico is known to
     * accept; the shared country list is the fallback for everywhere else.
     * "Turkey" rather than the list's "Türkiye" on purpose - that is the form
     * the integration was proved against, and this is not the place to find
     * out whether the other spelling is taken.
     */
    private function countryName(string $code): string
    {
        $code = strtoupper(trim($code));

        return match ($code) {
            '', 'TR' => 'Turkey',
            'DE' => 'Germany',
            'NL' => 'Netherlands',
            'GB' => 'United Kingdom',
            'US' => 'United States',
            default => \App\Support\Countries::all()[$code] ?? $code,
        };
    }

    /**
     * The phone number in the +90... shape iyzico expects.
     *
     * The sign-up form takes a number as "0540..." or "540..." and does not
     * keep the country code in a field of its own.
     */
    private function gsm(?string $phone, string $country): string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);

        if ($digits === '') {
            return '';
        }

        if (strtoupper($country) === 'TR' || $country === '') {
            $digits = ltrim($digits, '0');
            if (! str_starts_with($digits, '90')) {
                $digits = '90'.$digits;
            }
        }

        return '+'.$digits;
    }

    /**
     * Which group iyzico reports the transaction under.
     *
     * PRODUCT is right for an ordinary merchant. On an account registered as a
     * marketplace, PRODUCT asks for a subMerchantKey on every basket line; an
     * operator on such an account picks the group iyzico told them to use here
     * rather than in the code.
     */
    private function paymentGroup(): string
    {
        $group = strtoupper(trim((string) $this->getSetting('payment_group')));

        return in_array($group, ['PRODUCT', 'LISTING', 'SUBSCRIPTION', 'OTHER'], true)
            ? $group
            : 'PRODUCT';
    }

    /**
     * The instalment counts from the settings; a single charge when empty.
     *
     * @return array<int, int>
     */
    private function installments(): array
    {
        $raw = array_filter(array_map(
            fn ($v) => (int) trim($v),
            explode(',', (string) ($this->getSetting('installments') ?: '1'))
        ), fn ($v) => $v >= 1);

        $list = array_values(array_unique($raw));
        sort($list);

        return $list ?: [1];
    }

    /**
     * Open the payment form.
     *
     * The checkoutFormContent that comes back is iyzico's own script and is
     * printed as-is on the interstitial page shown to the customer.
     *
     * @return array<string, mixed>
     */
    public function capture(Invoice $invoice, float $amount, array $params = []): array
    {
        if ($amount <= 0) {
            return ['success' => false, 'message' => __('messages.iyzico.nothing_due')];
        }

        $lira = $this->tryPrice($amount);
        $price = $this->money($lira['amount']);

        // One basket line on purpose: on a partly paid invoice the line items
        // no longer add up to what is still owed, and iyzico insists the
        // basket total equals the price exactly.
        $body = [
            'locale' => app()->getLocale() === 'tr' ? 'tr' : 'en',
            'conversationId' => (string) $invoice->id,
            'price' => $price,
            'paidPrice' => $price,
            'currency' => 'TRY',
            'basketId' => (string) ($invoice->invoice_num ?: ('INV'.$invoice->id)),
            'paymentGroup' => $this->paymentGroup(),
            'callbackUrl' => route('gateway.iyzico.callback'),
            'enabledInstallments' => $this->installments(),
            'buyer' => $this->buyer($invoice),
            'shippingAddress' => $this->address($invoice),
            'billingAddress' => $this->address($invoice),
            'basketItems' => [[
                'id' => (string) $invoice->id,
                'name' => mb_substr((string) ($invoice->invoice_num ?: ('#'.$invoice->id)), 0, 100),
                'category1' => 'Hosting',
                'itemType' => 'VIRTUAL',
                'price' => $price,
            ]],
        ];

        // With card storage on, the form shows a "save my card" box, and a
        // cardUserKey brings back the cards this customer stored before.
        if ((string) $this->getSetting('save_cards') !== '0') {
            $body['registerCard'] = 1;

            $cardUserKey = $this->storedCardUserKey($invoice);
            if ($cardUserKey !== null) {
                $body['cardUserKey'] = $cardUserKey;
            }
        }

        $result = $this->request('/payment/iyzipos/checkoutform/initialize/auth/ecom', $body);

        if (! ($result['success'] ?? false)) {
            return $result;
        }

        $token = (string) ($result['token'] ?? '');

        if ($token === '') {
            return ['success' => false, 'message' => __('messages.iyzico.no_token')];
        }

        // How much to credit is never asked of the browser: the lira amount at
        // initialisation, its shop-currency equivalent and the rate between
        // them are written down here and read back when the payment returns.
        GatewayLog::create([
            'gateway' => 'iyzico',
            'date' => now(),
            'data' => json_encode([
                'token' => $token,
                'invoice_id' => (int) $invoice->id,
                'try_amount' => $lira['amount'],
                'due_amount' => round($amount, 2),
                'rate' => $lira['rate'],
                'sandbox' => $this->sandbox(),
            ], JSON_UNESCAPED_UNICODE),
            'result' => 'initialized',
        ]);

        return [
            'success' => true,
            'token' => $token,
            'checkout_form_content' => (string) ($result['checkoutFormContent'] ?? ''),
            'payment_page_url' => (string) ($result['paymentPageUrl'] ?? ''),
            'try_amount' => $lira['amount'],
        ];
    }

    /**
     * The iyzico user key this customer stored a card under.
     *
     * The card number is not here; only iyzico's handle for it, which is what
     * makes their saved cards appear on the form.
     */
    private function storedCardUserKey(Invoice $invoice): ?string
    {
        $row = PaymentMethod::where('client_id', $invoice->client_id)
            ->where('gateway_name', 'iyzico')
            ->whereNotNull('remote_token')
            ->latest('id')
            ->first();

        if (! $row) {
            return null;
        }

        $token = json_decode((string) $row->remote_token, true);

        return is_array($token) ? ($token['cardUserKey'] ?? null) : null;
    }

    /**
     * Ask iyzico whether the payment actually happened.
     *
     * The token arrives at the callback through the customer's browser and so
     * proves nothing on its own. The outcome comes from this call, signed with
     * our own keys - and so does the amount, never from the request.
     *
     * @return array<string, mixed>
     */
    public function retrieveCheckoutForm(string $token): array
    {
        if (trim($token) === '') {
            return ['success' => false, 'message' => __('messages.iyzico.no_token')];
        }

        $result = $this->request('/payment/iyzipos/checkoutform/auth/ecom/detail', [
            'locale' => app()->getLocale() === 'tr' ? 'tr' : 'en',
            'token' => $token,
        ]);

        if (! ($result['success'] ?? false)) {
            return $result;
        }

        if (($result['paymentStatus'] ?? '') !== 'SUCCESS') {
            return [
                'success' => false,
                'message' => $result['errorMessage'] ?? __('messages.iyzico.not_completed'),
                'status' => $result['paymentStatus'] ?? 'UNKNOWN',
            ];
        }

        $itemTransactions = $result['itemTransactions'] ?? [];

        return [
            'success' => true,
            'payment_id' => (string) ($result['paymentId'] ?? ''),
            'conversation_id' => (string) ($result['conversationId'] ?? ''),
            'basket_id' => (string) ($result['basketId'] ?? ''),
            'paid_price' => (float) ($result['paidPrice'] ?? 0),
            'currency' => (string) ($result['currency'] ?? 'TRY'),
            'installment' => (int) ($result['installment'] ?? 1),
            'card_user_key' => $result['cardUserKey'] ?? null,
            'card_token' => $result['cardToken'] ?? null,
            'last_four' => $result['lastFourDigits'] ?? null,
            'card_association' => $result['cardAssociation'] ?? null,
            'payment_transaction_id' => (string) ($itemTransactions[0]['paymentTransactionId'] ?? ''),
        ];
    }

    /**
     * Refund.
     *
     * iyzico refunds a basket line rather than the payment, so the payment is
     * fetched first to find the line. The amount arrives in the shop currency,
     * which is what our records are kept in; it is converted to lira here and
     * capped at what was actually taken, because asking for more than that is
     * refused at the other end.
     *
     * @return array<string, mixed>
     */
    public function refund(string $transactionId, float $amount): array
    {
        $detail = $this->request('/payment/detail', [
            'locale' => 'en',
            'conversationId' => $transactionId,
            'paymentId' => $transactionId,
        ]);

        if (! ($detail['success'] ?? false)) {
            return $detail;
        }

        $itemTransactions = $detail['itemTransactions'] ?? [];
        $paymentTransactionId = (string) ($itemTransactions[0]['paymentTransactionId'] ?? '');

        if ($paymentTransactionId === '') {
            return ['success' => false, 'message' => __('messages.iyzico.no_refund_line')];
        }

        $paidPrice = (float) ($detail['paidPrice'] ?? 0);
        $rate = (float) (Currency::where('code', 'TRY')->value('rate') ?: 1);
        $asked = $amount > 0 ? round($amount * ($rate > 0 ? $rate : 1), 2) : $paidPrice;
        $refund = min($asked, $paidPrice);

        if ($refund <= 0) {
            return ['success' => false, 'message' => __('messages.iyzico.nothing_to_refund')];
        }

        $result = $this->request('/payment/refund', [
            'locale' => 'en',
            'conversationId' => $transactionId,
            'paymentTransactionId' => $paymentTransactionId,
            'price' => $this->money($refund),
            'currency' => (string) ($detail['currency'] ?? 'TRY'),
            'ip' => request()?->ip() ?: '127.0.0.1',
        ]);

        if (! ($result['success'] ?? false)) {
            return $result;
        }

        // A refund should leave as much of a trail as a payment did. Taking
        // the money wrote two rows to gateway_logs and giving it back wrote
        // none, which is the first place anyone reconciling with an iyzico
        // statement would look.
        GatewayLog::create([
            'gateway' => 'iyzico',
            'date' => now(),
            'data' => json_encode([
                'payment_id' => $transactionId,
                'payment_transaction_id' => $paymentTransactionId,
                'refund_try' => $refund,
                'paid_try' => $paidPrice,
                'requested' => round($amount, 2),
            ], JSON_UNESCAPED_UNICODE),
            'result' => 'refunded: '.$transactionId.' / '.$this->money($refund).' TRY',
        ]);

        return [
            'success' => true,
            'refund_id' => (string) ($result['paymentId'] ?? ''),
            'status' => 'succeeded',
            'transaction_id' => $transactionId,
            'amount' => $refund,
        ];
    }

    /**
     * The "pay by card" button on the invoice page.
     *
     * It posts full-page to the interstitial rather than fetching: what iyzico
     * returns is a script, and a script written into a page with innerHTML
     * does not run. It has to be rendered server-side.
     */
    public function getPaymentForm(Invoice $invoice): string
    {
        if (trim((string) $this->getSetting('api_key')) === '' || trim((string) $this->getSetting('secret_key')) === '') {
            return '<div class="alert alert-danger">'.e(__('messages.iyzico.not_configured')).'</div>';
        }

        $amount = $this->money($this->tryPrice($invoice->amountDue())['amount']);
        $action = htmlspecialchars(route('gateway.iyzico.init', $invoice->id), ENT_QUOTES, 'UTF-8');

        return '<form method="POST" action="'.$action.'" class="my-3">'
            .csrf_field()
            .'<button type="submit" class="btn btn-primary w-100">'
            .'<i class="ri-bank-card-line me-1"></i> '
            .htmlspecialchars(__('messages.iyzico.pay_button', ['amount' => '₺'.$amount]), ENT_QUOTES, 'UTF-8')
            .'</button>'
            .'<div class="text-muted small mt-2">'.htmlspecialchars(__('messages.iyzico.secure_note'), ENT_QUOTES, 'UTF-8').'</div>'
            .'</form>';
    }

    /**
     * iyzico reports the outcome through the callback, not a webhook, and the
     * verification there is the token being asked back. The interface requires
     * this method, so it says so rather than pretending to verify anything.
     *
     * @return array<string, mixed>
     */
    public function processWebhook(array $data): array
    {
        return ['success' => false, 'message' => 'iyzico payments are verified through the callback.'];
    }

    // =========================================================================
    // STORED CARDS, AND CHARGING THEM WITH NOBODY WATCHING.
    //
    // Everything below implements TokenizableGatewayInterface. Nothing above it
    // changed: capture(), refund(), retrieveCheckoutForm() and the callback go
    // out through request() exactly as they did, because a refund that stops
    // working is a worse bug than an unattended charge that never starts.
    //
    // WHAT MAKES THIS DIFFERENT FROM STRIPE, AND IT IS ONE SENTENCE.
    // "Majority of iyzico services have designed non-idempotent to ensure
    // predictable and consistent behavior when making repeated requests."
    // (https://docs.iyzico.com/en/getting-started/preliminaries/idempotency,
    // fetched 2026-09-17.) There is no Idempotency-Key header on that page or
    // anywhere else in the API reference, and conversationId is documented only
    // as an "Optional value[] that merchants generates where It helps to match
    // request and response pairs" — a label, not a lock.
    //
    // So the protection Stripe grants us cannot be bought here. With Stripe, a
    // charge whose answer was never heard is repeated under the same key and
    // the gateway answers out of its own record of the first one; the card is
    // never asked twice. Repeat a POST /payment/auth to iyzico and a second
    // card is debited. The repeat IS the disaster.
    //
    // WHAT WE HAVE INSTEAD IS A QUESTION WE CAN ASK. POST /payment/detail takes
    // "paymentId veya paymentConversationId parametrelerinden birinin
    // gönderilmesi zorunludur" (https://docs.iyzico.com/ek-servisler/
    // odeme-sorgulama, fetched 2026-09-17) — and paymentConversationId is the
    // reference WE chose. So the whole design turns on one inversion:
    //
    //     A REPLAY NEVER CHARGES. A REPLAY ASKS.
    //
    // chargeStoredMethod() sends /payment/auth on a first send and ONLY on a
    // first send. Told $params['replay'] === true it sends /payment/detail
    // instead, under the same reference, and reports what iyzico says about the
    // charge that already went out. That is strictly better than Stripe's
    // answer to the same situation: Stripe's module parks an unknown outcome
    // and lets the replay machinery re-present the charge hoping for a cached
    // result; this one resolves the unknown outcome by reading, and a read
    // cannot cost anybody a penny however many times it is wrong.
    // =========================================================================

    /**
     * The currencies iyzico's payment API accepts.
     *
     * "Para birimi. Default; TRY." with the allowed values TRY, USD, EUR, GBP,
     * NOK and CHF (https://docs.iyzico.com/odeme-metotlari/api/non-3ds/
     * non-3ds-entegrasyonu/odeme-olusturma, fetched 2026-09-17; the English
     * page at /en/payment-methods/api/non-3ds/non-3ds-implementation/
     * create-payment lists the same six).
     *
     * SIX, AND STRIPE'S TABLES ARE NOT AMONG THEM. There is deliberately no
     * minor-unit table here and no exponent list, because iyzico has no minor
     * units at all: price and paidPrice are decimal fields — "Sepet toplam
     * tutarı" and "Kullanıcıdan tahsil edilecek toplam tutar" — and money()
     * above already writes them the way iyzico wants, as text with a dot. An
     * exponent table copied from another processor is how a shop selling in yen
     * charges a hundred times the invoice; the reason it cannot happen here is
     * that nothing is ever multiplied.
     */
    private const ACCEPTED_CURRENCIES = ['TRY', 'USD', 'EUR', 'GBP', 'NOK', 'CHF'];

    /**
     * iyzico's own word for "there is no such payment on this merchant".
     *
     * 5087, "Üye İşyerine Ait Ödeme Kaydı Bulunamadı"
     * (https://docs.iyzico.com/ek-bilgiler/hata-kodlari, fetched 2026-09-17).
     * It is the answer /payment/detail gives when the reference we are asking
     * about is not on iyzico's books — and read carelessly it is the most
     * dangerous sentence in this file. See resolveSentCharge().
     */
    private const PAYMENT_NOT_FOUND = '5087';

    /**
     * The stored card is gone, or was never there.
     *
     * 3006 "cardToken bulunamadı", 3001 "cardUserKey zorunlu bir alandır",
     * 3002 "cardToken zorunlu bir alandır", 5111 "cardUserKey bilgisi cardToken
     * ile birlikte gönderilmelidir" (same page). On a detach every one of them
     * means the thing we asked iyzico to let go of is not being held; on a
     * charge they mean this row cannot be presented again by anybody.
     */
    private const CARD_TOKEN_GONE = ['3001', '3002', '3006', '5111'];

    /**
     * Refusals that say the ACCOUNT may not do this, not that the CARD refused.
     *
     * errorGroup is iyzico's own classification — "Hata grubuna göre
     * many-to-many olan hataları iyzico tasnif ederek, API'den döner" — and
     * NOT_PERMITTED_TO_TERMINAL is the group carrying 10058, "Terminalin bu
     * işlemi yapmaya yetkisi yok" (iyzico's published bank-error table,
     * https://raw.githubusercontent.com/iyzico-kurtulussahin/iyzico.gitbook/
     * master/sss/teknik-sorular/hata-kodlari/banka-hatalari/README.md, fetched
     * 2026-09-17; the same code and message appear at
     * https://docs.iyzico.com/ek-bilgiler/hata-kodlari).
     *
     * WHY THIS GROUP HAS A BRANCH OF ITS OWN, AND WHAT IS AND IS NOT CLAIMED.
     * NON-3DS is not on by default: "NON-3DS kullanımı için iyzico hesabınızda
     * bu özelliğin olması gerekmektedir" and "Bu özelliği açtırmak için
     * entegrasyon@iyzico.com adresine eposta göndererek bu talebi
     * oluşturabilirsiniz" (https://docs.iyzico.com/odeme-metotlari/api/non-3ds,
     * fetched 2026-09-17). An unattended charge on an account that has not had
     * it switched on therefore cannot work, ever, until a person sends that
     * email.
     *
     * WHAT IYZICO RETURNS IN THAT CASE IS NOT DOCUMENTED ANYWHERE I COULD FIND,
     * and this constant does not pretend otherwise. It is not named
     * NON_3DS_DISABLED and it does not guess a code. What it says is the thing
     * that IS documented: this group means the terminal is not authorised for
     * the transaction, and "the terminal is not authorised" is an operator's
     * problem whatever switched it off — NON-3DS, instalments, foreign cards.
     * Treating it as a card decline is the failure mode worth avoiding: the
     * customer is told their card failed, the card is marked as needing their
     * attention, and the dunning cycle burns attempts on a card that was never
     * the problem. So it is reported as a refusal that will not change its
     * mind, it never touches the card, and it says the address to write to.
     */
    private const ACCOUNT_NOT_PERMITTED = ['NOT_PERMITTED_TO_TERMINAL', 'INVALID_MERCHANT_OR_SP'];

    /**
     * Refusals that end this card's usefulness until the customer acts.
     *
     * iyzico's errorGroup values, taken from the bank-error table cited above.
     * The test they all pass: presenting the same card again tomorrow cannot
     * produce a different answer, so a retry collects nothing and costs
     * something — card networks cap reattempts and issuers read repeated
     * attempts on a dead card as fraud.
     *
     * DEBIT_CARDS_REQUIRES_3DS (10217, "Banka kartları sadece 3D Secure
     * işleminde kullanılabilir") belongs here rather than with the
     * authentication answers below, and the difference matters. A debit card
     * cannot be charged off-session at all: there is no unattended path that
     * ever satisfies it, so the honest thing is to ask the customer for a
     * different card rather than to keep inviting them to authenticate a
     * payment this integration will never present.
     */
    private const CARD_IS_FINISHED = [
        'LOST_CARD', 'STOLEN_CARD', 'EXPIRED_CARD', 'BLOCKED_CARD', 'RESTRICTED_CARD',
        'INVALID_CARD_NUMBER', 'INVALID_CARD_TYPE', 'INVALID_EXPIRE_YEAR_MONTH',
        'NO_SUCH_ISSUER', 'BIN_NOT_FOUND', 'CARD_NOT_PERMITTED', 'NOT_PERMITTED_TO_CARDHOLDER',
        'NOT_PERMITTED_TO_FOREIGN_CARD', 'FRAUD_SUSPECT', 'RESTRICTED_BY_LAW',
        'REQUEST_BLOCKED_BY_BANK', 'DEBIT_CARDS_REQUIRES_3DS', 'CVC2_MAX_ATTEMPT',
        'INVALID_CVC2', 'INVALID_CVC2_LENGTH', 'EXCEEDS_ALLOWABLE_PIN_TRIES', 'INVALID_PIN',
    ];

    /**
     * Refusals where iyzico is telling us IT does not know what happened.
     *
     * COMMUNICATION_OR_SYSTEM_ERROR, REQUEST_TIMEOUT and
     * ISSUER_OR_SWITCH_INOPERATIVE are the bank-side equivalents of a dropped
     * connection: iyzico reached for the issuer and the exchange did not
     * complete. Nothing in iyzico's documentation says the card was not debited
     * in that case, and a timeout between an acquirer and an issuer is the
     * textbook way a card is debited by a transaction the merchant is told
     * failed.
     *
     * So these are NOT declines. They go to outcomeUnknown(), which on this
     * gateway is not a dead end at all: the next sweep asks /payment/detail
     * under the same reference and finds out. A guess here would be a guess
     * about somebody's money; an inquiry costs one HTTP request.
     */
    private const OUTCOME_NOT_ESTABLISHED = [
        'COMMUNICATION_OR_SYSTEM_ERROR', 'REQUEST_TIMEOUT', 'ISSUER_OR_SWITCH_INOPERATIVE',
        'REQUIRES_DAY_END',
    ];

    /**
     * One request to iyzico with the whole exchange kept intact.
     *
     * request() above flattens everything that is not a success into
     * ['success' => false, 'message' => ...]. That is right for the paths it
     * serves — a checkout form either opened or it did not — and it is useless
     * here, because it throws away the three things an unattended charge has to
     * branch on: whether an answer arrived at all, what HTTP status it arrived
     * under, and iyzico's own errorCode and errorGroup inside the body.
     * "Failed" and "we never heard" look identical through it, and those two
     * mean opposite things about a customer's money.
     *
     * So this is the same signing, byte for byte, with nothing discarded.
     * request() is left exactly as it was rather than being rewritten in terms
     * of this: refund() and capture() are in production and their behaviour is
     * not in the scope of an unattended-charging feature.
     *
     * @return array{sent: bool, status: int|null, body: array<string, mixed>, error: string|null}
     */
    private function send(string $verb, string $uriPath, array $body): array
    {
        $apiKey = trim((string) $this->getSetting('api_key'));
        $secretKey = trim((string) $this->getSetting('secret_key'));

        if ($apiKey === '' || $secretKey === '') {
            // sent: false is the load-bearing part. Nothing left this server, so
            // nothing can have happened at the other end.
            return ['sent' => false, 'status' => null, 'body' => [], 'error' => 'not configured'];
        }

        $payload = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $randomKey = (string) round(microtime(true) * 1000).bin2hex(random_bytes(4));
        $signature = hash_hmac('sha256', $randomKey.$uriPath.$payload, $secretKey);

        $authorization = self::AUTH_VERSION.' '.base64_encode(
            'apiKey:'.$apiKey.'&randomKey:'.$randomKey.'&signature:'.$signature
        );

        try {
            $request = Http::withHeaders([
                'Authorization' => $authorization,
                'x-iyzi-rnd' => $randomKey,
                'Content-Type' => 'application/json',
            ])->withBody($payload, 'application/json')->timeout(30);

            $response = $verb === 'DELETE'
                ? $request->delete($this->baseUrl().$uriPath)
                : $request->post($this->baseUrl().$uriPath);
        } catch (ConnectionException $e) {
            // THE REQUEST WENT OUT AND NOTHING CAME BACK. Not an error to
            // report, a fact to carry: sent is true, so every caller knows the
            // card may already have been charged.
            return ['sent' => true, 'status' => null, 'body' => [], 'error' => $e->getMessage()];
        } catch (\Throwable $e) {
            return ['sent' => true, 'status' => null, 'body' => [], 'error' => $e->getMessage()];
        }

        return [
            'sent' => true,
            'status' => $response->status(),
            'body' => (array) ($response->json() ?? []),
            'error' => null,
        ];
    }

    /**
     * The two keys iyzico needs to charge a card it is holding, off one row.
     *
     * "Kart saklama işlemi sonrasında tarafınıza dönen cardUserKey ve cardToken
     * bilgisini kullanarak 'NON3D veya 3DS' ödeme isteği gönderebilirsiniz",
     * for "abonelik döngüsü oluşturmak isteyen üye işyerleri"
     * (https://docs.iyzico.com/on-hazirliklar/api-reference-beta/kart-saklama,
     * fetched 2026-09-17).
     *
     * TWO SHAPES ARE READ AND ONE IS WRITTEN. Every row this integration has
     * ever stored keeps both keys as JSON in remote_token, which is what
     * storedCardUserKey() above still reads and what the callback has written
     * since before stored cards could be charged. Rows written from today also
     * carry the cardUserKey in gateway_customer_id, where it is indexed and
     * where every other gateway keeps the customer a card hangs off. The JSON
     * is read first so that nothing already in the table needs migrating, and
     * the column is the fallback rather than the other way round for the same
     * reason: a row that has one and not the other is still chargeable.
     *
     * @return array{cardUserKey: string, cardToken: string}|null
     */
    private function storedKeys(PaymentMethod $method): ?array
    {
        $decoded = json_decode((string) $method->remote_token, true);

        $userKey = is_array($decoded) ? trim((string) ($decoded['cardUserKey'] ?? '')) : '';
        $token = is_array($decoded) ? trim((string) ($decoded['cardToken'] ?? '')) : '';

        if ($token === '' && ! is_array($decoded)) {
            // A plain string in remote_token is the token itself.
            $token = trim((string) $method->remote_token);
        }

        if ($userKey === '') {
            $userKey = trim((string) $method->gateway_customer_id);
        }

        if ($userKey === '' || $token === '') {
            return null;
        }

        return ['cardUserKey' => $userKey, 'cardToken' => $token];
    }

    /**
     * Begin storing a card — which iyzico cannot do the way Stripe can, and
     * saying so plainly is the implementation.
     *
     * THE CONTRACT ASKS FOR A SESSION THE BROWSER CAN FINISH WITH NOTHING
     * CHARGED. iyzico has two ways to store a card and neither is that:
     *
     *  - POST /cardstorage/card stores a card with no payment, and its required
     *    fields are the card itself — "cardNumber", "expireYear", "expireMonth",
     *    "cardHolderName" (https://docs.iyzico.com/on-hazirliklar/
     *    api-reference-beta/kart-saklama, fetched 2026-09-17). Calling it means
     *    collecting a PAN on this server. The whole reason this module uses
     *    iyzico's hosted form is that the card never touches us and the PCI
     *    obligation stays at SAQ-A; a card-storage screen that posts a card
     *    number to PNLCS would undo that for every gateway at once.
     *
     *  - The checkout form stores a card as a by-product of taking a payment:
     *    capture() already sends registerCard => 1 and the callback already
     *    keeps what comes back. That is a real vaulting path and it is the one
     *    this integration uses — but it needs an invoice and a payment, so it
     *    is not a session that can be opened from a "add a card" button with
     *    nothing owed.
     *
     * So this returns false, and the message is written for the operator who
     * will read it in the log rather than for the customer, who is shown the
     * shop's own wording by the controller. Refusing here is not a gap in the
     * feature: a customer who pays one invoice by card with the box ticked is
     * vaulted, and every invoice after that is collected without them.
     *
     * NO HTTP CALL IS MADE, DELIBERATELY. PaymentMethodController tries each
     * tokenising gateway in turn and takes the first that opens a session, so a
     * shop running iyzico beside Stripe must be able to fall past this one for
     * free — no request, no customer created at iyzico, no latency in a
     * customer's click.
     *
     * customer_id is still answered whenever we know it, because that part of
     * the contract iyzico can keep: one client has one cardUserKey and their
     * stored cards hang off it, which is what keeps the checkout form showing
     * a returning customer the cards they saved.
     */
    public function beginVaulting(Client $client): array
    {
        return [
            'success' => false,
            'customer_id' => $this->cardUserKeyFor((int) $client->id),
            'message' => __('messages.iyzico.autocharge.no_card_form'),
        ];
    }

    /**
     * The client's cardUserKey at iyzico, if they have one.
     *
     * Asked of gateway_customers first, where every gateway's customer id is
     * kept and where a lookup is indexed, and of the stored cards second,
     * because that is where every key written before gateway_customers existed
     * still lives. A hit on the older place is copied to the newer one on the
     * way past, so each client migrates itself the first time it is asked
     * about — the same arrangement StripeModule::resolveCustomer() uses.
     *
     * Soft-deleted cards count. A customer who removed their only card still
     * has the user record at iyzico, and starting a second one because ours is
     * in the bin is how a client's cards end up scattered across two keys with
     * nothing joining them.
     */
    private function cardUserKeyFor(int $clientId): ?string
    {
        $known = GatewayCustomer::idFor('iyzico', $clientId);

        if ($known !== null && trim($known) !== '') {
            return $known;
        }

        $rows = PaymentMethod::withTrashed()
            ->where('client_id', $clientId)
            ->where('gateway_name', 'iyzico')
            ->latest('id')
            ->get(['gateway_customer_id', 'remote_token']);

        foreach ($rows as $row) {
            $key = trim((string) $row->gateway_customer_id);

            if ($key === '') {
                $decoded = json_decode((string) $row->remote_token, true);
                $key = is_array($decoded) ? trim((string) ($decoded['cardUserKey'] ?? '')) : '';
            }

            if ($key !== '') {
                return GatewayCustomer::remember('iyzico', $clientId, $key);
            }
        }

        return null;
    }

    /**
     * The browser is back from iyzico's form. Ask iyzico, then store the card.
     *
     * $sessionId is the checkout-form token. It arrives through the customer's
     * browser and so proves nothing on its own — which is exactly what
     * retrieveCheckoutForm() is already for, and it is what this calls: the
     * token is handed back to iyzico over a request signed with our own keys
     * and everything written comes out of iyzico's answer.
     *
     * OWNERSHIP IS CHECKED BEFORE ANYTHING IS WRITTEN, and the client is a
     * parameter here rather than something the caller verifies afterwards for
     * the reason the contract gives: by then the card would already be stored
     * against the other account. What iyzico hands back to check it with is the
     * conversationId, which capture() sets to the invoice id; the invoice names
     * its client, and that client must be the one asking. A token for somebody
     * else's payment stores nothing.
     *
     * AND IT WRITES NOTHING OF ITS OWN. rememberStoredCard() does the writing
     * and the iyzico callback in GatewayWebhookController calls the same
     * method, so there is one definition of what a stored iyzico card looks
     * like and one place that creates it. Both paths race on a customer who
     * pays with the box ticked — the callback fires and the browser comes back
     * — and the race is safe because that method is an updateOrCreate: whichever
     * arrives second writes the same row again.
     */
    public function confirmVaulting(Client $client, string $sessionId): array
    {
        $verified = $this->retrieveCheckoutForm($sessionId);

        if (! ($verified['success'] ?? false)) {
            return [
                'success' => false,
                'message' => (string) ($verified['message'] ?? __('messages.iyzico.not_completed')),
            ];
        }

        if (($verified['card_user_key'] ?? null) === null || ($verified['card_token'] ?? null) === null) {
            // The payment went through but the customer did not tick "save my
            // card", so there is nothing to store. Not an error anybody can act
            // on, and emphatically not a card.
            return ['success' => false, 'message' => __('messages.iyzico.autocharge.card_not_saved')];
        }

        $invoice = Invoice::find((int) ($verified['conversation_id'] ?? 0));

        if ($invoice === null || (int) $invoice->client_id !== (int) $client->id) {
            Log::warning('iyzico: refused to store a card from a payment belonging to another client', [
                'client' => $client->id,
                'conversation' => $verified['conversation_id'] ?? null,
            ]);

            return ['success' => false, 'message' => __('messages.iyzico.autocharge.not_your_payment')];
        }

        $this->rememberStoredCard((int) $client->id, $verified);

        return [
            'success' => true,
            'client_id' => (int) $client->id,
            'customer_id' => (string) $verified['card_user_key'],
        ];
    }

    /**
     * Keep the keys iyzico gives back when a customer stores their card.
     *
     * ONE DEFINITION, TWO DOORS. The iyzico callback reached this logic through
     * a private method on GatewayWebhookController and confirmVaulting() would
     * have needed its own copy; two places that both create a stored card are
     * two chances to disagree about what one looks like, and the contract says
     * so in as many words. The controller now calls this.
     *
     * The card number never reaches us; what is kept is iyzico's handle for it.
     *
     * THE MATCH KEY IS THE ONE THE CALLBACK HAS ALWAYS USED and it is not
     * widened here. There is a proven defect in this integration's history
     * where a uniqueness rule added around stored cards broke refunds, and the
     * lesson taken from it is that the set of rows this can collide with is not
     * a thing to change while adding a feature. client_id + gateway + last_four
     * is what it was; what is new is only the columns written, all of which
     * were nullable and empty on every existing row.
     */
    public function rememberStoredCard(int $clientId, array $verified): void
    {
        $cardUserKey = $verified['card_user_key'] ?? null;
        $cardToken = $verified['card_token'] ?? null;

        if (! $cardUserKey || ! $cardToken) {
            return;
        }

        try {
            PaymentMethod::updateOrCreate(
                [
                    'client_id' => $clientId,
                    'gateway_name' => 'iyzico',
                    'last_four' => $verified['last_four'] ?? null,
                ],
                [
                    'description' => trim('iyzico '.($verified['card_association'] ?? '')),
                    'payment_type' => 'card',
                    'remote_token' => json_encode([
                        'cardUserKey' => $cardUserKey,
                        'cardToken' => $cardToken,
                    ]),
                    // Where every other gateway keeps the customer a card hangs
                    // off, so that one client keeps one cardUserKey however many
                    // cards they store.
                    'gateway_customer_id' => (string) $cardUserKey,
                    'card_brand' => $verified['card_association'] ?? null,
                    // A card that arrives fresh from iyzico is usable. Said
                    // explicitly so that a customer whose previous card was
                    // marked as needing attention is not left with a new card
                    // inheriting the old row's verdict.
                    'status' => PaymentMethod::STATUS_ACTIVE,
                ]
            );

            GatewayCustomer::remember('iyzico', $clientId, (string) $cardUserKey);
        } catch (\Throwable $e) {
            // A card that cannot be stored does not make the payment any less
            // valid; it only means the customer types their card again next
            // time.
            Log::warning('iyzico: card key could not be stored: '.$e->getMessage());
        }
    }

    /**
     * Stop keeping a customer's card at iyzico.
     *
     * "Saklı kartı silmek için cardUserKey ve cardToken gönderilmelidir" —
     * DELETE /cardstorage/card (https://docs.iyzico.com/on-hazirliklar/
     * api-reference-beta/kart-saklama, fetched 2026-09-17). Deleting the row at
     * this end only stops PNLCS using the token; iyzico goes on holding the
     * card until this is called, and a customer who asked for their card to be
     * removed asked for both.
     *
     * SAFE TO CALL TWICE, WHICH THE SWEEP REQUIRES. It retries until it is told
     * the card is gone, so "iyzico has never heard of that token" has to be a
     * success rather than a failure retried for ever: there is nothing left to
     * detach, which is the result asked for.
     *
     * retryable is the sweep's instruction and it is set from what can change.
     * Missing keys and an unreachable gateway will be different tomorrow; a
     * token iyzico does not recognise will not.
     */
    public function detachStoredMethod(PaymentMethod $method): array
    {
        if (strtolower((string) $method->gateway_name) !== 'iyzico') {
            return [
                'success' => false,
                'message' => __('messages.iyzico.autocharge.not_an_iyzico_card'),
                'retryable' => false,
            ];
        }

        $keys = $this->storedKeys($method);

        if ($keys === null) {
            // NOTHING STORED AND HALF-STORED ARE DIFFERENT ANSWERS, and giving
            // the same one to both is how a card stays at iyzico while the
            // table says it was let go.
            //
            // A row with no token at all was never vaulted — a bank-transfer
            // reference, or a card the customer chose not to save — so there is
            // genuinely nothing for iyzico to release, and the sweep is right
            // to stop asking.
            //
            // A row that HAS a token but no cardUserKey is the opposite: iyzico
            // is holding that card, and a delete needs both keys — "Saklı kartı
            // silmek için cardUserKey ve cardToken gönderilmelidir", with error
            // 5111 "cardUserKey bilgisi cardToken ile birlikte gönderilmelidir"
            // for sending one without the other. We cannot make the request, so
            // the honest answer is that it is not done and nobody here can do
            // it. Not retryable, because tomorrow's row has the same columns:
            // the sweep says so once, at error level, and a person removes it
            // from iyzico's dashboard. Marking it detached would be a lie in
            // the table, and retryable would be that same lie every five
            // minutes for the life of the installation.
            if (trim((string) $method->remote_token) === '') {
                return ['success' => true, 'message' => __('messages.iyzico.autocharge.nothing_to_detach')];
            }

            Log::error('iyzico: a stored card cannot be deleted because the row does not carry the cardUserKey iyzico needs beside the token', [
                'method' => $method->id,
                'client' => $method->client_id,
            ]);

            return [
                'success' => false,
                'message' => __('messages.iyzico.autocharge.incomplete_keys'),
                'retryable' => false,
            ];
        }

        $result = $this->send('DELETE', '/cardstorage/card', [
            'locale' => 'en',
            'cardUserKey' => $keys['cardUserKey'],
            'cardToken' => $keys['cardToken'],
        ]);

        if (! $result['sent']) {
            // The operator will put the keys back. Until then the card is still
            // at iyzico and saying otherwise would be a lie in the table.
            return [
                'success' => false,
                'message' => __('messages.iyzico.not_configured'),
                'retryable' => true,
            ];
        }

        if ($result['status'] === null) {
            return [
                'success' => false,
                'message' => __('messages.iyzico.unreachable'),
                'retryable' => true,
            ];
        }

        if (($result['body']['status'] ?? null) === 'success') {
            return ['success' => true, 'message' => __('messages.iyzico.autocharge.detached')];
        }

        $code = (string) ($result['body']['errorCode'] ?? '');

        if (in_array($code, self::CARD_TOKEN_GONE, true)) {
            Log::info('iyzico: stored card was already gone', [
                'method' => $method->id,
                'code' => $code,
            ]);

            return ['success' => true, 'message' => __('messages.iyzico.autocharge.already_gone')];
        }

        // Anything else is unexplained. A 5xx or a refusal nobody has seen
        // before may well succeed on the next sweep; a person hears about it
        // either way once the sweep gives up.
        return [
            'success' => false,
            'message' => (string) ($result['body']['errorMessage'] ?? __('messages.iyzico.refused')),
            'retryable' => true,
        ];
    }

    /**
     * Charge a card iyzico is holding, with the cardholder not there.
     *
     * "Kart saklama işlemi sonrasında tarafınıza dönen cardUserKey ve cardToken
     * bilgisini kullanarak 'NON3D veya 3DS' ödeme isteği gönderebilirsiniz",
     * and the named use case is "abonelik döngüsü oluşturmak isteyen üye
     * işyerleri" (https://docs.iyzico.com/on-hazirliklar/api-reference-beta/
     * kart-saklama, fetched 2026-09-17). POST /payment/auth, with a paymentCard
     * carrying the two keys and no card number.
     *
     * THE ONE THING THIS METHOD IS BUILT AROUND: A REPLAY MUST NOT CHARGE.
     *
     * StripeModule answers a replay by sending the identical POST under the
     * identical idempotency key, and Stripe answers it out of its record of the
     * first one. iyzico has no such record and says so — "Majority of iyzico
     * services have designed non-idempotent" (https://docs.iyzico.com/en/
     * getting-started/preliminaries/idempotency, fetched 2026-09-17) — so the
     * identical POST is a second real debit. Every safety property Stripe's
     * module gets from its gateway has to be built here instead, and it is
     * built out of the one thing iyzico does offer: POST /payment/detail may be
     * queried by paymentConversationId, which is OUR reference, so a charge we
     * never heard the answer to can be asked about rather than repeated.
     *
     * Hence: $params['replay'] === true does not reach /payment/auth at all. It
     * goes to resolveSentCharge(), which reads. See that method for what an
     * unheard outcome is actually resolved to and why "not found" is not
     * "did not happen".
     *
     * AND $params['idempotency_key'] IS NOT OPTIONAL HERE, which is the second
     * divergence from Stripe. StripeModule falls back to deriving a key when a
     * caller keeps none, and a derived key is merely worse. On iyzico the
     * caller's reference is not an optimisation, it is the only handle that
     * will ever exist on this charge: it goes out as conversationId, and it is
     * the only string /payment/detail can be asked by afterwards. A charge sent
     * without one could never be asked about, so if the connection dropped
     * there would be no way back at all — not a replay, not an inquiry, not a
     * reconciliation. This module refuses to fire a charge it could not later
     * enquire about. Unreachable through AutoChargeService, which mints the key
     * with the attempt row before the first send and hands the same string back
     * for every repeat of it, and written anyway because a module has to be
     * right about its own answers whoever is asking.
     */
    public function chargeStoredMethod(Invoice $invoice, PaymentMethod $method, float $amount, array $params = []): array
    {
        $isReplay = ($params['replay'] ?? false) === true;

        $reference = $params['idempotency_key'] ?? null;
        $reference = is_string($reference) && trim($reference) !== '' ? trim($reference) : null;

        if (trim((string) $this->getSetting('api_key')) === '' || trim((string) $this->getSetting('secret_key')) === '') {
            // Retryable: nothing was asked of the card, so nothing has been
            // learned about it. Once keys are configured this same attempt
            // works, and refusing to try again would strand the invoice.
            return $this->nothingWasSent($isReplay, __('messages.iyzico.not_configured'), true);
        }

        // The card belongs to a client; the invoice belongs to a client. If
        // those are not the same client somebody has passed the wrong row, and
        // the cost of finding out from the cardholder is far higher than the
        // cost of this comparison.
        if ((int) $method->client_id !== (int) $invoice->client_id) {
            Log::warning('iyzico: refused to charge a stored card belonging to another client', [
                'invoice' => $invoice->id,
                'invoice_client' => $invoice->client_id,
                'method' => $method->id,
                'method_client' => $method->client_id,
            ]);

            return $this->nothingWasSent($isReplay, __('messages.iyzico.autocharge.wrong_client'));
        }

        if (strtolower((string) $method->gateway_name) !== 'iyzico') {
            return $this->nothingWasSent($isReplay, __('messages.iyzico.autocharge.not_an_iyzico_card'));
        }

        // A card in the bin is a card the customer has told us to stop using.
        // Removing one here does not delete it at iyzico, so the token on this
        // row very probably still works — which is exactly why this has to
        // refuse rather than rely on iyzico to. Not retryable: nothing about
        // tomorrow undeletes it.
        if ($method->trashed()) {
            Log::warning('iyzico: refused to charge a stored card the customer had removed', [
                'invoice' => $invoice->id,
                'method' => $method->id,
            ]);

            return $this->nothingWasSent($isReplay, __('messages.iyzico.autocharge.card_removed'));
        }

        // And a card already known to need the customer's attention is not
        // worth asking the issuer about again; that is what the status is for.
        if ($method->status !== PaymentMethod::STATUS_ACTIVE) {
            return $this->nothingWasSent($isReplay, __('messages.iyzico.autocharge.card_needs_update'));
        }

        $keys = $this->storedKeys($method);

        if ($keys === null) {
            return $this->nothingWasSent($isReplay, __('messages.iyzico.autocharge.no_stored_keys'));
        }

        if ($reference === null) {
            Log::error('iyzico: refused to send an unattended charge that could never be enquired about afterwards', [
                'invoice' => $invoice->id,
                'method' => $method->id,
            ]);

            return $this->nothingWasSent($isReplay, __('messages.iyzico.autocharge.no_reference'));
        }

        // WHAT THIS IS WORTH IN LIRA, AND THE ONE WAY THAT CAN BE SILENTLY
        // WRONG. An iyzico merchant account settles in TRY and every other path
        // in this module — capture(), refund(), the callback's credit — is
        // written in those terms, so an unattended charge converts the same way
        // or the books stop agreeing with themselves.
        //
        // tryPrice() falls back to sending the shop-currency number labelled as
        // lira when no TRY rate is stored. In front of a customer that is a
        // visible mistake on a payment page they can abandon; unattended it is
        // a silent one, and a shop pricing in euro would charge 100 TRY for a
        // €100 invoice and mark it paid. So the fallback is refused here rather
        // than taken. Retryable, because an operator entering the rate makes
        // this same attempt correct.
        $shopCurrency = strtoupper((string) shop_currency_code());

        if ($shopCurrency !== 'TRY' && (float) (Currency::where('code', 'TRY')->value('rate') ?: 0) <= 0) {
            Log::error('iyzico: refused an unattended charge because there is no TRY rate to convert it with', [
                'invoice' => $invoice->id,
                'shop_currency' => $shopCurrency,
            ]);

            return $this->nothingWasSent($isReplay, __('messages.iyzico.autocharge.no_try_rate'), true);
        }

        $lira = $this->tryPrice($amount);

        if ($lira['amount'] <= 0) {
            return $this->nothingWasSent($isReplay, __('messages.iyzico.nothing_due'));
        }

        // =====================================================================
        // A REPLAY ASKS. IT DOES NOT CHARGE.
        // =====================================================================
        if ($isReplay) {
            return $this->resolveSentCharge($invoice, $method, $reference, $lira['rate']);
        }

        $price = $this->money($lira['amount']);

        $result = $this->send('POST', '/payment/auth', [
            'locale' => 'en',
            // THE REFERENCE, AND THE WHOLE OF OUR PROTECTION. iyzico documents
            // conversationId as an optional matching aid and nothing more, and
            // that is exactly what is being used: /payment/detail accepts
            // paymentConversationId, so writing our own name on the charge is
            // what makes it findable afterwards. It is the caller's string,
            // written down before the first send and never derived, so it
            // cannot drift between the charge and the question about it.
            'conversationId' => $reference,
            'price' => $price,
            'paidPrice' => $price,
            'currency' => 'TRY',
            'basketId' => (string) ($invoice->invoice_num ?: ('INV'.$invoice->id)),
            'paymentChannel' => 'WEB',
            'paymentGroup' => $this->paymentGroup(),
            // One instalment, always, and not the operator's list. Instalments
            // are an offer made to a cardholder who is choosing; there is
            // nobody here to choose, and a renewal silently taken in six parts
            // is not what either end agreed to.
            'installment' => 1,
            'paymentCard' => [
                'cardUserKey' => $keys['cardUserKey'],
                'cardToken' => $keys['cardToken'],
            ],
            'buyer' => $this->buyer($invoice),
            'shippingAddress' => $this->address($invoice),
            'billingAddress' => $this->address($invoice),
            'basketItems' => [[
                'id' => (string) $invoice->id,
                'name' => mb_substr((string) ($invoice->invoice_num ?: ('#'.$invoice->id)), 0, 100),
                'category1' => 'Hosting',
                'itemType' => 'VIRTUAL',
                'price' => $price,
            ]],
        ]);

        return $this->readChargeAnswer($invoice, $method, $result, $lira['rate'], $reference);
    }

    /**
     * What came back from /payment/auth, and what it means for the money.
     *
     * THE ONE QUESTION FIRST, before the body is read for anything else: do we
     * know whether the card was charged? Every branch after it may assume the
     * answer is yes. Three shapes say no and they are handled together rather
     * than in three places, because "we do not know" is one concept and the
     * moment it was three conditions in three branches on the Stripe side was
     * the moment they started disagreeing about the same customer's money.
     *
     * NOTHING CAME BACK. The POST went out; a dropped connection or a read
     * timeout says nothing about whether iyzico received it or reached the
     * issuer.
     *
     * AN HTTP ANSWER THAT IS NOT IYZICO SPEAKING. iyzico reports its business
     * outcomes in the body — "status": "success" or "failure" with errorCode,
     * errorMessage and errorGroup beside it (https://docs.iyzico.com/
     * odeme-metotlari/api/non-3ds/non-3ds-entegrasyonu/odeme-olusturma, fetched
     * 2026-09-17) — so a response carrying neither is something in front of the
     * API, or something behind it, and it has no opinion on the charge. Every
     * 5xx is in here for the same reason. Nothing in iyzico's documentation
     * says a failed request did not execute, and the absence of a statement is
     * not a statement.
     *
     * A REDIRECT WHERE A RESULT SHOULD BE. See the branch below.
     */
    private function readChargeAnswer(Invoice $invoice, PaymentMethod $method, array $result, float $rate, string $reference): array
    {
        $body = $result['body'];
        $status = $result['status'];
        $spoken = in_array(($body['status'] ?? null), ['success', 'failure'], true);

        if ($status === null || $status >= 500 || ! $spoken) {
            Log::error('iyzico: an unattended charge was sent and no answer came back that says what became of it', [
                'invoice' => $invoice->id,
                'method' => $method->id,
                'status' => $status,
                'error' => $result['error'],
                'reference' => $reference,
            ]);

            return $this->outcomeUnknown(__('messages.iyzico.autocharge.no_answer'));
        }

        // A 3-D SECURE SCREEN IS NOT MONEY AND IT IS NOT A DECLINE.
        //
        // The documented /payment/auth response has no such field: neither the
        // Turkish nor the English create-payment schema lists threeDSHtmlContent
        // or any redirect, and the field exists only on the 3DS endpoint —
        // "/payment/3dsecure/initialize", whose response carries
        // "threeDSHtmlContent", the "Base64-encoded HTML content of the 3DS
        // verification screen" (https://docs.iyzico.com/en/payment-methods/api/
        // 3ds/3ds-implementation/init-3ds, fetched 2026-09-17).
        //
        // IT IS HANDLED ANYWAY, AND THE REASON IS NOT SUPERSTITION. There are
        // claims in circulation that iyzico applies "dynamic 3DS" and can turn
        // a NON3D request into a 3DS one at run time; I looked for that on the
        // payment-method pages and could not find it documented, which settles
        // nothing either way. What it would produce is a redirect arriving
        // where an unattended result was expected, and the cost of being wrong
        // about it is asymmetric: read as a success we credit an invoice for
        // money nobody has taken, read as a decline we tell a customer their
        // card failed and mark it, when the card is fine and the bank merely
        // wanted its owner. requires_action is the contract's name for exactly
        // that, the caller already treats it as neither payment nor failure,
        // and no unattended retry can satisfy it — so retryable is false.
        $redirect = trim((string) ($body['threeDSHtmlContent'] ?? $body['htmlContent'] ?? ''));

        if ($redirect !== '' || ($body['paymentStatus'] ?? null) === 'INIT_THREEDS') {
            Log::warning('iyzico: an unattended charge came back asking for the cardholder to authenticate', [
                'invoice' => $invoice->id,
                'method' => $method->id,
                'reference' => $reference,
            ]);

            return [
                'success' => false,
                'status' => 'requires_action',
                'message' => __('messages.iyzico.autocharge.needs_cardholder'),
                'transaction_id' => ($body['paymentId'] ?? null) ? (string) $body['paymentId'] : null,
                'decline_code' => 'authentication_required',
                'retryable' => false,
            ];
        }

        if (($body['status'] ?? null) === 'success') {
            $paymentId = trim((string) ($body['paymentId'] ?? ''));

            if ($paymentId === '') {
                // Nothing to credit the invoice against. The caller refuses a
                // success with no transaction id anyway; answering unknown
                // rather than success routes it to the machinery that will ask
                // /payment/detail about it on the next sweep, which is the one
                // thing that can still recover the id.
                Log::error('iyzico: reported a successful charge with no paymentId', [
                    'invoice' => $invoice->id,
                    'reference' => $reference,
                ]);

                return $this->outcomeUnknown(__('messages.iyzico.autocharge.no_payment_id'));
            }

            return [
                'success' => true,
                'status' => 'succeeded',
                'transaction_id' => $paymentId,
                // What iyzico says it took, read back through the conversion it
                // was sent through, so the invoice is credited in the currency
                // its books are kept in.
                'amount' => $this->shopAmount($body, $rate),
            ];
        }

        return $this->classifyRefusal($invoice, $method, $body);
    }

    /**
     * The lira iyzico took, expressed in the currency the shop sells in.
     *
     * The rate is the one this charge was sent under rather than whatever is in
     * the table now, so an operator who updates the rate between the POST and
     * the answer cannot change what the invoice is credited with.
     */
    private function shopAmount(array $body, float $rate): float
    {
        $taken = (float) ($body['paidPrice'] ?? 0);

        return $rate > 0 ? round($taken / $rate, 2) : round($taken, 2);
    }

    /**
     * A CHARGE WENT OUT AND NOBODY HEARD THE ANSWER. ASK.
     *
     * This is the central design question of the whole integration and this
     * method is the answer to it.
     *
     * StripeModule, told $params['replay'], sends the charge again and lets
     * Stripe's idempotency layer reply out of its record of the first one. That
     * option does not exist here: iyzico is "designed non-idempotent"
     * (https://docs.iyzico.com/en/getting-started/preliminaries/idempotency,
     * fetched 2026-09-17) and a second POST /payment/auth is a second debit.
     * But iyzico gives something Stripe's module never had — an inquiry keyed
     * on OUR OWN reference: "İstek esnasında paymentId veya
     * paymentConversationId parametrelerinden birinin gönderilmesi zorunludur"
     * (https://docs.iyzico.com/ek-servisler/odeme-sorgulama, fetched
     * 2026-09-17). We wrote conversationId on the charge, so we can ask about a
     * charge we never got an answer to WITHOUT presenting the card again.
     *
     * So a replay reads. And reading is strictly better than replaying: a
     * replay is only safe while the gateway still holds a key, and every one of
     * them risks being a fresh charge if the reasoning about that window is
     * wrong. A read cannot take money however often it is wrong, so being
     * wrong here costs an HTTP request and never a customer.
     *
     * ─────────────────────────────────────────────────────────────────────
     * WHAT THE ANSWERS RESOLVE TO, AND THE ONE THAT MUST NOT.
     * ─────────────────────────────────────────────────────────────────────
     *
     * PAYMENT FOUND, paymentStatus SUCCESS. The money moved. The unknown
     * outcome is RESOLVED — not parked, not replayed: the invoice is credited
     * against iyzico's own paymentId, which is the id the customer's statement
     * will carry. This is the case Stripe's module can only reach by getting
     * lucky with a cached result, and it is the reason this method exists.
     *
     * PAYMENT FOUND, paymentStatus FAILURE. The charge reached the issuer and
     * was refused. Also resolved: the contract requires it — a module told
     * replay "MUST go on resolving the row exactly as before for the answers
     * that do come from that record ... or crashed attempts that genuinely
     * declined would stop being collected". No money moved, so a fresh charge
     * days later is a first charge and not a second debit.
     *
     * NOT FOUND — 5087, "Üye İşyerine Ait Ödeme Kaydı Bulunamadı". THIS IS THE
     * DANGEROUS ONE AND IT IS DELIBERATELY NOT RESOLVED.
     *
     *     The tempting reading is "iyzico has no record of it, so it never
     *     happened, so it is safe to charge again". That reading would be worth
     *     a lot — it would turn every dropped connection back into a collected
     *     invoice — and it is exactly the reasoning that charges a customer
     *     twice, because "not found" carries two meanings that this end cannot
     *     tell apart:
     *
     *       (a) the request never arrived, or arrived and was rejected before
     *           anything was written. Nothing was taken.
     *       (b) the request arrived, the payment is being written, and the
     *           inquiry ran before it was visible.
     *
     *     iyzico documents no read-after-write guarantee for /payment/detail —
     *     the page says nothing about when a payment becomes queryable, and it
     *     does not describe a not-found response at all; 5087 is identified
     *     from the error-code table (https://docs.iyzico.com/ek-bilgiler/
     *     hata-kodlari, fetched 2026-09-17), not from a statement about timing.
     *     With no such guarantee, (b) cannot be excluded, and the cost of
     *     excluding it wrongly is the one thing this system must never do.
     *
     *     AND THE RACE IS NOT A REMOTE ONE. The inquiry is reached precisely
     *     when the charge's answer went missing — a timeout, a connection
     *     dropped mid-write, the gateway having a bad minute. Those are the
     *     conditions under which a payment is most likely to be mid-flight at
     *     the far end, not least.
     *
     *     So 5087 stays unknown, and unknown is cheap here: the caller leaves
     *     the row in flight and the next sweep asks again. Each pass costs one
     *     read. If the payment was merely late it appears and resolves itself;
     *     if it never existed, nothing appears, the caller's bounded machinery
     *     runs out — InvoiceChargeAttempt::MAX_REPLAYS, or its window measured
     *     from the first send — and the invoice goes to a person with the
     *     reference in the row for them to search iyzico's panel by. No card is
     *     touched on a guess at any point.
     *
     *     THE PRICE OF THIS IS REAL AND IT IS ACCEPTED. An invoice whose charge
     *     genuinely never arrived ends up in front of an operator instead of
     *     being collected automatically. That is a person's five minutes. The
     *     alternative is a duplicate debit, which is a chargeback, a refund and
     *     a customer who no longer believes the bill.
     *
     * ANYTHING ELSE. The inquiry itself was refused, or answered something not
     * understood. It says nothing about the charge, so nothing changes: still
     * unknown, ask again next sweep.
     *
     * NOTE WHAT IS NOT IN THAT LIST: there is no path from here to 'failed'
     * with retryable => true on a guess, and no path to 'succeeded' without
     * iyzico naming the payment. This method can only resolve an unknown with
     * iyzico's own word, or leave it unknown.
     */
    private function resolveSentCharge(Invoice $invoice, PaymentMethod $method, string $reference, float $rate): array
    {
        $result = $this->send('POST', '/payment/detail', [
            'locale' => 'en',
            // The merchant-chosen reference the charge went out under. This is
            // the whole reason chargeStoredMethod() refuses to send without one.
            'paymentConversationId' => $reference,
            'conversationId' => $reference,
            'ip' => request()?->ip() ?: '127.0.0.1',
        ]);

        $body = $result['body'];

        if (! $result['sent'] || $result['status'] === null || $body === []) {
            return $this->outcomeUnknown(__('messages.iyzico.autocharge.inquiry_unanswered'));
        }

        if (($body['status'] ?? null) === 'success') {
            $paymentStatus = (string) ($body['paymentStatus'] ?? '');
            $paymentId = trim((string) ($body['paymentId'] ?? ''));

            if ($paymentStatus === 'SUCCESS' && $paymentId !== '') {
                Log::info('iyzico: an unheard charge was traced by its reference and had gone through', [
                    'invoice' => $invoice->id,
                    'method' => $method->id,
                    'reference' => $reference,
                    'payment' => $paymentId,
                ]);

                return [
                    'success' => true,
                    'status' => 'succeeded',
                    'transaction_id' => $paymentId,
                    'amount' => $this->shopAmount($body, $rate),
                ];
            }

            if ($paymentStatus === 'FAILURE') {
                Log::info('iyzico: an unheard charge was traced by its reference and had been refused', [
                    'invoice' => $invoice->id,
                    'method' => $method->id,
                    'reference' => $reference,
                ]);

                // Classified from whatever the inquiry carries about the
                // refusal, and defaulted to worth another go when it carries
                // nothing. The money question is settled either way — iyzico
                // has said this charge took nothing — so a fresh attempt days
                // later is a first charge and cannot be a second debit. What is
                // NOT settled is why, and a decline nobody can read is not a
                // reason to stop collecting from a card that may simply have
                // been short that morning; the caller's own attempt cap bounds
                // how often it is asked.
                if (($body['errorGroup'] ?? $body['errorCode'] ?? null) !== null) {
                    // settled: true, because the outcome is no longer in
                    // question. classifyRefusal() answers "we do not know" for a
                    // bank exchange that did not complete, which is right when
                    // that group arrives on the charge itself — but here iyzico
                    // has already told us the payment FAILED, and a reason of
                    // "the issuer link timed out" does not unsay that. Left
                    // unknown, the row would be asked four more times and then
                    // handed to a person over a charge iyzico has settled.
                    return $this->classifyRefusal($invoice, $method, $body, true);
                }

                return $this->chargeFailed(__('messages.iyzico.autocharge.traced_refusal'), 'inquiry_failure', true);
            }

            // Found, and not finished: iyzico has the payment and has not said
            // the money is there. Not a success — an invoice must not be
            // credited on a maybe — and not a failure either. The id is
            // deliberately withheld: on an in-flight row a transaction id means
            // "the gateway has answered and the ledger owes an entry", and the
            // caller's rescue would credit an invoice against money that may
            // never arrive.
            return $this->outcomeUnknown(__('messages.iyzico.autocharge.inquiry_unfinished', [
                'status' => $paymentStatus !== '' ? $paymentStatus : 'unknown',
            ]));
        }

        $code = (string) ($body['errorCode'] ?? '');

        if ($code === self::PAYMENT_NOT_FOUND) {
            // See the long note above. "Not found" is not "did not happen".
            Log::warning('iyzico: the charge sent under this reference is not on iyzico\'s books yet, which is not proof it never was', [
                'invoice' => $invoice->id,
                'method' => $method->id,
                'reference' => $reference,
            ]);

            return $this->outcomeUnknown(__('messages.iyzico.autocharge.inquiry_not_found'));
        }

        Log::warning('iyzico: the inquiry about an unheard charge was itself refused', [
            'invoice' => $invoice->id,
            'method' => $method->id,
            'reference' => $reference,
            'code' => $code,
        ]);

        return $this->outcomeUnknown(__('messages.iyzico.autocharge.inquiry_refused'));
    }

    /**
     * iyzico refused. Which kind of refusal is it?
     *
     * THREE KINDS, AND CONFUSING THEM IS THE FAILURE THIS METHOD EXISTS TO
     * PREVENT. The classification is taken from errorGroup, which is iyzico's
     * own: "Hata grubuna göre many-to-many olan hataları iyzico tasnif ederek,
     * API'den döner" — the group is the stable, machine-readable name and the
     * numeric code is not (the same group covers many codes). errorCode is the
     * fallback for the validation refusals, which carry no group.
     *
     * THE ACCOUNT IS NOT ALLOWED TO DO THIS. See ACCOUNT_NOT_PERMITTED. The
     * card never came into it, so the card is not marked and the customer is
     * not told anything about their card; retryable is false because no number
     * of tomorrows changes an account setting, and the message names the
     * address iyzico says to write to. This is the branch that catches an
     * account which has not had NON-3DS switched on — and, honestly, anything
     * else the terminal is not cleared for.
     *
     * IYZICO DOES NOT KNOW EITHER. See OUTCOME_NOT_ESTABLISHED. A timeout
     * between acquirer and issuer is the classic way a card is debited by a
     * transaction the merchant is told failed, so this is not a decline at all:
     * it goes back as an unknown outcome, and the next sweep asks
     * /payment/detail about it rather than guessing.
     *
     * THE ISSUER SAID NO. Everything else. CARD_IS_FINISHED marks the card as
     * needing the customer's attention and refuses a retry — asking a dead card
     * again teaches the issuer to distrust every card we present. The rest are
     * ordinary declines worth another morning.
     */
    private function classifyRefusal(Invoice $invoice, PaymentMethod $method, array $body, bool $outcomeSettled = false): array
    {
        $group = strtoupper(trim((string) ($body['errorGroup'] ?? '')));
        $code = (string) ($body['errorCode'] ?? '');
        $message = (string) ($body['errorMessage'] ?? __('messages.iyzico.refused'));

        if (in_array($group, self::ACCOUNT_NOT_PERMITTED, true)) {
            // LOUD, because nothing else will fix it. Every invoice on this
            // shop will refuse the same way until a person acts, and an
            // operator reading "card declined" would go looking at cards.
            Log::error('iyzico: this account is not permitted to take the payment we sent — an unattended charge needs NON-3DS enabled on the iyzico account, which is not on by default', [
                'invoice' => $invoice->id,
                'method' => $method->id,
                'code' => $code,
                'group' => $group,
                'iyzico_message' => $message,
                'action' => 'Ask iyzico to enable NON-3DS on this merchant account: entegrasyon@iyzico.com',
            ]);

            return $this->chargeFailed(__('messages.iyzico.autocharge.account_not_permitted'), $group ?: $code, false);
        }

        if (! $outcomeSettled && in_array($group, self::OUTCOME_NOT_ESTABLISHED, true)) {
            Log::error('iyzico: the issuer exchange did not complete, so whether the card was charged is not established', [
                'invoice' => $invoice->id,
                'method' => $method->id,
                'code' => $code,
                'group' => $group,
            ]);

            return $this->outcomeUnknown(__('messages.iyzico.autocharge.bank_unresolved'));
        }

        // The stored keys no longer name a card iyzico is holding. Nothing at
        // this end can repair that; the customer has to store a card again.
        if (in_array($code, self::CARD_TOKEN_GONE, true)) {
            $method->update(['status' => PaymentMethod::STATUS_REQUIRES_UPDATE]);

            Log::warning('iyzico: the stored card token is no longer held, so the card was marked as needing the customer', [
                'method' => $method->id,
                'code' => $code,
            ]);

            return $this->chargeFailed(__('messages.iyzico.autocharge.token_gone'), $code, false);
        }

        $finished = in_array($group, self::CARD_IS_FINISHED, true);

        if ($finished) {
            // A refusal the customer has to act on is written on the card,
            // because that is what the column is for.
            $method->update(['status' => PaymentMethod::STATUS_REQUIRES_UPDATE]);

            Log::info('iyzico: stored card marked as needing the customer\'s attention', [
                'method' => $method->id,
                'group' => $group,
            ]);
        }

        Log::warning('iyzico: unattended charge refused', [
            'invoice' => $invoice->id,
            'method' => $method->id,
            'code' => $code,
            'group' => $group,
        ]);

        // A REFUSAL WE CANNOT READ IS NOT RETRIED, and that is the deliberate
        // half of this line. A group iyzico has published is an issuer's answer
        // and worth another morning unless it is one of the final ones; a
        // refusal carrying no group at all is a validation or integration
        // error — the request was wrong, not the card — and sending the same
        // wrong request again three days later collects nothing while counting
        // against the customer's attempts.
        return $this->chargeFailed($message, $group ?: $code, $group !== '' && ! $finished);
    }

    /**
     * The contract's failed branch, written in one place.
     *
     * Every caller gets the same keys whether the card was declined, the
     * gateway was unreachable or the row it was handed made no sense.
     */
    private function chargeFailed(string $message, ?string $declineCode = null, bool $retryable = false): array
    {
        return [
            'success' => false,
            'status' => 'failed',
            'message' => $message,
            'decline_code' => $declineCode,
            'retryable' => $retryable,
        ];
    }

    /**
     * The charge was sent and what became of it is not known.
     *
     * Kept apart from chargeFailed() because the two mean opposite things about
     * the customer's money: a failure says nothing was taken, and this says
     * something may have been. retryable is false so that a caller which has
     * never heard of outcome_unknown cannot schedule a fresh charge on the
     * strength of it, and no transaction_id is returned, because an id on an
     * unfinished payment invites a caller to credit an invoice against money
     * that may never arrive.
     *
     * ON THIS GATEWAY IT IS NOT A DEAD END. The caller leaves the row in flight
     * and comes back; on iyzico "coming back" is resolveSentCharge() asking
     * /payment/detail rather than re-presenting the card, so an unknown outcome
     * here is a question with an address on it rather than a coin toss.
     */
    private function outcomeUnknown(string $message): array
    {
        return [
            'success' => false,
            'status' => 'failed',
            'outcome_unknown' => true,
            'message' => $message,
            'decline_code' => null,
            'retryable' => false,
        ];
    }

    /**
     * This end refused before anything went out — and on a replay that is not
     * an answer about the charge that DID go out.
     *
     * Every refusal above the POST is a fact about the state of things right
     * now: no keys, a card the customer has since removed, no rate to convert
     * with. On a first send that is the whole truth and chargeFailed() reports
     * it honestly: nothing was taken, because nothing was sent.
     *
     * ON A REPLAY THE SAME SENTENCE IS TRUE AND IRRELEVANT. A replay only
     * happens because a charge WAS sent and nobody heard what became of it;
     * "we declined to send anything this time" says nothing whatever about
     * that, and reported as a failure it closes an open question with an answer
     * to a different one. Carrying retryable => true it would schedule a fresh
     * REAL charge — on a gateway with no idempotency at all, that is simply a
     * second debit — and carrying false it would write the row off as exhausted
     * with the first charge never looked at by anybody.
     *
     * So on a replay these all become outcomeUnknown(), and the row stays in
     * flight for the machinery that owns unknown outcomes: a few more inquiries
     * and then a person.
     */
    private function nothingWasSent(bool $isReplay, string $message, bool $retryable = false): array
    {
        if ($isReplay) {
            return $this->outcomeUnknown($message.' '.__('messages.iyzico.autocharge.still_unknown'));
        }

        return $this->chargeFailed($message, null, $retryable);
    }

}
