<?php

namespace Modules\Servers\Proxmox;

use App\Models\Server;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;

/**
 * Talks to one Proxmox VE cluster.
 *
 * Signs in with an API token when there is one and with a user and password
 * otherwise. Every call returns a ProxmoxResult; nothing here throws for an
 * answer Proxmox gave, so callers can say exactly what went wrong.
 */
class ProxmoxClient
{
    public const DEFAULT_PORT = 8006;

    public function __construct(private readonly Server $server, private readonly string $host) {}

    public function server(): Server
    {
        return $this->server;
    }

    public function baseUrl(): string
    {
        $host = str_contains($this->host, ':') && ! str_starts_with($this->host, '[') ? "[{$this->host}]" : $this->host;

        return "https://{$host}:".($this->server->port ?: self::DEFAULT_PORT).'/api2/json';
    }

    /**
     * The Authorization header for the token, or null when the server signs in
     * with a password.
     *
     * Proxmox shows a new token as two things - "root@pam!billing" and a
     * secret - and wants them sent as PVEAPIToken=root@pam!billing=secret.
     * Operators pasted the secret alone, or the two with a space, and got
     * "refused the credentials" with no hint of the expected shape. Every
     * shape they could reasonably have copied is accepted here.
     */
    public static function tokenHeader(?string $username, ?string $secret): ?string
    {
        $secret = trim((string) $secret);
        $username = trim((string) $username);

        if ($secret === '') {
            return null;
        }

        if (str_starts_with($secret, 'PVEAPIToken=')) {
            return $secret;
        }

        // "root@pam!billing=secret" or "root@pam!billing secret"
        if (preg_match('/^([^\s=!]+@[^\s=!]+![^\s=]+)[\s=]+(\S+)$/', $secret, $m)) {
            return "PVEAPIToken={$m[1]}={$m[2]}";
        }

        // Token ID in the username field, secret in the key field.
        if (str_contains($username, '!')) {
            return "PVEAPIToken={$username}={$secret}";
        }

        return null;
    }

    /** The user or token the calls are made as, for messages and permission checks. */
    public function identity(): string
    {
        $header = self::tokenHeader($this->server->username, $this->server->access_hash);
        if ($header !== null) {
            return explode('=', substr($header, strlen('PVEAPIToken=')), 2)[0];
        }

        return $this->server->username ?: 'root@pam';
    }

    public function usesToken(): bool
    {
        return self::tokenHeader($this->server->username, $this->server->access_hash) !== null;
    }

    private function request(): PendingRequest|ProxmoxResult
    {
        // Form-encoded bodies work on every Proxmox release; JSON only on 7.2+.
        $client = Http::asForm()->acceptJson()->connectTimeout(10)->timeout(60);
        $client = $this->server->setting('verify_tls') ? $client : $client->withoutVerifying();

        if ($header = self::tokenHeader($this->server->username, $this->server->access_hash)) {
            return $client->withHeaders(['Authorization' => $header]);
        }

        $ticket = $this->ticket();
        if ($ticket instanceof ProxmoxResult) {
            return $ticket;
        }

        return $client->withHeaders(['CSRFPreventionToken' => $ticket['csrf']])
            ->withCookies(['PVEAuthCookie' => $ticket['ticket']], parse_url($this->baseUrl(), PHP_URL_HOST));
    }

    /** @return array{ticket: string, csrf: string}|ProxmoxResult */
    private function ticket(): array|ProxmoxResult
    {
        $key = 'proxmox_ticket_'.$this->server->id.'_'.md5((string) $this->server->username.'|'.$this->server->password);
        if ($cached = Cache::get($key)) {
            return $cached;
        }

        if (blank($this->server->password)) {
            return ProxmoxResult::failed('No API token and no password: nothing to sign in with.', 401);
        }

        try {
            $client = Http::asForm()->acceptJson()->connectTimeout(10)->timeout(20);
            $client = $this->server->setting('verify_tls') ? $client : $client->withoutVerifying();
            $response = $client->post($this->baseUrl().'/access/ticket', [
                'username' => $this->server->username ?: 'root@pam',
                'password' => $this->server->password,
            ]);
        } catch (ConnectionException $e) {
            return ProxmoxResult::failed('Could not reach Proxmox: '.$e->getMessage());
        }

        if (! $response->successful() || ! $response->json('data.ticket')) {
            return ProxmoxResult::failed('user name or password not accepted for '.($this->server->username ?: 'root@pam'), $response->status() ?: 401);
        }

        $ticket = [
            'ticket' => (string) $response->json('data.ticket'),
            'csrf' => (string) $response->json('data.CSRFPreventionToken'),
        ];
        // Tickets live two hours; renew well before that.
        Cache::put($key, $ticket, now()->addMinutes(90));

        return $ticket;
    }

    public function get(string $path, array $query = []): ProxmoxResult
    {
        return $this->send('get', $path, $query);
    }

    public function post(string $path, array $body = []): ProxmoxResult
    {
        return $this->send('post', $path, $body);
    }

    public function put(string $path, array $body = []): ProxmoxResult
    {
        return $this->send('put', $path, $body);
    }

    public function delete(string $path, array $query = []): ProxmoxResult
    {
        return $this->send('delete', $path, $query);
    }

    private function send(string $method, string $path, array $payload): ProxmoxResult
    {
        $request = $this->request();
        if ($request instanceof ProxmoxResult) {
            return $request;
        }

        $url = $this->baseUrl().'/'.ltrim($path, '/');

        try {
            $response = match ($method) {
                // DELETE takes its parameters in the query string.
                'delete' => $request->delete($payload ? $url.'?'.http_build_query($payload) : $url),
                'get' => $request->get($url, $payload),
                default => $request->{$method}($url, $payload),
            };
        } catch (ConnectionException $e) {
            return ProxmoxResult::failed('Could not reach Proxmox: '.$e->getMessage());
        }

        return $this->result($response);
    }

    private function result(Response $response): ProxmoxResult
    {
        if ($response->successful()) {
            return new ProxmoxResult(true, $response->status(), $response->json('data'));
        }

        $parts = [];
        $reason = trim((string) $response->reason());
        if ($reason !== '' && ! in_array($reason, ['Internal Server Error', 'Bad Request', 'Forbidden', 'Unauthorized', 'Not Found'], true)) {
            $parts[] = $reason;
        }
        $errors = $response->json('errors');
        if (is_array($errors) && $errors !== []) {
            foreach ($errors as $field => $message) {
                $parts[] = is_string($field) ? "{$field}: ".trim((string) $message) : trim((string) $message);
            }
        } elseif (is_string($message = $response->json('message')) && $message !== '') {
            $parts[] = trim($message);
        }

        if ($parts === []) {
            $parts[] = match ($response->status()) {
                401 => 'Proxmox refused the credentials.',
                403 => 'Permission check failed.',
                default => "HTTP {$response->status()}",
            };
        }

        return new ProxmoxResult(false, $response->status(), null, implode('; ', array_unique($parts)));
    }

    /**
     * Wait for a background task (clone, create, stop...) to finish.
     *
     * Proxmox answers most changes at once with a task id and does the work
     * afterwards; the answer says nothing about whether the work succeeded.
     */
    public function waitForTask(string $node, mixed $upid, int $timeout = 300): ProxmoxResult
    {
        if (! is_string($upid) || ! str_starts_with($upid, 'UPID:')) {
            // Nothing to wait for: the call finished synchronously.
            return new ProxmoxResult(true, 200, $upid);
        }

        $deadline = time() + $timeout;
        $path = 'nodes/'.rawurlencode($node).'/tasks/'.rawurlencode($upid).'/status';

        do {
            $status = $this->get($path);
            if ($status->ok && ($status->data['status'] ?? null) === 'stopped') {
                $exit = (string) ($status->data['exitstatus'] ?? '');

                // "OK" or "WARNINGS: n" is a finished task; anything else is its error.
                return str_starts_with($exit, 'OK') || str_starts_with($exit, 'WARNINGS')
                    ? new ProxmoxResult(true, 200, $upid)
                    : ProxmoxResult::failed($this->taskError($node, $upid, $exit));
            }
            Sleep::for(2)->seconds();
        } while (time() < $deadline);

        Log::warning('Proxmox task still running when the wait ran out', ['upid' => $upid, 'server' => $this->server->id]);

        return ProxmoxResult::failed("The task is still running on Proxmox after {$timeout} seconds ({$upid}).", 504);
    }

    /** The last lines of a failed task's log say why it failed. */
    private function taskError(string $node, string $upid, string $exit): string
    {
        $log = $this->get('nodes/'.rawurlencode($node).'/tasks/'.rawurlencode($upid).'/log', ['start' => 0, 'limit' => 500]);
        $lines = collect($log->list())->pluck('t')->filter()->reject(fn ($t) => $t === 'TASK OK')->take(-3)->implode(' | ');

        return trim($exit.($lines !== '' && ! str_contains($lines, $exit) ? " ({$lines})" : ''));
    }
}
