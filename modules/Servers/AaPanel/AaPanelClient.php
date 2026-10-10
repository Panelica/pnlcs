<?php

namespace Modules\Servers\AaPanel;

use App\Models\Server;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class AaPanelClient
{
    private const ACTIONS = [
        'get_service_info', 'get_account_list', 'get_package_list',
        'get_disk_list', 'create_account', 'modify_account', 'remove_account',
    ];

    public function __construct(private Server $server) {}

    /**
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    public function request(string $action, array $parameters = []): array
    {
        if (! in_array($action, self::ACTIONS, true)) {
            throw new AaPanelException('Unsupported aaPanel API action.');
        }
        $url = $this->endpoint($action);
        $parameters = $this->signed($parameters);
        try {
            $response = Http::asForm()->acceptJson()
                ->connectTimeout(10)->timeout(30)
                ->withOptions(['allow_redirects' => false, 'verify' => true])
                ->post($url, $parameters);
        } catch (\Throwable) {
            // Never expose transport exceptions containing credentials or retry a write.
            throw new AaPanelException('aaPanel connection failed. Check HTTPS, certificate trust, port and the API IP whitelist. The remote outcome may be unknown.', true);
        }

        return $this->decode($response);
    }

    private function endpoint(string $action): string
    {
        $host = trim((string) ($this->server->hostname ?: $this->server->ip_address));
        $ip = trim($host, '[]');
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $host = '['.$ip.']';
        } elseif (! filter_var($host, FILTER_VALIDATE_IP)
            && ! filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            throw new AaPanelException('Use an aaPanel hostname or IP address without a URL path.');
        }
        $port = $this->server->port ?? 8888;
        if (filter_var($port, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]) === false) {
            throw new AaPanelException('Invalid aaPanel API port.');
        }

        return "https://{$host}:{$port}/v2/virtual/{$action}.json";
    }

    /** @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    private function signed(array $parameters): array
    {
        $key = trim((string) $this->server->access_hash);
        if ($key === '') {
            throw new AaPanelException('Configure the aaPanel API secret in the server API Key field.');
        }
        $time = (string) (int) floor(microtime(true) * 1000);
        $parameters['request_time'] = $time;
        $parameters['request_token'] = md5($time.md5($key));

        return $parameters;
    }

    /** @return array<string, mixed> */
    private function decode(Response $response): array
    {
        if (! $response->successful()) {
            throw new AaPanelException('aaPanel API returned HTTP '.$response->status().'. Check the panel and API access.', ! in_array($response->status(), [400, 401, 403, 404, 405, 422, 429], true));
        }
        $data = $response->json();
        if (! is_array($data) || ! in_array($data['status'] ?? null, [0, '0'], true)) {
            throw new AaPanelException('aaPanel rejected the request or returned an invalid response. Check its Account logs and API permissions.', ! is_array($data) || ! in_array($data['status'] ?? null, [-1, '-1', false], true));
        }

        return $data;
    }

    public function assertReady(): void
    {
        $info = $this->request('get_service_info')['message'] ?? null;
        if (! is_array($info)
            || ! in_array($info['install_status'] ?? null, [2, '2'], true)
            || ! in_array($info['run_status'] ?? null, [1, '1'], true)) {
            throw new AaPanelException('Sub aaPanel must be installed, entitled and running before provisioning hosting accounts.');
        }
    }

    /**
     * Read a bounded, complete list. Malformed or changing pagination cannot prove absence.
     *
     * @param  array<string, mixed>  $parameters
     * @return list<array<string, mixed>>
     */
    public function listing(string $action, array $parameters = []): array
    {
        if (! in_array($action, ['get_account_list', 'get_package_list'], true)) {
            throw new AaPanelException('Unsupported aaPanel list action.');
        }
        $all = [];
        $seen = [];
        $expectedTotal = null;
        for ($page = 1; $page <= 25; $page++) {
            $body = $this->request($action, array_merge($parameters, ['p' => $page, 'rows' => 100]));
            [$rows, $total] = $this->listPage($body);
            $this->appendRows($action, $rows, $all, $seen);
            if ($page > 1 && $total !== $expectedTotal) {
                throw new AaPanelException('aaPanel list changed during pagination; retry the read.');
            }
            $expectedTotal = $total;
            if ($this->listComplete(count($all), count($rows), $total)) {
                return $all;
            }
        }

        throw new AaPanelException('aaPanel list exceeds the safe pagination limit; narrow the account search.');
    }

    /** @param array<string, mixed> $body
     * @return array{0: list<mixed>, 1: int|null}
     */
    private function listPage(array $body): array
    {
        $message = $body['message'] ?? null;
        $rows = is_array($message) ? ($message['list'] ?? null) : null;
        if (! is_array($rows) || ! array_is_list($rows) || count($rows) > 100) {
            throw new AaPanelException('aaPanel returned an invalid list. No account changes were made.');
        }
        $pagination = array_key_exists('page', $message) ? $message['page'] : [];
        if (! is_array($pagination)) {
            throw new AaPanelException('aaPanel returned invalid pagination.');
        }
        $total = null;
        if (array_key_exists('count', $pagination)) {
            $total = AaPanelValue::integer($pagination['count']);
        }

        return [$rows, $total];
    }

    /** @param list<mixed> $rows
     * @param  list<array<string, mixed>>  $all
     * @param  array<int|string, bool>  $seen
     */
    private function appendRows(string $action, array $rows, array &$all, array &$seen): void
    {
        $idField = $action === 'get_account_list' ? 'account_id' : 'package_id';
        $nameField = $action === 'get_account_list' ? 'username' : 'package_name';
        foreach ($rows as $row) {
            if (! is_array($row)) {
                throw new AaPanelException('aaPanel returned an invalid list entry.');
            }
            $id = AaPanelValue::integer($row[$idField] ?? null, 1);
            if (! is_string($row[$nameField] ?? null) || trim($row[$nameField]) === '') {
                throw new AaPanelException('aaPanel returned an invalid list identity.');
            }
            if (isset($seen[(string) $id])) {
                throw new AaPanelException('aaPanel repeated a list identity; refusing an incomplete result.');
            }
            $seen[(string) $id] = true;
            /** @var array<string, mixed> $row API records are JSON objects with named fields. */
            $all[] = $row;
        }
    }

    private function listComplete(int $read, int $pageSize, ?int $total): bool
    {
        if ($total === null) {
            return $pageSize < 100;
        }
        if ($read > $total || ($pageSize === 0 && $read < $total)) {
            throw new AaPanelException('aaPanel returned an incomplete or inconsistent list.');
        }

        return $read === $total;
    }
}
