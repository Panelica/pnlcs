<?php

namespace Modules\Servers\PanelicaLicense;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Panelica Central's partner API (/api/reseller/v1), signed in with the
 * partner's API key (rk_...) from the Panelica Licenses page (PartnerAccount).
 *
 * The key never reaches a log line or a message: errors carry Central's own
 * words and the HTTP status only.
 */
final class CentralClient
{
    private const API = '/api/reseller/v1/';

    private function __construct(private string $baseUrl, private string $apiKey) {}

    /** The partner account this site is connected to, or null when it is not. */
    public static function partner(): ?self
    {
        $key = PartnerAccount::apiKey();

        return $key === null ? null : new self(PartnerAccount::centralUrl(), $key);
    }

    /** A key and address being tried before they are saved. */
    public static function make(string $baseUrl, string $apiKey): self
    {
        return new self(rtrim(trim($baseUrl), '/') ?: PartnerAccount::DEFAULT_CENTRAL, trim($apiKey));
    }

    public function baseUrl(): string
    {
        return $this->baseUrl;
    }

    public function get(string $path, array $query = []): CentralResponse
    {
        return $this->send('GET', $path, $query);
    }

    public function post(string $path, array $body = [], ?string $idempotencyKey = null): CentralResponse
    {
        return $this->send('POST', $path, $body, $idempotencyKey);
    }

    public function patch(string $path, array $body = []): CentralResponse
    {
        return $this->send('PATCH', $path, $body);
    }

    public function put(string $path, array $body = []): CentralResponse
    {
        return $this->send('PUT', $path, $body);
    }

    /**
     * The script Central serves at /install/{code}/{ref} (or /install/{code}
     * for the partner's general link): the official installer, unchanged,
     * plus the report that ties the server to the partner and to this order.
     * Null when Central did not answer with that script - an unknown or
     * disabled code is answered with a redirect to the plain installer, which
     * would install a server nobody can tie to the partner.
     */
    public function installScript(string $code, ?string $ref = null): ?string
    {
        try {
            $response = Http::withoutRedirecting()
                ->timeout(20)
                ->accept('text/plain')
                ->withUserAgent('PNLCS')
                ->get($this->baseUrl().'/install/'.rawurlencode($code).($ref !== null ? '/'.rawurlencode($ref) : ''));
        } catch (\Throwable $e) {
            Log::warning('Panelica license: Central did not serve the install script', ['error' => $e->getMessage()]);

            return null;
        }

        $body = (string) $response->body();

        if ($response->status() !== 200 || ! str_starts_with($body, '#!') || ! str_contains($body, 'panelica_installer')) {
            Log::warning('Panelica license: Central did not serve the install script', ['status' => $response->status()]);

            return null;
        }

        return $body;
    }

    private function request(): PendingRequest
    {
        return Http::withToken($this->apiKey)
            ->acceptJson()
            ->asJson()
            ->withUserAgent('PNLCS')
            // Renewals run inside the request that took the customer's
            // payment; a slow Central must not hold that page for long.
            ->timeout(15)
            ->connectTimeout(8);
    }

    private function send(string $method, string $path, array $payload = [], ?string $idempotencyKey = null): CentralResponse
    {
        if ($this->apiKey === '') {
            return new CentralResponse(false, 0, error: __('panelica_license.error.no_key'));
        }

        $url = $this->baseUrl().self::API.ltrim($path, '/');

        try {
            $request = $this->request();
            if ($idempotencyKey !== null) {
                $request = $request->withHeaders(['Idempotency-Key' => $idempotencyKey]);
            }

            $response = match ($method) {
                'POST' => $request->post($url, $payload),
                'PATCH' => $request->patch($url, $payload),
                'PUT' => $request->put($url, $payload),
                default => $request->get($url, $payload),
            };
        } catch (\Throwable $e) {
            Log::warning('Panelica license: Central could not be reached', ['path' => $path, 'error' => $e->getMessage()]);

            return new CentralResponse(false, 0, error: __('panelica_license.error.unreachable'));
        }

        $json = $response->json();
        $json = is_array($json) ? $json : [];

        if ($response->successful() && ($json['success'] ?? true) !== false) {
            return new CentralResponse(true, $response->status(), $json['data'] ?? null, '', (array) ($json['meta'] ?? []));
        }

        // Two shapes: {"error": "words"} today, {"error": {"code", "message"}}
        // in the partner API's later phases; validation failures say "message".
        $error = $json['error'] ?? null;
        $message = is_array($error) ? (string) ($error['message'] ?? '') : (string) ($error ?? '');
        if ($message === '') {
            $message = (string) ($json['message'] ?? '');
        }

        Log::info('Panelica license: Central refused', ['path' => $path, 'status' => $response->status(), 'error' => $message]);

        return new CentralResponse(false, $response->status(), $json['data'] ?? null, $message !== '' ? $message : 'HTTP '.$response->status());
    }
}
