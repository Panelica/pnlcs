<?php

namespace Modules\Registrars\OVH;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Minimal client for the OVHcloud REST API (api.ovh.com).
 *
 * Every request is signed: the signature is "$1$" followed by the SHA-1 of
 *   applicationSecret + consumerKey + METHOD + URL + BODY + timestamp
 * and carried in the X-Ovh-Signature header alongside X-Ovh-Application,
 * X-Ovh-Consumer and X-Ovh-Timestamp. The URL is the full endpoint URL,
 * including the query string; the body is the exact JSON sent (empty for
 * GET/DELETE).
 */
class OvhClient
{
    protected const ENDPOINTS = [
        'eu' => 'https://eu.api.ovh.com/1.0',
        'ca' => 'https://ca.api.ovh.com/1.0',
        'us' => 'https://api.us.ovhcloud.com/1.0',
    ];

    protected string $endpoint;

    public function __construct(
        protected string $applicationKey,
        protected string $applicationSecret,
        protected string $consumerKey,
        string $region = 'eu',
    ) {
        $this->endpoint = self::ENDPOINTS[$region] ?? self::ENDPOINTS['eu'];
    }

    public function get(string $path, array $query = []): mixed
    {
        return $this->request('GET', $path, $query);
    }

    /** @param array<mixed> $body */
    public function post(string $path, array $body = []): mixed
    {
        return $this->request('POST', $path, [], $body);
    }

    /** @param array<mixed> $body */
    public function put(string $path, array $body = []): mixed
    {
        return $this->request('PUT', $path, [], $body);
    }

    public function delete(string $path): mixed
    {
        return $this->request('DELETE', $path);
    }

    protected function request(string $method, string $path, array $query = [], ?array $body = null): mixed
    {
        $url = $this->endpoint.$path;
        if ($query !== []) {
            $url .= '?'.http_build_query($query);
        }

        $bodyText = $body === null ? '' : (string) json_encode($body, JSON_UNESCAPED_SLASHES);
        $now = time();
        // AS + CK + METHOD + URL + BODY + TSTAMP, joined with "+", hashed SHA-1.
        $toSign = $this->applicationSecret.'+'.$this->consumerKey.'+'.$method.'+'.$url.'+'.$bodyText.'+'.$now;
        $signature = '$1$'.sha1($toSign);

        $http = Http::withHeaders([
            'X-Ovh-Application' => $this->applicationKey,
            'X-Ovh-Consumer' => $this->consumerKey,
            'X-Ovh-Timestamp' => (string) $now,
            'X-Ovh-Signature' => $signature,
        ]);

        try {
            $response = match ($method) {
                'GET' => $http->get($url),
                'DELETE' => $http->delete($url),
                'POST' => $http->withBody($bodyText, 'application/json')->post($url),
                'PUT' => $http->withBody($bodyText, 'application/json')->put($url),
                default => throw new RuntimeException('OVH: unsupported method '.$method),
            };
        } catch (\Throwable $e) {
            throw new RuntimeException('OVH: could not reach the API — '.$e->getMessage());
        }

        if ($response->failed()) {
            throw new RuntimeException('OVH HTTP '.$response->status().': '.$this->errorMessage($response->json()));
        }

        return $response->json();
    }

    /** @param array<mixed>|null $json */
    protected function errorMessage(?array $json): string
    {
        if (! $json) {
            return 'unknown error';
        }

        return (string) ($json['message'] ?? $json['error'] ?? 'unknown error');
    }
}
