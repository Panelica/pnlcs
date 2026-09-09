<?php

namespace Modules\Gateways\Iyzico;

use App\Contracts\GatewayModuleInterface;
use App\Models\Currency;
use App\Models\GatewayLog;
use App\Models\GatewaySettings;
use App\Models\Invoice;
use App\Models\PaymentMethod;
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
class IyzicoModule implements GatewayModuleInterface
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
}
