<?php

namespace Modules\Ssl\GoGetSSL;

use App\Models\SslOrder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Modules\Ssl\AbstractSslModule;

class GoGetSslModule extends AbstractSslModule
{
    protected ?string $authKey = null;

    public function getModuleName(): string
    {
        return 'gogetssl';
    }

    public function getConfigFields(): array
    {
        return [
            'api_username' => [
                'label' => 'API Username',
                'type' => 'text',
                'required' => true,
            ],
            'api_password' => [
                'label' => 'API Password',
                'type' => 'password',
                'required' => true,
            ],
            'sandbox_mode' => [
                'label' => 'Sandbox Mode',
                'type' => 'checkbox',
                'default' => '1',
            ],
        ];
    }

    public function testConnection(): bool
    {
        try {
            $this->authenticate();
            return $this->authKey !== null;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function purchaseCertificate(SslOrder $order, array $config): array
    {
        try {
            $this->authenticate();

            $productId = $config['product_id'] ?? $order->config_data['gogetssl_product_id'] ?? null;
            if (!$productId) {
                return $this->buildResult(false, 'GoGetSSL product ID not configured');
            }

            $params = [
                'product_id' => $productId,
                'csr' => $order->csr,
                'server_count' => '-1',
                'period' => $config['period'] ?? 12,
                'webserver_type' => $order->webserver_type ?? 1,
                'dcv_method' => $this->mapDcvMethod($order->validation_method),
                'admin_firstname' => $order->admin_first_name,
                'admin_lastname' => $order->admin_last_name,
                'admin_email' => $order->admin_email,
                'admin_phone' => $order->admin_phone ?? '',
                'admin_organization' => $order->admin_org ?? 'N/A',
                'admin_addressline1' => $order->admin_address ?? '',
                'admin_city' => $order->admin_city ?? '',
                'admin_region' => $order->admin_state ?? '',
                'admin_postalcode' => $order->admin_zip ?? '',
                'admin_country' => $order->admin_country ?? 'US',
            ];

            if ($order->validation_method === 'EMAIL') {
                $params['approver_email'] = $order->approver_email;
            }

            // SAN domains
            $sanDomains = $order->getSanDomainsArray();
            if (!empty($sanDomains)) {
                $params['dns_names'] = implode(',', $sanDomains);
            }

            $response = $this->apiPost('/orders/add_ssl_order/', $params);

            if (!empty($response['order_id'])) {
                $order->update([
                    'remote_id' => (string) $response['order_id'],
                    'status' => 'Awaiting Issuance',
                    'order_date' => now(),
                    'last_polled_at' => now(),
                ]);

                $result = $this->buildResult(true, 'Certificate order placed successfully', $response);
                $this->logAction($order, 'purchaseCertificate', $result);
                return $result;
            }

            $error = $response['message'] ?? $response['description'] ?? 'Unknown GoGetSSL error';
            $result = $this->buildResult(false, "Order failed: {$error}", $response);
            $this->logAction($order, 'purchaseCertificate', $result);
            return $result;
        } catch (\Throwable $e) {
            $result = $this->buildResult(false, "GoGetSSL API error: {$e->getMessage()}");
            $this->logAction($order, 'purchaseCertificate', $result);
            return $result;
        }
    }

    public function getCertificateStatus(SslOrder $order): array
    {
        try {
            $this->authenticate();

            if (empty($order->remote_id)) {
                return $this->buildResult(false, 'No remote order ID');
            }

            $response = $this->apiGet("/orders/status/{$order->remote_id}");

            $updates = ['last_polled_at' => now()];

            $status = $response['status'] ?? null;
            if ($status === 'active' || $status === 'issued') {
                $updates['status'] = 'Completed';
                $updates['completion_date'] = now();

                if (!empty($response['crt_code'])) {
                    $updates['cert'] = $response['crt_code'];
                }
                if (!empty($response['ca_code'])) {
                    $updates['ca_cert'] = $response['ca_code'];
                }
                if (!empty($response['crt_code']) && !empty($response['ca_code'])) {
                    $updates['fullchain'] = $response['crt_code'] . "\n" . $response['ca_code'];
                }
                if (!empty($response['valid_till'])) {
                    $updates['crt_expires'] = $response['valid_till'];
                }
            } elseif ($status === 'cancelled' || $status === 'rejected') {
                $updates['status'] = 'Cancelled';
            } elseif ($status === 'processing' || $status === 'pending') {
                $updates['status'] = 'Awaiting Issuance';
            }

            $order->update($updates);

            $result = $this->buildResult(true, "Status: {$status}", $response);
            $this->logAction($order, 'getCertificateStatus', $result);
            return $result;
        } catch (\Throwable $e) {
            $result = $this->buildResult(false, "Status check failed: {$e->getMessage()}");
            $this->logAction($order, 'getCertificateStatus', $result);
            return $result;
        }
    }

    public function renewCertificate(SslOrder $order): array
    {
        try {
            $this->authenticate();

            $productId = $order->config_data['gogetssl_product_id'] ?? null;
            if (!$productId) {
                return $this->buildResult(false, 'GoGetSSL product ID not configured');
            }

            $params = [
                'product_id' => $productId,
                'csr' => $order->csr,
                'server_count' => '-1',
                'period' => 12,
                'webserver_type' => $order->webserver_type ?? 1,
                'dcv_method' => $this->mapDcvMethod($order->validation_method),
                'admin_firstname' => $order->admin_first_name,
                'admin_lastname' => $order->admin_last_name,
                'admin_email' => $order->admin_email,
                'admin_phone' => $order->admin_phone ?? '',
                'admin_organization' => $order->admin_org ?? 'N/A',
                'admin_addressline1' => $order->admin_address ?? '',
                'admin_city' => $order->admin_city ?? '',
                'admin_region' => $order->admin_state ?? '',
                'admin_postalcode' => $order->admin_zip ?? '',
                'admin_country' => $order->admin_country ?? 'US',
            ];

            if ($order->validation_method === 'EMAIL') {
                $params['approver_email'] = $order->approver_email;
            }

            $response = $this->apiPost('/orders/add_ssl_renew_order/', $params);

            if (!empty($response['order_id'])) {
                $order->update([
                    'remote_id' => (string) $response['order_id'],
                    'status' => 'Awaiting Issuance',
                    'order_date' => now(),
                    'cert' => null,
                    'ca_cert' => null,
                    'fullchain' => null,
                    'completion_date' => null,
                ]);

                $result = $this->buildResult(true, 'Renewal order placed', $response);
                $this->logAction($order, 'renewCertificate', $result);
                return $result;
            }

            $error = $response['message'] ?? 'Renewal failed';
            $result = $this->buildResult(false, $error, $response);
            $this->logAction($order, 'renewCertificate', $result);
            return $result;
        } catch (\Throwable $e) {
            $result = $this->buildResult(false, "Renewal error: {$e->getMessage()}");
            $this->logAction($order, 'renewCertificate', $result);
            return $result;
        }
    }

    public function revokeCertificate(SslOrder $order, string $reason = ''): array
    {
        try {
            $this->authenticate();

            if (empty($order->remote_id)) {
                return $this->buildResult(false, 'No remote order ID');
            }

            $response = $this->apiPost('/orders/cancel_ssl_order/', [
                'order_id' => $order->remote_id,
                'reason' => $reason ?: 'Revoked by administrator',
            ]);

            $order->update(['status' => 'Revoked']);

            $result = $this->buildResult(true, 'Certificate revoked', $response);
            $this->logAction($order, 'revokeCertificate', $result);
            return $result;
        } catch (\Throwable $e) {
            $result = $this->buildResult(false, "Revoke error: {$e->getMessage()}");
            $this->logAction($order, 'revokeCertificate', $result);
            return $result;
        }
    }

    public function reissueCertificate(SslOrder $order, string $newCsr): array
    {
        try {
            $this->authenticate();

            if (empty($order->remote_id)) {
                return $this->buildResult(false, 'No remote order ID');
            }

            $params = [
                'order_id' => $order->remote_id,
                'csr' => $newCsr,
                'dcv_method' => $this->mapDcvMethod($order->validation_method),
                'webserver_type' => $order->webserver_type ?? 1,
            ];

            if ($order->validation_method === 'EMAIL') {
                $params['approver_email'] = $order->approver_email;
            }

            $response = $this->apiPost('/orders/ssl/reissue/', $params);

            $order->update([
                'csr' => $newCsr,
                'status' => 'Awaiting Issuance',
                'cert' => null,
                'ca_cert' => null,
                'fullchain' => null,
                'completion_date' => null,
            ]);

            $result = $this->buildResult(true, 'Reissue requested', $response);
            $this->logAction($order, 'reissueCertificate', $result);
            return $result;
        } catch (\Throwable $e) {
            $result = $this->buildResult(false, "Reissue error: {$e->getMessage()}");
            $this->logAction($order, 'reissueCertificate', $result);
            return $result;
        }
    }

    public function resendValidationEmail(SslOrder $order): array
    {
        try {
            $this->authenticate();

            if (empty($order->remote_id)) {
                return $this->buildResult(false, 'No remote order ID');
            }

            $response = $this->apiPost('/orders/ssl/resend_validation_email/', [
                'order_id' => $order->remote_id,
            ]);

            $result = $this->buildResult(true, 'Validation email resent', $response);
            $this->logAction($order, 'resendValidationEmail', $result);
            return $result;
        } catch (\Throwable $e) {
            $result = $this->buildResult(false, "Resend error: {$e->getMessage()}");
            $this->logAction($order, 'resendValidationEmail', $result);
            return $result;
        }
    }

    public function changeValidationMethod(SslOrder $order, string $method): array
    {
        try {
            $this->authenticate();

            if (empty($order->remote_id)) {
                return $this->buildResult(false, 'No remote order ID');
            }

            $response = $this->apiPost('/orders/ssl/change_dcv_method/', [
                'order_id' => $order->remote_id,
                'dcv_method' => $this->mapDcvMethod($method),
            ]);

            $order->update(['validation_method' => $method]);

            $result = $this->buildResult(true, 'Validation method changed', $response);
            $this->logAction($order, 'changeValidationMethod', $result);
            return $result;
        } catch (\Throwable $e) {
            $result = $this->buildResult(false, "Change DCV error: {$e->getMessage()}");
            $this->logAction($order, 'changeValidationMethod', $result);
            return $result;
        }
    }

    public function getApproverEmails(string $domain): array
    {
        try {
            $this->authenticate();

            $response = $this->apiPost('/tools/domain/emails/', [
                'domain' => $domain,
            ]);

            $emails = [];
            if (!empty($response) && is_array($response)) {
                foreach ($response as $key => $value) {
                    if (is_string($value) && str_contains($value, '@')) {
                        $emails[] = $value;
                    } elseif (is_array($value)) {
                        foreach ($value as $email) {
                            if (is_string($email) && str_contains($email, '@')) {
                                $emails[] = $email;
                            }
                        }
                    }
                }
            }

            // Fallback standard emails
            if (empty($emails)) {
                $emails = [
                    "admin@{$domain}",
                    "administrator@{$domain}",
                    "webmaster@{$domain}",
                    "hostmaster@{$domain}",
                    "postmaster@{$domain}",
                ];
            }

            return $emails;
        } catch (\Throwable $e) {
            return [
                "admin@{$domain}",
                "administrator@{$domain}",
                "webmaster@{$domain}",
                "hostmaster@{$domain}",
                "postmaster@{$domain}",
            ];
        }
    }

    public function getCertificateTypes(): array
    {
        try {
            $this->authenticate();
            $response = $this->apiGet('/products/');

            $types = [];
            if (!empty($response['products']) && is_array($response['products'])) {
                foreach ($response['products'] as $product) {
                    $types[] = [
                        'id' => $product['id'] ?? null,
                        'name' => $product['name'] ?? '',
                        'brand' => $product['brand'] ?? '',
                        'type' => $product['validation'] ?? '',
                        'wildcard' => $product['wildcard'] ?? false,
                        'multi_domain' => $product['multi_domain'] ?? false,
                        'min_period' => $product['periods']['min'] ?? 12,
                        'max_period' => $product['periods']['max'] ?? 24,
                    ];
                }
            }
            return $types;
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function decodeCsr(string $csr): array
    {
        try {
            $this->authenticate();

            $response = $this->apiPost('/tools/csr/decode/', [
                'csr' => $csr,
            ]);

            return [
                'success' => true,
                'data' => [
                    'cn' => $response['csrResult']['CN'] ?? '',
                    'org' => $response['csrResult']['O'] ?? '',
                    'ou' => $response['csrResult']['OU'] ?? '',
                    'city' => $response['csrResult']['L'] ?? '',
                    'state' => $response['csrResult']['ST'] ?? '',
                    'country' => $response['csrResult']['C'] ?? '',
                    'email' => $response['csrResult']['emailAddress'] ?? '',
                    'key_size' => $response['csrResult']['keySize'] ?? '',
                    'san' => $response['csrResult']['subjectAlternativeNames'] ?? [],
                ],
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function generateCsr(array $params): array
    {
        try {
            $this->authenticate();

            $response = $this->apiPost('/tools/csr/generate/', [
                'common_name' => $params['domain'] ?? '',
                'organization' => $params['org'] ?? 'N/A',
                'department' => $params['ou'] ?? '',
                'city' => $params['city'] ?? '',
                'state' => $params['state'] ?? '',
                'country' => $params['country'] ?? 'US',
                'email' => $params['email'] ?? '',
            ]);

            if (!empty($response['csr_code'])) {
                return [
                    'success' => true,
                    'csr' => $response['csr_code'],
                    'key' => $response['key'] ?? '',
                ];
            }

            return ['success' => false, 'message' => $response['message'] ?? 'CSR generation failed'];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ── Internal API Methods ──────────────────────────────────────

    protected function getApiBase(): string
    {
        $sandbox = $this->getSetting('sandbox_mode', '1');
        return $sandbox === '1'
            ? 'https://sandbox.gogetssl.com/api'
            : 'https://my.gogetssl.com/api';
    }

    protected function authenticate(): void
    {
        if ($this->authKey) {
            return;
        }

        $cacheKey = 'gogetssl_auth_' . md5($this->getSetting('api_username', ''));
        $this->authKey = Cache::get($cacheKey);

        if ($this->authKey) {
            return;
        }

        $response = Http::asForm()->post($this->getApiBase() . '/auth/', [
            'user' => $this->getSetting('api_username'),
            'pass' => $this->getSetting('api_password'),
        ])->json();

        if (empty($response['key'])) {
            throw new \RuntimeException('GoGetSSL authentication failed: ' . ($response['message'] ?? 'no key returned'));
        }

        $this->authKey = $response['key'];
        Cache::put($cacheKey, $this->authKey, now()->addMinutes(25));
    }

    protected function apiGet(string $path): array
    {
        $url = $this->getApiBase() . $path;
        $response = Http::get($url, ['auth_key' => $this->authKey]);
        return $response->json() ?? [];
    }

    protected function apiPost(string $path, array $data = []): array
    {
        $data['auth_key'] = $this->authKey;
        $url = $this->getApiBase() . $path;
        $response = Http::asForm()->post($url, $data);
        return $response->json() ?? [];
    }

    protected function mapDcvMethod(string $method): string
    {
        return match (strtoupper($method)) {
            'EMAIL' => 'email',
            'HTTP', 'HTTPCSRHASH' => 'http',
            'HTTPS', 'HTTPSCSRHASH' => 'https',
            'DNS', 'CNAME', 'CNAMECSRHASH' => 'dns',
            default => 'email',
        };
    }
}
