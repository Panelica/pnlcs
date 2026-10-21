<?php

namespace Modules\Servers\PanelicaLicense;

use App\Models\Server;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Panelica Central's partner API (/api/reseller/v1), signed in with the
 * partner's API key (rk_...), which the server record keeps encrypted in
 * access_hash.
 *
 * The key never reaches a log line or a message: errors carry Central's own
 * words and the HTTP status only.
 */
final class CentralClient
{
    private const API = '/api/reseller/v1/';

    private function __construct(private Server $server) {}

    public static function for(Server $server): self
    {
        return new self($server);
    }

    /**
     * https://<hostname>[:port]. A private or loopback address (a Central
     * someone runs for testing) is spoken to over plain http; the real one is
     * a public name and always https.
     */
    public function baseUrl(): string
    {
        $host = trim((string) ($this->server->hostname ?: $this->server->ip_address));
        $local = $host === 'localhost'
            || (filter_var($host, FILTER_VALIDATE_IP) && ! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE));
        $scheme = $local ? 'http' : 'https';
        $port = (int) $this->server->port;
        $default = $scheme === 'https' ? 443 : 80;
        $hostPart = str_contains($host, ':') && ! str_starts_with($host, '[') ? '['.$host.']' : $host;

        return $scheme.'://'.$hostPart.($port > 0 && $port !== $default ? ':'.$port : '');
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

    /**
     * The script Central serves at /install/{code}/{ref}: the official
     * installer, unchanged, plus the report that ties the server to this
     * order. Null when Central did not answer with that script - an unknown
     * or disabled code is answered with a redirect to the plain installer,
     * which would install a server nobody can tie to the order.
     */
    public function installScript(string $code, string $ref): ?string
    {
        try {
            $response = Http::withoutRedirecting()
                ->timeout(20)
                ->accept('text/plain')
                ->withUserAgent('PNLCS')
                ->get($this->baseUrl().'/install/'.rawurlencode($code).'/'.rawurlencode($ref));
        } catch (\Throwable $e) {
            Log::warning('Panelica license: Central did not serve the install script', ['server_id' => $this->server->id, 'error' => $e->getMessage()]);

            return null;
        }

        $body = (string) $response->body();

        if ($response->status() !== 200 || ! str_starts_with($body, '#!') || ! str_contains($body, 'panelica_installer')) {
            Log::warning('Panelica license: Central did not serve the install script', ['server_id' => $this->server->id, 'status' => $response->status()]);

            return null;
        }

        return $body;
    }

    private function request(): PendingRequest
    {
        return Http::withToken((string) $this->server->access_hash)
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
        if (blank($this->server->access_hash)) {
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
                default => $request->get($url, $payload),
            };
        } catch (\Throwable $e) {
            Log::warning('Panelica license: Central could not be reached', ['server_id' => $this->server->id, 'path' => $path, 'error' => $e->getMessage()]);

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

        Log::info('Panelica license: Central refused', ['server_id' => $this->server->id, 'path' => $path, 'status' => $response->status(), 'error' => $message]);

        return new CentralResponse(false, $response->status(), $json['data'] ?? null, $message !== '' ? $message : 'HTTP '.$response->status());
    }
}
