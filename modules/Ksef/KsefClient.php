<?php

namespace Modules\Ksef;

use App\Models\Invoice;
use App\Models\KsefInvoice;
use Illuminate\Support\Facades\Http;
use Modules\Ksef\Support\Crypto;
use Modules\Ksef\Support\InvoiceXmlBuilder;
use Modules\Ksef\Support\XadesSigner;

/**
 * HTTP client for the KSeF 2.0 API.
 *
 * Authentication: XAdES qualified-seal signature over the AuthTokenRequest
 * (challenge → xades-signature → token/redeem). Invoice sending: an online
 * session with an AES-encrypted invoice (the symmetric key is wrapped with
 * the MF public key using RSA-OAEP SHA-256).
 */
class KsefClient
{
    public function __construct(
        protected InvoiceXmlBuilder $xml,
        protected XadesSigner $signer,
    ) {}

    /**
     * Run the authentication exchange and return an access token.
     */
    public function authenticate(array $settings): string
    {
        $endpoint = rtrim((string) $settings['endpoint'], '/');
        $nip = (string) $settings['nip'];

        // 1. Challenge.
        $challenge = Http::accept('application/json')
            ->timeout((int) $settings['http']['request_timeout'])
            ->connectTimeout((int) $settings['http']['connect_timeout'])
            ->post($endpoint.'/auth/challenge')
            ->throw()
            ->json();

        $challengeValue = (string) ($challenge['challenge'] ?? '');

        // 2. Build + sign the AuthTokenRequest (XAdES).
        $unsigned = $this->authTokenRequestXml($challengeValue, $nip);
        $signed = $this->signer->sign(
            $unsigned,
            (string) $settings['private_key'],
            (string) ($settings['private_key_passphrase'] ?? ''),
            (string) $settings['certificate'],
        );

        // 3. Submit the signed XML.
        $initResponse = Http::accept('application/json')
            ->contentType('application/xml')
            ->timeout((int) $settings['http']['request_timeout'])
            ->connectTimeout((int) $settings['http']['connect_timeout'])
            ->withBody($signed, 'application/xml')
            ->post($endpoint.'/auth/xades-signature');

        if (! $initResponse->successful()) {
            throw new \RuntimeException('KSeF auth rejected: '.(string) $initResponse->body());
        }

        $init = $initResponse->json();

        $referenceNumber = (string) ($init['referenceNumber'] ?? '');
        $authenticationToken = (string) ($init['authenticationToken']['token'] ?? '');

        if ($referenceNumber === '' || $authenticationToken === '') {
            throw new \RuntimeException(__('messages.ksef.auth_failed'));
        }

        // 4. Poll the status until the authentication becomes active.
        $this->waitForAuth($endpoint, $referenceNumber, $authenticationToken, $settings);

        // 5. Redeem the temporary token for the access token.
        $tokens = Http::withToken($authenticationToken)
            ->accept('application/json')
            ->timeout((int) $settings['http']['request_timeout'])
            ->connectTimeout((int) $settings['http']['connect_timeout'])
            ->post($endpoint.'/auth/token/redeem')
            ->throw()
            ->json();

        $accessToken = (string) ($tokens['accessToken']['token'] ?? '');

        if ($accessToken === '') {
            throw new \RuntimeException(__('messages.ksef.auth_failed'));
        }

        return $accessToken;
    }

    /**
     * @return array{success: bool, status?: string, ksef_number?: string, accepted?: bool, sent_at?: string, request_xml?: string, response_xml?: string, message?: string}
     */
    public function submit(Invoice $invoice, KsefInvoice $record): array
    {
        $settings = KsefSettings::resolve();

        $requestXml = $this->xml->build($invoice, (string) $settings['nip']);

        if (! KsefSettings::configured()) {
            return [
                'success' => false,
                'request_xml' => $requestXml,
                'message' => __('messages.ksef.missing_credentials'),
            ];
        }

        try {
            $accessToken = $this->authenticate($settings);
            $endpoint = rtrim((string) $settings['endpoint'], '/');

            // Open an online session.
            [$key, $iv, $encryptedKey, $publicKeyId] = $this->sessionCredentials($endpoint, $accessToken, $settings);

            $sessionResponse = Http::withToken($accessToken)
                ->accept('application/json')
                ->timeout((int) $settings['http']['request_timeout'])
                ->connectTimeout((int) $settings['http']['connect_timeout'])
                ->post($endpoint.'/sessions/online', [
                    'formCode' => [
                        'systemCode' => 'FA (2)',
                        'schemaVersion' => '1-0E',
                        'value' => 'FA',
                    ],
                    'encryption' => [
                        'encryptedSymmetricKey' => base64_encode($encryptedKey),
                        'initializationVector' => base64_encode($iv),
                        'publicKeyId' => $publicKeyId,
                    ],
                ]);

            if (! $sessionResponse->successful()) {
                return ['success' => false, 'request_xml' => $requestXml, 'message' => 'Session open: '.(string) $sessionResponse->body()];
            }

            $session = $sessionResponse->json();

            $sessionRef = (string) ($session['referenceNumber'] ?? '');

            if ($sessionRef === '') {
                return ['success' => false, 'request_xml' => $requestXml, 'message' => __('messages.ksef.session_failed').': '.json_encode($session)];
            }

            // Encrypt the invoice and send it.
            $encrypted = Crypto::aes256Cbc($requestXml, $key, $iv);

            $sendResponse = Http::withToken($accessToken)
                ->accept('application/json')
                ->timeout((int) $settings['http']['request_timeout'])
                ->connectTimeout((int) $settings['http']['connect_timeout'])
                ->post($endpoint."/sessions/online/{$sessionRef}/invoices", [
                    'invoiceHash' => Crypto::sha256Base64($requestXml),
                    'invoiceSize' => strlen($requestXml),
                    'encryptedInvoiceHash' => Crypto::sha256Base64($encrypted),
                    'encryptedInvoiceSize' => strlen($encrypted),
                    'encryptedInvoiceContent' => base64_encode($encrypted),
                    'offlineMode' => false,
                ]);

            if (! $sendResponse->successful()) {
                return ['success' => false, 'request_xml' => $requestXml, 'message' => 'Invoice send: '.(string) $sendResponse->body()];
            }

            $send = $sendResponse->json();

            $invoiceRef = (string) ($send['referenceNumber'] ?? '');

            // Poll the invoice status to get the acceptance result and the
            // KSeF number (processing is asynchronous).
            $status = $this->pollInvoice($endpoint, $accessToken, $sessionRef, $invoiceRef, $settings);

            return [
                'success' => true,
                'session_reference' => $sessionRef,
                'status' => $status['status'] ?? 'sent',
                'ksef_number' => $status['ksef_number'] ?? $invoiceRef,
                'accepted' => ($status['status'] ?? '') === 'accepted',
                'sent_at' => now()->toDateTimeString(),
                'request_xml' => $requestXml,
                'response_xml' => json_encode($send),
                'message' => $status['message'] ?? __('messages.ksef.sent'),
                'error_message' => $status['error'] ?? null,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'request_xml' => $requestXml,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function testConnection(): array
    {
        $settings = KsefSettings::resolve();

        if (! KsefSettings::configured()) {
            return ['success' => false, 'message' => __('messages.ksef.missing_credentials')];
        }

        try {
            $accessToken = $this->authenticate($settings);

            return $accessToken !== ''
                ? ['success' => true, 'message' => __('messages.ksef.test_ok')]
                : ['success' => false, 'message' => __('messages.ksef.auth_failed')];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    protected function authTokenRequestXml(string $challenge, string $nip): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<AuthTokenRequest xmlns="http://ksef.mf.gov.pl/auth/token/2.0">'
            .'<Challenge>'.htmlspecialchars($challenge, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</Challenge>'
            .'<ContextIdentifier><Nip>'.htmlspecialchars($nip, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</Nip></ContextIdentifier>'
            .'<SubjectIdentifierType>certificateSubject</SubjectIdentifierType>'
            .'</AuthTokenRequest>';
    }

    protected function waitForAuth(string $endpoint, string $referenceNumber, string $authenticationToken, array $settings): void
    {
        for ($i = 0; $i < 30; $i++) {
            $status = Http::withToken($authenticationToken)
                ->accept('application/json')
                ->timeout((int) $settings['http']['request_timeout'])
                ->connectTimeout((int) $settings['http']['connect_timeout'])
                ->get($endpoint."/auth/{$referenceNumber}")
                ->throw()
                ->json();

            $code = (int) ($status['status']['code'] ?? 0);

            if ($code === 200) {
                return;
            }

            if ($code >= 400) {
                $description = (string) ($status['status']['description'] ?? '');
                throw new \RuntimeException(__('messages.ksef.auth_failed').($description !== '' ? ': '.$description : ''));
            }

            sleep(1);
        }

        throw new \RuntimeException(__('messages.ksef.auth_timeout'));
    }

    /**
     * Poll an invoice's status until it is accepted or rejected.
     *
     * @return array{status: string, ksef_number?: ?string, message?: string, error?: ?string}
     */
    protected function pollInvoice(string $endpoint, string $token, string $sessionRef, string $invoiceRef, array $settings): array
    {
        for ($i = 0; $i < 10; $i++) {
            $r = Http::withToken($token)
                ->accept('application/json')
                ->timeout((int) $settings['http']['request_timeout'])
                ->connectTimeout((int) $settings['http']['connect_timeout'])
                ->get($endpoint."/sessions/{$sessionRef}/invoices/{$invoiceRef}");

            if ($r->successful()) {
                $data = $r->json();
                $status = $data['status'] ?? [];
                $code = (int) ($status['code'] ?? 0);

                if ($code === 200) {
                    return [
                        'status' => 'accepted',
                        'ksef_number' => $data['ksefNumber'] ?? null,
                        'message' => __('messages.ksef.sent'),
                    ];
                }

                if ($code >= 400) {
                    $description = (string) ($status['description'] ?? '');
                    $details = implode('; ', (array) ($status['details'] ?? []));

                    return [
                        'status' => 'error',
                        'error' => $description.($details !== '' ? ': '.$details : ''),
                    ];
                }
            }

            sleep(2);
        }

        return ['status' => 'sent', 'message' => __('messages.ksef.sent')];
    }

    /**
     * @return array{0: string, 1: string, 2: string, 3: ?string} [key, iv, encryptedKey, publicKeyId]
     */
    protected function sessionCredentials(string $endpoint, string $accessToken, array $settings): array
    {
        $certs = Http::withToken($accessToken)
            ->accept('application/json')
            ->timeout((int) $settings['http']['request_timeout'])
            ->connectTimeout((int) $settings['http']['connect_timeout'])
            ->get($endpoint.'/security/public-key-certificates')
            ->throw()
            ->json();

        $symmetric = null;
        foreach ($certs as $cert) {
            if (in_array('SymmetricKeyEncryption', $cert['usage'] ?? [], true)) {
                $symmetric = $cert;
                break;
            }
        }

        if ($symmetric === null) {
            throw new \RuntimeException(__('messages.ksef.public_key_missing'));
        }

        $key = Crypto::randomKey();
        $iv = Crypto::randomIv();
        $encryptedKey = Crypto::rsaOaepSha256($key, (string) $symmetric['certificate']);

        return [$key, $iv, $encryptedKey, $symmetric['publicKeyId'] ?? null];
    }
}
