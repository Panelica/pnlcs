<?php

namespace Modules\Servers\Plesk;

use App\Models\Server;
use App\Models\Service;
use App\Support\ServerAddress;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Servers\AbstractServerModule;

/**
 * Plesk Obsidian REST API (/api/v2) module.
 *
 * Endpoints verified against the official openapi.yml:
 *  - POST   /clients                      create customer
 *  - POST   /domains                      create subscription (hosting_type=virtual,
 *                                         hosting_settings.ftp_login/ftp_password REQUIRED,
 *                                         owner_client assigns the customer)
 *  - PUT    /clients/{id}/suspend        suspend account (services without a subscription id)
 *  - PUT    /clients/{id}/activate       reactivate account (idem)
 *  - DELETE /clients/{id}                 remove customer (cascades subscriptions)
 *
 * The XML API (agent.php) covers what REST does not: service plans, the list
 * of existing subscriptions with their owners, and suspending, reactivating
 * or removing one subscription (webspace set/del) without touching the
 * customer's others.
 *  - PUT    /clients/{id}                 update (password, ...)
 *  - PUT    /domains/{id}                 update (plan change)
 *  - GET    /clients/{id}/statistics      disk/traffic usage
 */
class PleskModule extends AbstractServerModule
{
    /** Marks a reseller plan in a product's package, and a reseller in an account id. */
    public const RESELLER_PREFIX = 'reseller:';

    public function getModuleName(): string
    {
        return 'plesk';
    }

    public function getConfigFields(): array
    {
        return [
            ['name' => 'api_key', 'label' => 'Plesk API Secret Key', 'type' => 'password'],
        ];
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    private function baseUrl(Server $server): string
    {
        $port = $server->port ?: 8443;

        return "https://{$this->serverHost($server)}:{$port}/api/v2";
    }

    /**
     * Plesk authenticates with an API key or with an administrator login, and
     * the server form asks for a username and a password while calling the key
     * "Optional". Sending only the key meant an operator who filled the form in
     * the ordinary way was never authenticated at all.
     *
     * A key is tied to the address it was issued for, so the call stays on the
     * address family of the recorded IP (ServerAddress::familyOptions).
     */
    private function http(Server $server)
    {
        $request = Http::withoutVerifying()
            ->withOptions(ServerAddress::familyOptions(trim((string) $server->ip_address)))
            ->withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
            ->timeout(30);

        $key = trim((string) $server->access_hash);

        if ($key !== '') {
            return $request->withHeaders(['X-API-Key' => $key]);
        }

        return $request->withBasicAuth(
            (string) ($server->username ?: 'admin'),
            (string) $server->password
        );
    }

    /**
     * A password Plesk will take.
     *
     * The API documents a client password as 5 to 14 characters; this module
     * generated 21, which Plesk is entitled to refuse outright.
     */
    private function generatePassword(): string
    {
        $lower = 'abcdefghijkmnopqrstuvwxyz';
        $upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $digits = '23456789';
        $alphabet = $lower.$upper.$digits;

        $password = [
            $lower[random_int(0, strlen($lower) - 1)],
            $upper[random_int(0, strlen($upper) - 1)],
            $digits[random_int(0, strlen($digits) - 1)],
        ];

        for ($i = 0; $i < 9; $i++) {
            $password[] = $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        shuffle($password);

        return implode('', $password);
    }

    /**
     * The service plans this Plesk server offers, for the product form.
     *
     * The REST API has no plans endpoint at all - the list comes from the XML
     * API, which is where WHMCS reads it too.
     *
     * @return array<int, array{id: string, name: string}>
     */
    public function listPackages(Server $server): array
    {
        $xml = $this->xmlApi($server, '<service-plan><get><filter/></get></service-plan>');

        if (! $xml) {
            return [];
        }

        $plans = [];

        foreach ($xml->xpath('//service-plan/get/result') ?: [] as $result) {
            $name = trim((string) $result->name);

            if ($name !== '') {
                $plans[$name] = ['id' => $name, 'name' => $name];
            }
        }

        ksort($plans);

        // Reseller plans follow, marked so a product sold with one creates a
        // reseller account instead of a customer and subscription.
        $resellerPlans = [];
        foreach ($this->resellerPlans($server) as $name) {
            $resellerPlans[$name] = ['id' => self::RESELLER_PREFIX.$name, 'name' => 'Reseller: '.$name];
        }
        ksort($resellerPlans);

        return array_values($plans + $resellerPlans);
    }

    /**
     * The reseller plans on the server by guid.
     *
     * @return array<string, string>
     */
    private function resellerPlans(Server $server): array
    {
        $xml = $this->xmlApi($server, '<reseller-plan><get><filter><all/></filter></get></reseller-plan>');

        $plans = [];
        foreach ($xml ? ($xml->xpath('//reseller-plan/get/result[status="ok"]') ?: []) : [] as $plan) {
            $name = trim((string) $plan->name);
            if ($name !== '') {
                $plans[(string) $plan->guid] = $name;
            }
        }

        return $plans;
    }

    /**
     * The reseller plan a package names ("reseller:<plan>"), or null for a
     * service plan.
     */
    private static function resellerPlanOf(?string $package): ?string
    {
        $package = (string) $package;

        return str_starts_with($package, self::RESELLER_PREFIX) ? substr($package, strlen(self::RESELLER_PREFIX)) : null;
    }

    private function isReseller(Service $service): bool
    {
        return ($this->getModuleData($service)['plesk_account_type'] ?? null) === 'reseller';
    }

    private static function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /**
     * One call to the XML API (agent.php), which reads what the REST API does
     * not: service plans, every subscription with its owner, and the status of
     * a single subscription.
     *
     * The XML API takes a secret key in the KEY header; X-API-Key is the REST
     * API's header, which agent.php does not read. A failure returns null.
     */
    private function xmlApi(Server $server, string $operations): ?\SimpleXMLElement
    {
        $key = trim((string) $server->access_hash);

        $headers = $key !== ''
            ? ['KEY' => $key]
            : [
                'HTTP_AUTH_LOGIN' => (string) ($server->username ?: 'admin'),
                'HTTP_AUTH_PASSWD' => (string) $server->password,
            ];

        try {
            $port = (int) ($server->port ?: 8443);
            $url = "https://{$this->serverHost($server)}:{$port}/enterprise/control/agent.php";

            $response = Http::withoutVerifying()
                ->withOptions(ServerAddress::familyOptions(trim((string) $server->ip_address)))
                ->withHeaders($headers)
                ->timeout(30)
                ->withBody("<packet>{$operations}</packet>", 'text/xml')
                ->post($url);
        } catch (\Throwable $e) {
            Log::warning('Plesk XML API call failed', ['server' => $server->id, 'error' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        return @simplexml_load_string($response->body()) ?: null;
    }

    /**
     * Whether every result of an XML API answer came back ok.
     */
    private function xmlOk(?\SimpleXMLElement $xml): bool
    {
        if (! $xml || $xml->xpath('//system/status')) {
            return false;
        }

        $statuses = $xml->xpath('//result/status') ?: [];

        return $statuses !== [] && collect($statuses)->every(fn ($status) => (string) $status === 'ok');
    }

    private function xmlError(?\SimpleXMLElement $xml): string
    {
        $error = $xml ? ($xml->xpath('//errtext') ?: []) : [];

        return $error ? (string) $error[0] : 'Plesk did not answer.';
    }

    /**
     * Customers and resellers by id: login, name and email.
     *
     * The reseller object names its dataset gen-info where the customer one
     * says gen_info, and wants an explicit <all/> filter: asked any other way,
     * Plesk rejects the whole packet and every subscription reads as the
     * administrator's.
     *
     * @return array<string, array{login: string, name: string, email: string}>
     */
    private function owners(Server $server): array
    {
        $xml = $this->xmlApi($server, '<customer><get><filter/><dataset><gen_info/></dataset></get></customer>'
            .'<reseller><get><filter><all/></filter><dataset><gen-info/></dataset></get></reseller>');

        $owners = [];
        foreach ($xml ? ($xml->xpath('//get/result[status="ok"]') ?: []) : [] as $result) {
            $info = $result->data->gen_info ?? $result->data->{'gen-info'};
            $owners[(string) $result->id] = [
                'login' => (string) $info->login,
                'name' => trim((string) $info->cname) ?: trim((string) $info->pname),
                'email' => (string) $info->email,
            ];
        }

        return $owners;
    }

    /**
     * The subscriptions that already exist on the server, so an operator moving
     * customers in can link a billing service to the real subscription instead
     * of creating a new one. The id is the subscription's; the label carries
     * the domain and who owns it on the panel. A failure returns an empty list.
     *
     * @return array<int, array{id: string, username: string, email: string, status: string}>
     */
    public function listAccounts(Server $server): array
    {
        $xml = $this->xmlApi($server, '<webspace><get><filter/><dataset><gen_info/></dataset></get></webspace>');

        if (! $xml) {
            return [];
        }

        $owners = $this->owners($server);
        $accounts = [];

        foreach ($xml->xpath('//webspace/get/result[status="ok"]') ?: [] as $result) {
            $info = $result->data->gen_info;
            $owner = $owners[(string) $info->{'owner-id'}] ?? null;

            $accounts[] = [
                'id' => (string) $result->id,
                'username' => (string) $info->name.($owner ? ' · '.($owner['name'] ?: $owner['login']) : ''),
                'email' => $owner['email'] ?? '',
                'status' => (int) $info->status === 0 ? 'active' : 'suspended',
            ];
        }

        usort($accounts, fn ($a, $b) => strcasecmp($a['username'], $b['username']));

        return $accounts;
    }

    /**
     * Every subscription on the server with what an import needs to bill it:
     * the domain, its owner, the service plan it follows and the day it was
     * created (the anniversary a yearly renewal usually falls on). Read for
     * the server sync page; a failure returns an empty list.
     *
     * Reseller accounts follow the subscriptions, with an id of "reseller:<id>"
     * and the reseller plan as their package.
     *
     * @return list<array{id: string, domain: string, owner: string, email: string, status: string, plan: ?string, created: ?string, kind?: string, package?: string}>
     */
    public function listAccountDetails(Server $server): array
    {
        $xml = $this->xmlApi($server, '<webspace><get><filter/><dataset><gen_info/><subscriptions/></dataset></get></webspace>'
            .'<service-plan><get><filter/></get></service-plan>');

        $results = $xml ? ($xml->xpath('//webspace/get/result[status="ok"]') ?: []) : [];
        if ($results === []) {
            // A panel that does not take the subscriptions dataset still lists them, without their plan.
            $xml = $this->xmlApi($server, '<webspace><get><filter/><dataset><gen_info/></dataset></get></webspace>');
            $results = $xml ? ($xml->xpath('//webspace/get/result[status="ok"]') ?: []) : [];
        }

        $plans = [];
        foreach ($xml ? ($xml->xpath('//service-plan/get/result[status="ok"]') ?: []) : [] as $plan) {
            $plans[(string) $plan->guid] = trim((string) $plan->name);
        }

        $owners = $results === [] ? [] : $this->owners($server);
        $accounts = [];

        foreach ($results as $result) {
            $info = $result->data->gen_info;
            $owner = $owners[(string) $info->{'owner-id'}] ?? null;
            $planGuid = (string) ($result->data->subscriptions->subscription->plan->{'plan-guid'} ?? '');
            $created = (string) $info->cr_date;

            $accounts[] = [
                'id' => (string) $result->id,
                'domain' => (string) $info->name,
                'owner' => $owner ? ($owner['name'] ?: $owner['login']) : '',
                'email' => $owner['email'] ?? '',
                'status' => (int) $info->status === 0 ? 'active' : 'suspended',
                'plan' => $plans[$planGuid] ?? null,
                'created' => preg_match('/^\d{4}-\d{2}-\d{2}/', $created) ? substr($created, 0, 10) : null,
            ];
        }

        usort($accounts, fn ($a, $b) => strcasecmp($a['domain'], $b['domain']));

        return array_merge($accounts, $this->resellerAccounts($server));
    }

    /**
     * The reseller accounts on the server, shaped like the subscriptions.
     *
     * @return list<array{id: string, domain: string, owner: string, email: string, status: string, plan: ?string, created: ?string, kind: string, package: ?string}>
     */
    private function resellerAccounts(Server $server): array
    {
        $xml = $this->xmlApi($server, '<reseller><get><filter><all/></filter><dataset><gen-info/><subscriptions/></dataset></get></reseller>');
        $results = $xml ? ($xml->xpath('//reseller/get/result[status="ok"]') ?: []) : [];
        $plans = $results === [] ? [] : $this->resellerPlans($server);

        $accounts = [];
        foreach ($results as $result) {
            $info = $result->data->{'gen-info'};
            $plan = $plans[(string) ($result->data->subscriptions->subscription->plan->{'plan-guid'} ?? '')] ?? null;
            $created = (string) $info->{'cr-date'};

            $accounts[] = [
                'id' => self::RESELLER_PREFIX.$result->id,
                'domain' => (string) $info->login,
                'owner' => trim((string) $info->cname) ?: trim((string) $info->pname),
                'email' => (string) $info->email,
                'status' => (int) $info->status === 0 ? 'active' : 'suspended',
                'plan' => $plan,
                'created' => preg_match('/^\d{4}-\d{2}-\d{2}/', $created) ? substr($created, 0, 10) : null,
                'kind' => 'reseller',
                'package' => $plan === null ? null : self::RESELLER_PREFIX.$plan,
            ];
        }

        usort($accounts, fn ($a, $b) => strcasecmp($a['domain'], $b['domain']));

        return $accounts;
    }

    /**
     * The subscription a service is linked to, for the "already linked" check.
     */
    public function linkedPanelAccount(Service $service): ?string
    {
        if ($this->isReseller($service)) {
            $id = $this->getClientId($service);

            return $id ? self::RESELLER_PREFIX.$id : null;
        }

        return $this->getDomainId($service);
    }

    /**
     * Link a billing service to a subscription that already runs on the server.
     *
     * Records the subscription and, when a customer or reseller owns it, that
     * owner: what suspend, terminate and the password change address. Nothing
     * on the panel changes. A subscription another live service manages is not
     * linked twice.
     *
     * @return array{success: bool, message: string, data: array<string, mixed>}
     */
    public function linkAccount(Service $service, string $subscriptionId): array
    {
        $server = $this->getServer($service);
        if ($server && str_starts_with($subscriptionId, self::RESELLER_PREFIX)) {
            return $this->linkReseller($service, $server, substr($subscriptionId, strlen(self::RESELLER_PREFIX)));
        }
        if (! $server || ! ctype_digit($subscriptionId)) {
            return $this->buildResult(false, 'Missing server or subscription.');
        }

        $taken = Service::where('server_id', $server->id)->where('id', '!=', $service->id)
            ->whereNotIn('status', ['terminated', 'cancelled', 'fraud'])->get(['id', 'module_data'])
            ->first(fn ($other) => (string) $this->linkedPanelAccount($other) === $subscriptionId);
        if ($taken) {
            return $this->buildResult(false, "Service #{$taken->id} already manages this Plesk subscription.");
        }

        $xml = $this->xmlApi($server, "<webspace><get><filter><id>{$subscriptionId}</id></filter><dataset><gen_info/></dataset></get></webspace>");
        $found = $xml ? ($xml->xpath('//webspace/get/result[status="ok"]') ?: []) : [];
        if (! $found) {
            return $this->buildResult(false, 'Plesk has no subscription #'.$subscriptionId.'.');
        }

        $info = $found[0]->data->gen_info;
        $ownerId = (string) $info->{'owner-id'};
        $owner = $this->owners($server)[$ownerId] ?? null;

        // A subscription the administrator owns has no customer to address.
        $this->setModuleData($service, array_filter([
            'plesk_domain_id' => $subscriptionId,
            'plesk_client_id' => $owner ? $ownerId : null,
        ]));

        $service->forceFill(array_filter([
            'username' => $owner['login'] ?? null,
            'domain' => $service->domain ?: (string) $info->name,
        ]))->save();

        $this->logAction($service, 'link', ['success' => true]);

        return $this->buildResult(true, 'Linked to Plesk subscription '.$info->name.'.');
    }

    /**
     * Link a billing service to a reseller account that already exists. The
     * service then acts on the reseller: suspending it suspends the reseller
     * and, with it, everything the reseller hosts.
     *
     * @return array{success: bool, message: string, data: array<string, mixed>}
     */
    private function linkReseller(Service $service, Server $server, string $resellerId): array
    {
        if (! ctype_digit($resellerId)) {
            return $this->buildResult(false, 'Missing reseller.');
        }

        $taken = Service::where('server_id', $server->id)->where('id', '!=', $service->id)
            ->whereNotIn('status', ['terminated', 'cancelled', 'fraud'])->get(['id', 'module_data'])
            ->first(fn ($other) => (string) $this->linkedPanelAccount($other) === self::RESELLER_PREFIX.$resellerId);
        if ($taken) {
            return $this->buildResult(false, "Service #{$taken->id} already manages this Plesk reseller.");
        }

        $xml = $this->xmlApi($server, "<reseller><get><filter><id>{$resellerId}</id></filter><dataset><gen-info/></dataset></get></reseller>");
        $found = $xml ? ($xml->xpath('//reseller/get/result[status="ok"]') ?: []) : [];
        if (! $found) {
            return $this->buildResult(false, 'Plesk has no reseller #'.$resellerId.'.');
        }

        $login = (string) $found[0]->data->{'gen-info'}->login;
        $this->setModuleData($service, ['plesk_client_id' => $resellerId, 'plesk_account_type' => 'reseller']);
        $service->forceFill(['username' => $login])->save();

        $this->logAction($service, 'link', ['success' => true]);

        return $this->buildResult(true, 'Linked to Plesk reseller '.$login.'.');
    }

    /**
     * Set a reseller's status: 0 active, 16 suspended by the administrator.
     *
     * @return array{ok: bool, message: string}
     */
    private function setResellerStatus(Server $server, string $resellerId, int $status): array
    {
        $id = (int) $resellerId;
        $xml = $this->xmlApi($server, "<reseller><set><filter><id>{$id}</id></filter><values><gen-info><status>{$status}</status></gen-info></values></set></reseller>");

        return $this->xmlOk($xml) ? ['ok' => true, 'message' => ''] : ['ok' => false, 'message' => $this->xmlError($xml)];
    }

    /**
     * Set one subscription's status: 0 active, 16 suspended by the administrator.
     *
     * @return array{ok: bool, message: string}
     */
    private function setSubscriptionStatus(Server $server, string $domainId, int $status): array
    {
        $id = (int) $domainId;
        $xml = $this->xmlApi($server, "<webspace><set><filter><id>{$id}</id></filter><values><gen_setup><status>{$status}</status></gen_setup></values></set></webspace>");

        return $this->xmlOk($xml) ? ['ok' => true, 'message' => ''] : ['ok' => false, 'message' => $this->xmlError($xml)];
    }

    private function getClientId(Service $service): ?string
    {
        return $this->getModuleData($service)['plesk_client_id'] ?? null;
    }

    private function getDomainId(Service $service): ?string
    {
        $data = $this->getModuleData($service);

        // plesk_webspace_id is the legacy key from the previous module version
        return $data['plesk_domain_id'] ?? $data['plesk_webspace_id'] ?? null;
    }

    /**
     * The subscription id when it is one Plesk issued (numeric); otherwise the
     * service is addressed through its customer, as before.
     */
    private function subscriptionId(Service $service): ?string
    {
        $id = (string) $this->getDomainId($service);

        return ctype_digit($id) ? $id : null;
    }

    private function errorMessage($response): string
    {
        return $response->json('message') ?? $response->json('errors.0.message') ?? $response->body();
    }

    // -------------------------------------------------------------------------
    // Interface methods
    // -------------------------------------------------------------------------

    public function create(Service $service): array
    {
        $server = $this->getServer($service);
        if (! $server) {
            return $this->buildResult(false, 'No server assigned to this service.');
        }

        $resellerPlan = self::resellerPlanOf($this->getRemotePackage($service));
        if ($resellerPlan !== null) {
            return $this->createReseller($service, $server, $resellerPlan);
        }

        $client = $service->client;
        $domain = $service->domain ?: '';
        if (! $client || ! $domain) {
            return $this->buildResult(false, 'Service is missing client or domain.');
        }

        // Plesk login: derived from the domain, must be unique on the server
        $login = preg_replace('/[^a-z0-9]/', '', strtolower(explode('.', $domain)[0]));
        $login = substr(ltrim($login, '0123456789') ?: 'u'.$service->id, 0, 16).$service->id;

        $password = $service->password ?: $this->generatePassword();
        $planName = $this->getRemotePackage($service);

        $base = $this->baseUrl($server);
        $http = $this->http($server);

        // Step 1 – create customer
        $clientResp = $http->post("{$base}/clients", [
            'name' => trim($client->first_name.' '.$client->last_name) ?: $login,
            'login' => $login,
            'password' => $password,
            'email' => $client->email ?? '',
            'type' => 'customer',
        ]);

        if (! $clientResp->successful()) {
            Log::error('Plesk create client failed', ['body' => $clientResp->body()]);

            return $this->buildResult(false, 'Failed to create Plesk client: '.$this->errorMessage($clientResp));
        }

        $clientId = $clientResp->json('id');

        // Step 2 – create the subscription: POST /domains with virtual hosting.
        // ftp_login + ftp_password are REQUIRED when creating a subscription.
        $payload = [
            'name' => $domain,
            'hosting_type' => 'virtual',
            'hosting_settings' => [
                'ftp_login' => $login,
                'ftp_password' => $password,
            ],
            'owner_client' => ['id' => $clientId],
        ];
        if ($planName) {
            $payload['plan'] = ['name' => $planName];
        }

        $domainResp = $http->post("{$base}/domains", $payload);

        if (! $domainResp->successful()) {
            // Rollback: delete the client we just created
            Log::error('Plesk create domain failed - rolling back client', ['body' => $domainResp->body()]);
            $http->delete("{$base}/clients/{$clientId}");

            return $this->buildResult(false, 'Failed to create Plesk subscription: '.$this->errorMessage($domainResp));
        }

        $domainId = $domainResp->json('id');

        $this->setModuleData($service, [
            'plesk_client_id' => $clientId,
            'plesk_domain_id' => $domainId,
        ]);
        $service->update(['username' => $login, 'password' => $password]);

        $this->logAction($service, 'create', ['success' => true]);

        return $this->buildResult(true, 'Plesk account created successfully.', [
            'plesk_client_id' => $clientId,
            'plesk_domain_id' => $domainId,
        ]);
    }

    /**
     * Create a reseller account on a reseller plan. A reseller has no domain
     * of its own: it creates its customers and their subscriptions itself.
     *
     * @return array{success: bool, message: string, data: array<string, mixed>}
     */
    private function createReseller(Service $service, Server $server, string $plan): array
    {
        $client = $service->client;
        if (! $client) {
            return $this->buildResult(false, 'Service is missing its client.');
        }

        $base = $service->domain ? explode('.', $service->domain)[0] : ($client->company_name ?: $client->first_name.$client->last_name);
        $login = preg_replace('/[^a-z0-9]/', '', strtolower((string) $base));
        $login = substr(ltrim($login, '0123456789') ?: 'r', 0, 14).$service->id;
        $password = $service->password ?: $this->generatePassword();
        $person = trim($client->first_name.' '.$client->last_name) ?: $login;

        $xml = $this->xmlApi($server, '<reseller><add><gen-info>'
            .'<cname>'.self::xml((string) $client->company_name).'</cname>'
            .'<pname>'.self::xml($person).'</pname>'
            .'<login>'.self::xml($login).'</login>'
            .'<passwd>'.self::xml($password).'</passwd>'
            .'<status>0</status>'
            .'<email>'.self::xml((string) $client->email).'</email>'
            .'</gen-info><plan-name>'.self::xml($plan).'</plan-name></add></reseller>');

        $id = $xml ? (string) ($xml->xpath('//reseller/add/result[status="ok"]/id')[0] ?? '') : '';
        if ($id === '') {
            $result = $this->buildResult(false, 'Failed to create Plesk reseller: '.$this->xmlError($xml));
            $this->logAction($service, 'create', $result);

            return $result;
        }

        $this->setModuleData($service, ['plesk_client_id' => $id, 'plesk_account_type' => 'reseller']);
        $service->update(['username' => $login, 'password' => $password]);

        $this->logAction($service, 'create', ['success' => true]);

        return $this->buildResult(true, 'Plesk reseller created successfully.', ['plesk_client_id' => $id]);
    }

    /**
     * Suspend the subscription this service bills for.
     *
     * Suspending the Plesk customer instead took every other subscription of
     * that customer down with it: one unpaid domain suspended all their sites.
     * A service recorded before the subscription id was kept still suspends
     * the customer, as it always did.
     */
    public function suspend(Service $service, string $reason = ''): array
    {
        return $this->toggle($service, 'suspend');
    }

    public function unsuspend(Service $service): array
    {
        return $this->toggle($service, 'unsuspend');
    }

    /**
     * @return array{success: bool, message: string, data: array<string, mixed>}
     */
    private function toggle(Service $service, string $action): array
    {
        $server = $this->getServer($service);
        $domainId = $this->subscriptionId($service);
        $clientId = $this->getClientId($service);
        $suspend = $action === 'suspend';

        if ($server && $clientId && $this->isReseller($service)) {
            $set = $this->setResellerStatus($server, $clientId, $suspend ? 16 : 0);
            $result = $this->buildResult($set['ok'], $set['ok'] ? ($suspend ? 'Reseller suspended.' : 'Reseller unsuspended.') : $set['message']);
        } elseif ($server && $domainId) {
            $set = $this->setSubscriptionStatus($server, $domainId, $suspend ? 16 : 0);
            $result = $this->buildResult($set['ok'], $set['ok'] ? ($suspend ? 'Subscription suspended.' : 'Subscription unsuspended.') : $set['message']);
        } elseif ($server && $clientId) {
            $resp = $this->http($server)->put("{$this->baseUrl($server)}/clients/{$clientId}/".($suspend ? 'suspend' : 'activate'));
            $result = $this->buildResult($resp->successful(), $resp->successful() ? ($suspend ? 'Account suspended.' : 'Account unsuspended.') : $this->errorMessage($resp));
        } else {
            return $this->buildResult(false, 'Missing server or Plesk subscription ID.');
        }

        $this->logAction($service, $action, $result);

        return $result;
    }

    /**
     * Remove the subscription this service bills for, and the Plesk customer
     * only once nothing else of theirs is left on the server.
     *
     * Deleting the customer outright cascaded to every subscription they own,
     * so terminating one service wiped the customer's other sites too.
     */
    public function terminate(Service $service): array
    {
        $server = $this->getServer($service);
        $domainId = $this->subscriptionId($service);
        $clientId = $this->getClientId($service);

        if (! $server || (! $domainId && ! $clientId)) {
            return $this->buildResult(false, 'Missing server or Plesk subscription ID.');
        }

        // Removing a reseller removes its customers and their sites with it,
        // as the reseller's own service is what hosted them.
        if ($clientId && $this->isReseller($service)) {
            $id = (int) $clientId;
            $deleted = $this->xmlApi($server, "<reseller><del><filter><id>{$id}</id></filter></del></reseller>");
            $result = $this->buildResult($this->xmlOk($deleted), $this->xmlOk($deleted) ? 'Reseller removed.' : $this->xmlError($deleted));
            $this->logAction($service, 'terminate', $result);

            return $result;
        }

        if (! $domainId) {
            // A service recorded before the subscription id was kept.
            $resp = $this->http($server)->delete("{$this->baseUrl($server)}/clients/{$clientId}");
            $result = $this->buildResult($resp->successful(), $resp->successful() ? 'Account terminated.' : $this->errorMessage($resp));
            $this->logAction($service, 'terminate', $result);

            return $result;
        }

        $id = (int) $domainId;
        $deleted = $this->xmlApi($server, "<webspace><del><filter><id>{$id}</id></filter></del></webspace>");
        if (! $this->xmlOk($deleted)) {
            $result = $this->buildResult(false, $this->xmlError($deleted));
            $this->logAction($service, 'terminate', $result);

            return $result;
        }

        $message = 'Subscription removed.';
        if ($clientId) {
            $owner = (int) $clientId;
            $left = $this->xmlApi($server, "<webspace><get><filter><owner-id>{$owner}</owner-id></filter><dataset><gen_info/></dataset></get></webspace>");
            $remaining = $left ? count($left->xpath('//webspace/get/result[status="ok"]') ?: []) : null;

            if ($remaining === 0) {
                $resp = $this->http($server)->delete("{$this->baseUrl($server)}/clients/{$clientId}");
                $message = $resp->successful()
                    ? 'Subscription and Plesk customer removed.'
                    : 'Subscription removed; the Plesk customer was kept: '.$this->errorMessage($resp);
            } else {
                $message = 'Subscription removed; the Plesk customer still has other subscriptions and was kept.';
            }
        }

        $result = $this->buildResult(true, $message);
        $this->logAction($service, 'terminate', $result);

        return $result;
    }

    public function changePassword(Service $service, string $newPassword): array
    {
        $server = $this->getServer($service);
        $clientId = $this->getClientId($service);

        if (! $server || ! $clientId) {
            return $this->buildResult(false, 'Missing server or Plesk client ID.');
        }

        if ($this->isReseller($service)) {
            $id = (int) $clientId;
            $set = $this->xmlApi($server, "<reseller><set><filter><id>{$id}</id></filter><values><gen-info><passwd>".self::xml($newPassword).'</passwd></gen-info></values></set></reseller>');
            if (! $this->xmlOk($set)) {
                return $this->buildResult(false, $this->xmlError($set));
            }
            $service->update(['password' => $newPassword]);

            return $this->buildResult(true, 'Password changed.');
        }

        $resp = $this->http($server)->put("{$this->baseUrl($server)}/clients/{$clientId}", [
            'password' => $newPassword,
        ]);

        if (! $resp->successful()) {
            return $this->buildResult(false, $this->errorMessage($resp));
        }

        // The client password is the control panel login. The credential the
        // customer is shown - and uses to upload their site - is the
        // subscription's FTP login, which was left on the old password: the
        // panel then displayed a password that no longer worked for FTP.
        $domainId = $this->getDomainId($service);

        if ($domainId) {
            $ftp = $this->http($server)->put("{$this->baseUrl($server)}/domains/{$domainId}", [
                'hosting_settings' => ['ftp_password' => $newPassword],
            ]);

            if (! $ftp->successful()) {
                $service->update(['password' => $newPassword]);

                return $this->buildResult(false, 'Control panel password changed, but Plesk kept the old FTP password: '.$this->errorMessage($ftp));
            }
        }

        $service->update(['password' => $newPassword]);

        return $this->buildResult(true, 'Password changed.');
    }

    public function changePackage(Service $service, array $newPackage): array
    {
        $server = $this->getServer($service);
        $domainId = $this->getDomainId($service);

        $config = is_string($newPackage['config_options'] ?? null)
            ? json_decode($newPackage['config_options'], true)
            : ($newPackage['config_options'] ?? []);
        $planName = $config['plesk_plan'] ?? $config['package_name'] ?? $newPackage['package_name'] ?? $newPackage['name'] ?? null;
        $resellerPlan = self::resellerPlanOf($planName);

        if ($this->isReseller($service) || $resellerPlan !== null) {
            return $this->changeResellerPlan($service, $server, $resellerPlan);
        }

        if (! $server || ! $domainId || ! $planName) {
            return $this->buildResult(false, 'Missing server, domain ID, or plan name.');
        }

        $resp = $this->http($server)->put("{$this->baseUrl($server)}/domains/{$domainId}", [
            'plan' => ['name' => $planName],
        ]);

        return $this->buildResult($resp->successful(), $resp->successful() ? 'Package changed.' : $this->errorMessage($resp));
    }

    /**
     * Move a reseller to another reseller plan. A reseller cannot become a
     * subscription, nor a subscription a reseller: that is a new account.
     *
     * @return array{success: bool, message: string, data: array<string, mixed>}
     */
    private function changeResellerPlan(Service $service, ?Server $server, ?string $plan): array
    {
        $resellerId = $this->getClientId($service);
        if (! $server || ! $resellerId || ! $this->isReseller($service) || $plan === null) {
            return $this->buildResult(false, 'A reseller moves only to another reseller plan, and a subscription only to a service plan.');
        }

        $guid = array_search($plan, $this->resellerPlans($server), true);
        if ($guid === false) {
            return $this->buildResult(false, 'Plesk has no reseller plan named '.$plan.'.');
        }

        $id = (int) $resellerId;
        $xml = $this->xmlApi($server, "<reseller><switch-subscription><filter><id>{$id}</id></filter><plan-guid>".self::xml((string) $guid).'</plan-guid></switch-subscription></reseller>');

        return $this->buildResult($this->xmlOk($xml), $this->xmlOk($xml) ? 'Package changed.' : $this->xmlError($xml));
    }

    /**
     * Pull disk/traffic per client via GET /clients/{id}/statistics and update
     * the services directly (same contract as the cPanel module).
     */
    public function usageUpdate(Server $server): array
    {
        $updated = 0;
        $errors = 0;

        $services = Service::where('server_id', $server->id)
            ->where('status', 'active')
            ->get();

        foreach ($services as $service) {
            $clientId = $this->getClientId($service);
            if (! $clientId) {
                continue;
            }

            try {
                $resp = $this->http($server)->get("{$this->baseUrl($server)}/clients/{$clientId}/statistics");
                if (! $resp->successful()) {
                    $errors++;

                    continue;
                }

                $stats = $resp->json() ?? [];
                $updateData = [];
                if (isset($stats['disk_space'])) {
                    $updateData['disk_usage'] = (int) round(((int) $stats['disk_space']) / 1048576); // bytes → MB
                }
                if (isset($stats['traffic'])) {
                    $updateData['bw_usage'] = (int) round(((int) $stats['traffic']) / 1048576);
                }

                if ($updateData) {
                    $service->update($updateData);
                    $updated++;
                }
            } catch (\Throwable $e) {
                $errors++;
                Log::error('Plesk usageUpdate failed', ['service' => $service->id, 'error' => $e->getMessage()]);
            }
        }

        return ['updated' => $updated, 'errors' => $errors];
    }

    public function testConnection(Server $server): bool
    {
        try {
            $resp = $this->http($server)->get("{$this->baseUrl($server)}/server");

            return $resp->successful();
        } catch (\Throwable $e) {
            Log::warning('Plesk testConnection failed', ['server' => $server->id, 'error' => $e->getMessage()]);

            return false;
        }
    }
}
