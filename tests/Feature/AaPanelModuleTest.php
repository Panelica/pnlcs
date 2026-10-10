<?php

use App\Models\Client;
use App\Models\Product;
use App\Models\Server;
use App\Models\Service;
use App\Services\Module\ModuleRegistry;
use Illuminate\Cache\ArrayStore;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\ConnectionResolver;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Encryption\Encrypter;
use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Application;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Http;
use Modules\Servers\AaPanel\AaPanelClient;
use Modules\Servers\AaPanel\AaPanelException;
use Modules\Servers\AaPanel\AaPanelModule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Isolated contract tests: real Eloquent casts and relations, fake HTTP and
 * persistence (one usage test uses SQLite memory). Never touches an existing DB.
 * An explicit PHPUnit class avoids this repository's global Pest DB fixtures.
 */
final class AaPanelModuleTest extends TestCase
{
    private ?Container $previousContainer;

    private mixed $previousFacadeApplication;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousContainer = Container::getInstance();
        $this->previousFacadeApplication = Facade::getFacadeApplication();
        $app = new Application(dirname(__DIR__, 2));
        $app->instance('config', new Repository(['app' => ['key' => 'base64:'.base64_encode(str_repeat('k', 32)), 'cipher' => 'AES-256-CBC']]));
        $app->instance('encrypter', new Encrypter(str_repeat('k', 32), 'AES-256-CBC'));
        $app->instance('log', new class extends NullLogger
        {
            public function channel($name = null): self
            {
                return $this;
            }
        });
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($app);
        Cache::swap(new Illuminate\Cache\Repository(new ArrayStore));
        Http::swap(new Factory);
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($this->previousFacadeApplication);
        Container::setInstance($this->previousContainer);
        parent::tearDown();
    }

    private function server(array $attributes = []): Server
    {
        return (new Server)->forceFill(array_merge([
            'id' => 7, 'type' => 'aapanel', 'hostname' => 'panel.example.test',
            'ip_address' => '', 'port' => null, 'access_hash' => 'private-api-secret',
            'username' => 'admin', 'password' => 'private-admin-password',
        ], $attributes));
    }

    private function service(array $data = [], array $config = []): AaPanelMemoryService
    {
        $service = (new AaPanelMemoryService)->forceFill([
            'id' => 42, 'server_id' => 7, 'client_id' => 3, 'product_id' => 9,
            'domain' => 'shop.example.test', 'username' => 'shopuser',
            'password' => 'account-password-123', 'status' => 'active',
            'module_data' => $data,
        ]);
        $service->exists = true;
        $service->setRelation('server', $this->server());
        $service->setRelation('client', (new Client)->forceFill(['id' => 3, 'email' => 'customer@example.test']));
        $service->setRelation('product', (new Product)->forceFill([
            'id' => 9, 'server_type' => 'aapanel',
            'config_options' => array_merge(['package_name' => 'Basic'], $config),
        ]));

        return $service;
    }

    private function identity(): array
    {
        return ['aapanel_account_id' => 77, 'aapanel_username' => 'shopuser', 'aapanel_server_id' => 7, 'aapanel_owner' => 'pnlcs-test-owner'];
    }

    private function account(array $overrides = []): array
    {
        return array_merge([
            'account_id' => 77, 'username' => 'shopuser', 'remark' => 'pnlcs-test-owner',
            'status' => 1, 'package_id' => 10, 'disk_path' => '/www',
            'email' => 'customer@example.test', 'expire_date' => '0000-00-00',
            'disk_space_quota' => 1073741824, 'monthly_bandwidth_limit' => 10737418240,
            'max_site_limit' => 5, 'max_database' => 5,
            'php_start_children' => 2, 'php_max_children' => 5,
            'disk_space_used' => 5242880, 'monthly_bandwidth_used' => 7340032,
        ], $overrides);
    }

    private function listing(array $rows, ?int $count = null): array
    {
        return ['status' => 0, 'message' => ['list' => $rows, 'page' => ['count' => $count ?? count($rows)]]];
    }

    public function test_request_uses_signed_form_https_and_safe_transport(): void
    {
        Http::fake(function ($request, $options) {
            self::assertSame('POST', $request->method());
            self::assertSame('https://panel.example.test:8888/v2/virtual/get_service_info.json', $request->url());
            self::assertTrue($request->hasHeader('Content-Type', 'application/x-www-form-urlencoded'));
            self::assertMatchesRegularExpression('/^\d{13}$/', (string) $request['request_time']);
            self::assertSame(md5($request['request_time'].md5('private-api-secret')), $request['request_token']);
            self::assertTrue($options['verify']);
            self::assertFalse($options['allow_redirects']);
            self::assertArrayNotHasKey('access_hash', $request->data());
            self::assertArrayNotHasKey('password', $request->data());

            return Http::response(['status' => 0, 'message' => []]);
        });
        (new AaPanelClient($this->server()))->request('get_service_info');
        Http::assertSentCount(1);
    }

    public static function invalidResponses(): array
    {
        return [
            'html' => ['<html>private-api-secret</html>', 200],
            'missing status' => [['message' => 'private-api-secret'], 200],
            'boolean false' => [['status' => false], 200],
            'boolean true' => [['status' => true], 200],
            'error status' => [['status' => -1, 'message' => 'private-api-secret'], 200],
            'http failure' => [['status' => 0], 500],
            'redirect' => [['status' => 0], 302],
        ];
    }

    #[DataProvider('invalidResponses')]
    public function test_invalid_responses_are_failures_without_secret_echo(mixed $body, int $status): void
    {
        Http::fake(['*' => Http::response($body, $status)]);
        try {
            (new AaPanelClient($this->server()))->request('get_service_info');
            self::fail('Invalid aaPanel response was accepted.');
        } catch (AaPanelException $e) {
            self::assertStringNotContainsString('private-api-secret', $e->getMessage());
        }
    }

    public function test_transport_failure_does_not_echo_request_secrets(): void
    {
        Http::fake(fn () => throw new ConnectionException('private-api-secret account-password-123'));
        try {
            (new AaPanelClient($this->server()))->request('get_service_info');
            self::fail('A transport failure was accepted.');
        } catch (AaPanelException $e) {
            self::assertStringNotContainsString('private-api-secret', $e->getMessage());
            self::assertStringNotContainsString('account-password-123', $e->getMessage());
        }
    }

    public function test_api_key_is_required_without_password_fallback(): void
    {
        Http::fake();
        try {
            (new AaPanelClient($this->server(['access_hash' => ''])))->request('get_service_info');
            self::fail('An API key must be required.');
        } catch (AaPanelException $e) {
            self::assertStringContainsString('API', $e->getMessage());
        }
        Http::assertNothingSent();
    }

    public function test_listing_reads_all_pages(): void
    {
        Http::fake(fn ($r) => Http::response($this->listing([['account_id' => (int) $r['p'], 'username' => 'user'.$r['p']]], 2)));
        self::assertSame([['account_id' => 1, 'username' => 'user1'], ['account_id' => 2, 'username' => 'user2']], (new AaPanelClient($this->server()))->listing('get_account_list'));
        Http::assertSentCount(2);
    }

    public function test_repeated_page_is_rejected_instead_of_silently_truncated(): void
    {
        Http::fake(['*' => Http::response($this->listing([['account_id' => 1, 'username' => 'user1']], 3))]);
        $this->expectException(AaPanelException::class);
        (new AaPanelClient($this->server()))->listing('get_account_list');
    }

    public function test_manifest_discovers_module_and_requires_api_key(): void
    {
        $path = dirname(__DIR__, 2).'/modules/Servers/AaPanel/pnlcs.json';
        $manifest = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $registry = new ModuleRegistry;
        self::assertTrue($registry->registerDiscovered($manifest['type'], $manifest['name'], $manifest['class'], $path));
        self::assertInstanceOf(AaPanelModule::class, $registry->getServerModule('aapanel'));
        self::assertSame('token', $registry->serverCredentialRequirement('aapanel'));
    }

    public function test_package_picker_uses_portable_remote_package_names(): void
    {
        Http::fake([
            '*get_service_info.json' => Http::response($this->ready()),
            '*get_package_list.json' => Http::response($this->listing([
                ['package_id' => 10, 'package_name' => 'Basic'],
                ['package_id' => 20, 'package_name' => 'Business'],
            ])),
        ]);
        $packages = (new AaPanelModule)->listPackages($this->server());
        self::assertCount(2, $packages);
        self::assertSame(['Basic', 'Business'], array_column($packages, 'id'));
        self::assertSame(['Basic', 'Business'], array_column($packages, 'name'));
    }

    public static function identityMismatches(): array
    {
        return [
            'account id' => [['account_id' => 78]],
            'username' => [['username' => 'somebodyelse']],
            'ownership marker' => [['remark' => 'belongs-to-somebody-else']],
        ];
    }

    #[DataProvider('identityMismatches')]
    public function test_remote_identity_mismatch_never_mutates_an_account(array $changed): void
    {
        Http::fake([
            '*get_service_info.json' => Http::response($this->ready()),
            '*get_account_list.json' => Http::response($this->listing([$this->account($changed)])),
        ]);
        $result = (new AaPanelModule)->suspend($this->service($this->identity()));
        self::assertFalse($result['success']);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/remove_account.') || str_contains($r->url(), '/modify_account.'));
    }

    public function test_server_reassignment_never_mutates_the_old_identity(): void
    {
        Http::fake();
        $service = $this->service(array_merge($this->identity(), ['aapanel_server_id' => 8]));
        self::assertFalse((new AaPanelModule)->suspend($service, 'unpaid')['success']);
        Http::assertNothingSent();
    }

    private function ready(): array
    {
        return ['status' => 0, 'message' => ['install_status' => 2, 'run_status' => 1]];
    }

    private function package(array $overrides = []): array
    {
        return array_merge(array_intersect_key($this->account(), array_flip([
            'package_id', 'disk_space_quota', 'monthly_bandwidth_limit',
            'max_site_limit', 'max_database', 'php_start_children', 'php_max_children',
        ])), ['package_name' => 'Basic'], $overrides);
    }

    public function test_create_persists_ownership_before_request_and_reconciles_idempotently(): void
    {
        $service = $this->service();
        $created = false;
        $creates = 0;
        Http::fake(function ($r) use ($service, &$created, &$creates) {
            $action = basename(parse_url($r->url(), PHP_URL_PATH), '.json');
            switch ($action) {
                case 'get_service_info': return Http::response($this->ready());
                case 'get_package_list': return Http::response($this->listing([$this->package()]));
                case 'get_disk_list': return Http::response(['status' => 0, 'message' => [['mountpoint' => '/www', 'is_default' => 1]]]);
                case 'get_account_list': return Http::response($this->listing($created ? [$this->account(['remark' => $service->module_data['aapanel_owner']])] : []));
                case 'create_account':
                    self::assertNotEmpty($service->savedStates);
                    self::assertTrue($service->module_data['aapanel_create_pending']);
                    self::assertSame($service->module_data['aapanel_owner'], $r['remark']);
                    self::assertSame(0, $r['automatic_dns']);
                    self::assertSame(10, $r['package_id']);
                    self::assertSame(1073741824, $r['disk_space_quota']);
                    self::assertSame('/www', $r['mountpoint']);
                    $created = true;
                    $creates++;

                    return Http::response(['status' => 0]);
                default: throw new RuntimeException('Unexpected endpoint '.$action);
            }
        });
        $module = new AaPanelModule;
        $result = $module->create($service);
        self::assertTrue($result['success'], $result['message']);
        self::assertSame(77, $service->module_data['aapanel_account_id']);
        self::assertSame('shopuser', $service->module_data['aapanel_username']);
        self::assertSame(7, $service->module_data['aapanel_server_id']);
        self::assertFalse($service->module_data['aapanel_create_pending']);
        self::assertTrue($module->create($service)['success']);
        self::assertSame(1, $creates);
    }

    public function test_create_refuses_unowned_username_collision(): void
    {
        Http::fake([
            '*get_service_info.json' => Http::response($this->ready()),
            '*get_account_list.json' => Http::response($this->listing([$this->account(['remark' => 'another-owner'])])),
        ]);
        self::assertFalse((new AaPanelModule)->create($this->service())['success']);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/create_account.'));
    }

    public function test_unknown_create_outcome_does_not_send_another_create(): void
    {
        $service = $this->service();
        $creates = 0;
        Http::fake(function ($r) use (&$creates) {
            return match (basename(parse_url($r->url(), PHP_URL_PATH), '.json')) {
                'get_service_info' => Http::response($this->ready()),
                'get_package_list' => Http::response($this->listing([$this->package()])),
                'get_disk_list' => Http::response(['status' => 0, 'message' => ['list' => [['mountpoint' => '/www']]]]),
                'get_account_list' => Http::response($this->listing([])),
                'create_account' => (function () use (&$creates) {
                    $creates++;
                    throw new ConnectionException('timeout');
                })(),
                default => throw new RuntimeException('Unexpected endpoint'),
            };
        });
        $module = new AaPanelModule;
        self::assertFalse($module->create($service)['success']);
        self::assertTrue($service->module_data['aapanel_create_pending']);
        self::assertFalse($module->create($service)['success']);
        self::assertSame(1, $creates);
    }

    public function test_suspend_unsuspend_and_password_preserve_existing_limits(): void
    {
        $remote = $this->account(['init_password' => 'do-not-forward', 'login_url' => 'https://secret.test']);
        $modifications = [];
        Http::fake(function ($r) use (&$remote, &$modifications) {
            return match (basename(parse_url($r->url(), PHP_URL_PATH), '.json')) {
                'get_service_info' => Http::response($this->ready()),
                'get_account_list' => Http::response($this->listing([$remote])),
                'modify_account' => (function () use ($r, &$remote, &$modifications) {
                    $modifications[] = $r->data();
                    $remote = array_merge($remote, $r->data());

                    return Http::response(['status' => 0]);
                })(),
                default => throw new RuntimeException('Unexpected endpoint'),
            };
        });
        $module = new AaPanelModule;
        $service = $this->service($this->identity());
        self::assertTrue($module->suspend($service, 'unpaid')['success']);
        self::assertSame(0, $modifications[0]['status']);
        self::assertTrue($module->unsuspend($service)['success']);
        self::assertSame(1, $modifications[1]['status']);
        self::assertTrue($module->changePassword($service, 'new-password-123')['success']);
        self::assertSame('new-password-123', $service->password);
        foreach ($modifications as $data) {
            self::assertSame(77, $data['account_id']);
            self::assertSame(1073741824, $data['disk_space_quota']);
            self::assertSame(10737418240, $data['monthly_bandwidth_limit']);
            self::assertArrayNotHasKey('init_password', $data);
            self::assertArrayNotHasKey('login_url', $data);
        }
    }

    public function test_package_change_uses_remote_package_quotas_and_preserves_identity(): void
    {
        Http::fake([
            '*get_service_info.json' => Http::response($this->ready()),
            '*get_account_list.json' => Http::response($this->listing([$this->account()])),
            '*get_package_list.json' => Http::response($this->listing([$this->package(['package_id' => 20, 'package_name' => 'Business', 'disk_space_quota' => 2147483648])])),
            '*modify_account.json' => Http::response(['status' => 0]),
        ]);
        $result = (new AaPanelModule)->changePackage($this->service($this->identity()), ['config_options' => ['package_name' => 'Business']]);
        self::assertTrue($result['success'], $result['message']);
        Http::assertSent(fn ($r) => str_contains($r->url(), '/modify_account.') && $r['package_id'] === 20 && $r['disk_space_quota'] === 2147483648 && $r['account_id'] === 77 && $r['remark'] === 'pnlcs-test-owner');
    }

    public function test_termination_deletes_only_bound_account_and_keeps_tombstone(): void
    {
        $removed = false;
        $deletes = 0;
        Http::fake(function ($r) use (&$removed, &$deletes) {
            return match (basename(parse_url($r->url(), PHP_URL_PATH), '.json')) {
                'get_service_info' => Http::response($this->ready()),
                'get_account_list' => Http::response($this->listing($removed ? [] : [$this->account()])),
                'remove_account' => (function () use ($r, &$removed, &$deletes) {
                    self::assertSame(77, $r['account_id']);
                    self::assertSame(1, $r['is_del_resources']);
                    $removed = true;
                    $deletes++;

                    return Http::response(['status' => 0]);
                })(),
                default => throw new RuntimeException('Unexpected endpoint'),
            };
        });
        $service = $this->service($this->identity());
        $module = new AaPanelModule;
        self::assertTrue($module->terminate($service)['success']);
        self::assertTrue($service->module_data['aapanel_terminated']);
        self::assertSame(77, $service->module_data['aapanel_account_id']);
        self::assertTrue($module->terminate($service)['success']);
        self::assertSame(1, $deletes);
    }

    public function test_live_usage_converts_bytes_to_megabytes(): void
    {
        Http::fake([
            '*get_service_info.json' => Http::response($this->ready()),
            '*get_account_list.json' => Http::response($this->listing([$this->account()])),
        ]);
        $usage = (new AaPanelModule)->liveUsage($this->service($this->identity()));
        self::assertTrue($usage['available']);
        self::assertSame(['used_mb' => 5, 'quota_mb' => 1024], $usage['disk']);
        self::assertSame(['used_mb' => 7, 'quota_mb' => 10240], $usage['bandwidth']);
    }

    public function test_failed_password_update_keeps_local_password_and_redacts_error(): void
    {
        Http::fake([
            '*get_service_info.json' => Http::response($this->ready()),
            '*get_account_list.json' => Http::response($this->listing([$this->account()])),
            '*modify_account.json' => Http::response(['status' => false, 'message' => 'new-password-123 private-api-secret']),
        ]);
        $service = $this->service($this->identity());
        $result = (new AaPanelModule)->changePassword($service, 'new-password-123');
        self::assertFalse($result['success']);
        self::assertSame('account-password-123', $service->password);
        self::assertStringNotContainsString('new-password-123', $result['message']);
        self::assertStringNotContainsString('private-api-secret', $result['message']);
    }

    public function test_unknown_termination_outcome_is_not_repeated(): void
    {
        $deletes = 0;
        Http::fake(function ($r) use (&$deletes) {
            return match (basename(parse_url($r->url(), PHP_URL_PATH), '.json')) {
                'get_service_info' => Http::response($this->ready()),
                'get_account_list' => Http::response($this->listing([$this->account()])),
                'remove_account' => (function () use (&$deletes) {
                    $deletes++;
                    throw new ConnectionException('timeout');
                })(),
                default => throw new RuntimeException('Unexpected endpoint'),
            };
        });
        $service = $this->service($this->identity());
        $module = new AaPanelModule;
        self::assertFalse($module->terminate($service)['success']);
        self::assertTrue($service->module_data['aapanel_terminate_pending']);
        self::assertFalse($module->terminate($service)['success']);
        self::assertSame(1, $deletes);
    }

    public function test_connection_checks_both_sub_service_and_package_api(): void
    {
        Http::fake([
            '*get_service_info.json' => Http::response($this->ready()),
            '*get_package_list.json' => Http::response($this->listing([])),
        ]);
        self::assertTrue((new AaPanelModule)->testConnection($this->server()));
        Http::assertSentCount(2);
    }

    public function test_connection_rejects_main_panel_without_running_sub_service(): void
    {
        Http::fake(['*get_service_info.json' => Http::response(['status' => 0, 'message' => ['install_status' => 0, 'run_status' => 0]])]);
        self::assertFalse((new AaPanelModule)->testConnection($this->server()));
        Http::assertSentCount(1);
    }

    public static function usagePersistenceCases(): array
    {
        return [
            'saved' => [false, ['updated' => 1, 'errors' => 1], [5, 1024, 7, 10240]],
            'vetoed' => [true, ['updated' => 0, 'errors' => 2], [99, 99, 99, 99]],
        ];
    }

    #[DataProvider('usagePersistenceCases')]
    public function test_usage_poll_updates_only_owned_accounts_in_isolated_memory_database(bool $veto, array $expectedResult, array $expectedUsage): void
    {
        // The repository's normal test bootstrap targets MySQL. This single
        // connection is created explicitly in memory and never runs migrations.
        $previous = Service::getConnectionResolver();
        $previousEvents = Service::getEventDispatcher();
        $connection = new SQLiteConnection(new PDO('sqlite::memory:'));
        $resolver = new ConnectionResolver(['aapanel-test' => $connection]);
        $resolver->setDefaultConnection('aapanel-test');
        Service::setConnectionResolver($resolver);
        try {
            $events = new Dispatcher(Container::getInstance());
            $events->listen('eloquent.saving: '.Service::class, fn () => ! $veto);
            Service::setEventDispatcher($events);
            $connection->statement('CREATE TABLE services (id INTEGER PRIMARY KEY, server_id INTEGER, status TEXT, module_data TEXT, disk_usage INTEGER, disk_limit INTEGER, bw_usage INTEGER, bw_limit INTEGER, updated_at TEXT, created_at TEXT)');
            foreach ([42 => $this->identity(), 43 => array_merge($this->identity(), ['aapanel_owner' => 'wrong-owner'])] as $id => $identity) {
                $connection->table('services')->insert([
                    'id' => $id, 'server_id' => 7, 'status' => 'active',
                    'module_data' => json_encode($identity), 'disk_usage' => 99,
                    'disk_limit' => 99, 'bw_usage' => 99, 'bw_limit' => 99,
                ]);
            }
            Http::fake([
                '*get_service_info.json' => Http::response($this->ready()),
                '*get_account_list.json' => Http::response($this->listing([$this->account()])),
            ]);
            self::assertSame($expectedResult, (new AaPanelModule)->usageUpdate($this->server()));
            Http::assertSentCount(2);
            $owned = $connection->table('services')->find(42);
            self::assertSame($expectedUsage[0], $owned->disk_usage);
            self::assertSame($expectedUsage[1], $owned->disk_limit);
            self::assertSame($expectedUsage[2], $owned->bw_usage);
            self::assertSame($expectedUsage[3], $owned->bw_limit);
            self::assertSame(99, $connection->table('services')->find(43)->disk_usage);
        } finally {
            if ($previousEvents) {
                Service::setEventDispatcher($previousEvents);
            } else {
                Service::unsetEventDispatcher();
            }
            if ($previous) {
                Service::setConnectionResolver($previous);
            } else {
                Service::unsetConnectionResolver();
            }
        }
    }

    public function test_termination_refuses_renamed_account_instead_of_reporting_it_deleted(): void
    {
        Http::fake(function ($r) {
            return match (basename(parse_url($r->url(), PHP_URL_PATH), '.json')) {
                'get_service_info' => Http::response($this->ready()),
                'get_account_list' => Http::response($this->listing($r['search_value'] === '' ? [$this->account(['username' => 'renameduser'])] : [])),
                default => throw new RuntimeException('Unexpected endpoint'),
            };
        });
        $service = $this->service($this->identity());
        self::assertFalse((new AaPanelModule)->terminate($service)['success']);
        self::assertEmpty($service->module_data['aapanel_terminated'] ?? false);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/remove_account.'));
        Http::assertSent(fn ($r) => str_contains($r->url(), '/get_account_list.') && $r['search_value'] === '');
    }

    public static function missingAccountFields(): array
    {
        return [['status'], ['email'], ['expire_date'], ['disk_space_quota'], ['monthly_bandwidth_limit'], ['max_site_limit'], ['max_database'], ['php_start_children'], ['php_max_children']];
    }

    #[DataProvider('missingAccountFields')]
    public function test_incomplete_account_is_not_overwritten(string $field): void
    {
        $account = $this->account();
        unset($account[$field]);
        Http::fake([
            '*get_service_info.json' => Http::response($this->ready()),
            '*get_account_list.json' => Http::response($this->listing([$account])),
        ]);
        self::assertFalse((new AaPanelModule)->changePassword($this->service($this->identity()), 'new-password-123')['success']);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/modify_account.'));
    }

    public function test_missing_usage_is_unavailable_instead_of_reported_as_zero(): void
    {
        $account = $this->account();
        unset($account['monthly_bandwidth_used']);
        Http::fake([
            '*get_service_info.json' => Http::response($this->ready()),
            '*get_account_list.json' => Http::response($this->listing([$account])),
        ]);
        self::assertFalse((new AaPanelModule)->liveUsage($this->service($this->identity()))['available']);
    }

    public function test_unknown_password_change_is_fenced_without_persisting_secret_in_module_data(): void
    {
        $modifications = 0;
        Http::fake(function ($r) use (&$modifications) {
            return match (basename(parse_url($r->url(), PHP_URL_PATH), '.json')) {
                'get_service_info' => Http::response($this->ready()),
                'get_account_list' => Http::response($this->listing([$this->account()])),
                'modify_account' => (function () use (&$modifications) {
                    $modifications++;
                    throw new ConnectionException('timeout');
                })(),
                default => throw new RuntimeException('Unexpected endpoint'),
            };
        });
        $service = $this->service($this->identity());
        $module = new AaPanelModule;
        self::assertFalse($module->changePassword($service, 'new-password-123')['success']);
        self::assertSame(['action' => 'password', 'target' => []], $service->module_data['aapanel_modify_pending']);
        self::assertStringNotContainsString('new-password-123', json_encode($service->module_data));
        self::assertSame('account-password-123', $service->password);
        self::assertFalse($module->changePassword($service, 'second-password-456')['success']);
        self::assertFalse($module->suspend($service)['success']);
        self::assertSame(1, $modifications);
    }

    public function test_matching_package_is_a_no_op(): void
    {
        Http::fake([
            '*get_service_info.json' => Http::response($this->ready()),
            '*get_account_list.json' => Http::response($this->listing([$this->account()])),
            '*get_package_list.json' => Http::response($this->listing([$this->package()])),
        ]);
        self::assertTrue((new AaPanelModule)->changePackage($this->service($this->identity()), ['config_options' => ['package_name' => 'Basic']])['success']);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/modify_account.'));
    }

    public static function unknownStatusOutcomes(): array
    {
        return ['applied remotely' => [true], 'not observed remotely' => [false]];
    }

    #[DataProvider('unknownStatusOutcomes')]
    public function test_status_timeout_reconciles_observed_change_and_otherwise_fences(bool $applied): void
    {
        $remote = $this->account();
        $modifications = 0;
        Http::fake(function ($r) use (&$remote, &$modifications, $applied) {
            return match (basename(parse_url($r->url(), PHP_URL_PATH), '.json')) {
                'get_service_info' => Http::response($this->ready()),
                'get_account_list' => Http::response($this->listing([$remote])),
                'modify_account' => (function () use ($r, &$remote, &$modifications, $applied) {
                    $modifications++;
                    if ($applied) {
                        $remote['status'] = $r['status'];
                    }
                    throw new ConnectionException('timeout');
                })(),
                default => throw new RuntimeException('Unexpected endpoint'),
            };
        });
        $service = $this->service($this->identity());
        $module = new AaPanelModule;
        self::assertFalse($module->suspend($service)['success']);
        self::assertSame(['action' => 'status', 'target' => ['status' => 0]], $service->module_data['aapanel_modify_pending']);
        self::assertSame($applied, $module->suspend($service)['success']);
        self::assertSame(1, $modifications);
        if ($applied) {
            self::assertNull($service->module_data['aapanel_modify_pending']);
        } else {
            self::assertNotEmpty($service->module_data['aapanel_modify_pending']);
        }
    }

    public function test_customer_json_notes_are_not_trusted_or_erased(): void
    {
        $service = $this->service();
        $notes = json_encode($this->identity(), JSON_THROW_ON_ERROR);
        $service->forceFill(['notes' => $notes])->syncOriginal();
        Http::fake([
            '*get_service_info.json' => Http::response($this->ready()),
            '*get_account_list.json' => Http::response($this->listing([$this->account()])),
        ]);
        self::assertFalse((new AaPanelModule)->create($service)['success']);
        self::assertSame($notes, $service->notes);
        self::assertArrayNotHasKey('aapanel_account_id', $service->module_data);
        self::assertNotSame('pnlcs-test-owner', $service->module_data['aapanel_owner']);
        Http::assertSentCount(2);
    }

    public static function terminationFences(): array
    {
        $cases = [];
        foreach (['aapanel_terminate_pending', 'aapanel_terminated'] as $flag) {
            foreach (['create', 'suspend', 'unsuspend', 'changePassword', 'changePackage'] as $action) {
                $cases[$flag.' '.$action] = [$flag, $action];
            }
        }

        return $cases;
    }

    #[DataProvider('terminationFences')]
    public function test_termination_fences_all_other_mutations(string $flag, string $action): void
    {
        Http::fake();
        $service = $this->service(array_merge($this->identity(), [$flag => true]));
        $result = $this->invokeAction(new AaPanelModule, $service, $action);
        self::assertFalse($result['success']);
        Http::assertNothingSent();
    }

    private function invokeAction(AaPanelModule $module, Service $service, string $action): array
    {
        return match ($action) {
            'changePassword' => $module->changePassword($service, 'new-password-123'),
            'changePackage' => $module->changePackage($service, ['config_options' => ['package_name' => 'Basic']]),
            default => $module->$action($service),
        };
    }

    public static function malformedAccountRows(): array
    {
        return [
            'empty' => [[]], 'no ID' => [['username' => 'shopuser']],
            'no username' => [['account_id' => 77]],
            'array ID' => [['account_id' => [77], 'username' => 'shopuser']],
            'empty username' => [['account_id' => 77, 'username' => '']],
            'boolean ID' => [['account_id' => true, 'username' => 'shopuser']],
        ];
    }

    #[DataProvider('malformedAccountRows')]
    public function test_malformed_row_cannot_prove_an_account_was_deleted(array $row): void
    {
        Http::fake([
            '*get_service_info.json' => Http::response($this->ready()),
            '*get_account_list.json' => Http::response($this->listing([$row])),
        ]);
        $service = $this->service($this->identity());
        self::assertFalse((new AaPanelModule)->terminate($service)['success']);
        self::assertEmpty($service->module_data['aapanel_terminated'] ?? false);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/remove_account.'));
    }

    public static function invalidTotals(): array
    {
        return [['-1'], [1.5], ['1.5'], [true], [null], [[]], ['1e9'], ['999999999999999999999999999']];
    }

    #[DataProvider('invalidTotals')]
    public function test_invalid_pagination_totals_are_rejected(mixed $total): void
    {
        Http::fake(['*' => Http::response(['status' => 0, 'message' => ['list' => [], 'page' => ['count' => $total]]])]);
        $this->expectException(AaPanelException::class);
        (new AaPanelClient($this->server()))->listing('get_account_list');
    }

    public function test_overlapping_pages_do_not_masquerade_as_complete(): void
    {
        Http::fakeSequence()
            ->push($this->listing([$this->account(['account_id' => 1]), $this->account(['account_id' => 2])], 4))
            ->push($this->listing([$this->account(['account_id' => 2]), $this->account(['account_id' => 3])], 4));
        $this->expectException(AaPanelException::class);
        (new AaPanelClient($this->server()))->listing('get_account_list');
    }

    public static function inconsistentPages(): array
    {
        return ['changing total' => [3, 2], 'empty incomplete page' => [2, 2], 'too many rows' => [0, 0]];
    }

    #[DataProvider('inconsistentPages')]
    public function test_inconsistent_pages_fail_closed(int $firstTotal, int $secondTotal): void
    {
        $secondRows = $firstTotal === 3 ? [$this->account(['account_id' => 78])] : [];
        Http::fakeSequence()->push($this->listing([$this->account()], $firstTotal))->push($this->listing($secondRows, $secondTotal));
        $this->expectException(AaPanelException::class);
        (new AaPanelClient($this->server()))->listing('get_account_list');
    }

    public static function malformedPaginationObjects(): array
    {
        return [[null], [false], ['invalid']];
    }

    #[DataProvider('malformedPaginationObjects')]
    public function test_present_but_malformed_pagination_is_not_missing_metadata(mixed $page): void
    {
        Http::fake(['*' => Http::response(['status' => 0, 'message' => ['list' => [], 'page' => $page]])]);
        $this->expectException(AaPanelException::class);
        (new AaPanelClient($this->server()))->listing('get_account_list');
    }

    public function test_short_page_without_count_remains_compatible(): void
    {
        Http::fake(['*' => Http::response(['status' => 0, 'message' => ['list' => [$this->account()]]])]);
        self::assertSame([$this->account()], (new AaPanelClient($this->server()))->listing('get_account_list'));
        Http::assertSentCount(1);
    }

    public function test_pagination_safety_bound_does_not_return_a_partial_list(): void
    {
        Http::fake(fn ($r) => Http::response($this->listing([$this->account(['account_id' => $r['p']])], 26)));
        try {
            (new AaPanelClient($this->server()))->listing('get_account_list');
            self::fail('Incomplete list accepted.');
        } catch (AaPanelException $e) {
            self::assertStringContainsString('limit', $e->getMessage());
        }
        Http::assertSentCount(25);
    }

    public static function invalidUsageNumbers(): array
    {
        return [[-1], [true], ['1e309'], ['999999999999999999999999999'], [0.5], [[]], [null]];
    }

    #[DataProvider('invalidUsageNumbers')]
    public function test_invalid_usage_never_becomes_zero_or_overflows(mixed $value): void
    {
        Http::fake([
            '*get_service_info.json' => Http::response($this->ready()),
            '*get_account_list.json' => Http::response($this->listing([$this->account(['disk_space_used' => $value])])),
        ]);
        self::assertFalse((new AaPanelModule)->liveUsage($this->service($this->identity()))['available']);
    }

    public function test_usage_rounds_up_bytes_and_preserves_zero_unlimited_limits(): void
    {
        Http::fake([
            '*get_service_info.json' => Http::response($this->ready()),
            '*get_account_list.json' => Http::response($this->listing([$this->account([
                'disk_space_used' => '1048577', 'disk_space_quota' => 0,
                'monthly_bandwidth_used' => 1, 'monthly_bandwidth_limit' => 0,
            ])])),
        ]);
        $usage = (new AaPanelModule)->liveUsage($this->service($this->identity()));
        self::assertTrue($usage['available']);
        self::assertSame(['used_mb' => 2, 'quota_mb' => 0], $usage['disk']);
        self::assertSame(['used_mb' => 1, 'quota_mb' => 0], $usage['bandwidth']);
    }

    public function test_existing_action_lock_prevents_api_calls(): void
    {
        $lock = Cache::lock('aapanel:service:42', 3600);
        self::assertTrue($lock->get());
        try {
            Http::fake();
            self::assertFalse((new AaPanelModule)->suspend($this->service($this->identity()))['success']);
            Http::assertNothingSent();
        } finally {
            $lock->release();
        }
    }

    public function test_failed_intent_save_prevents_remote_creation(): void
    {
        $service = $this->service();
        $service->rejectSaves = true;
        Http::fake(['*get_service_info.json' => Http::response($this->ready())]);
        self::assertFalse((new AaPanelModule)->create($service)['success']);
        Http::assertSentCount(1);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/create_account.'));
    }

    public static function invalidConnections(): array
    {
        return [
            [['hostname' => 'https://panel.example.test/path']],
            [['hostname' => 'panel.example.test:8888']],
            [['hostname' => 'panel.example.test/user@evil.test']],
            [['port' => 0]], [['port' => -1]], [['port' => 65536]],
        ];
    }

    #[DataProvider('invalidConnections')]
    public function test_invalid_endpoint_never_sends_secrets(array $attributes): void
    {
        Http::fake();
        try {
            (new AaPanelClient($this->server($attributes)))->request('get_service_info');
            self::fail('Invalid endpoint was accepted.');
        } catch (AaPanelException) {
            Http::assertNothingSent();
        }
    }

    public function test_ipv6_and_signatures_cannot_be_overridden_by_parameters(): void
    {
        Http::fake(['*' => Http::response(['status' => 0])]);
        (new AaPanelClient($this->server(['hostname' => '2001:db8::1', 'port' => 9443])))
            ->request('get_service_info', ['request_time' => 'forged', 'request_token' => 'forged']);
        Http::assertSent(fn ($r) => $r->url() === 'https://[2001:db8::1]:9443/v2/virtual/get_service_info.json'
            && $r['request_time'] !== 'forged' && $r['request_token'] === md5($r['request_time'].md5('private-api-secret')));
    }

    public function test_listing_cannot_invoke_a_mutation_action(): void
    {
        Http::fake();
        try {
            (new AaPanelClient($this->server()))->listing('remove_account');
            self::fail('Mutation used as listing.');
        } catch (AaPanelException) {
            Http::assertNothingSent();
        }
    }

    public static function mutationResponses(): array
    {
        return [
            'unauthorized' => [401, ['status' => 0], false],
            'forbidden' => [403, ['status' => 0], false],
            'bad gateway' => [502, ['status' => -1], true],
            'redirect' => [302, ['status' => 0], true],
            'invalid body' => [200, '<html>private-api-secret</html>', true],
            'explicit rejection' => [200, ['status' => -1], false],
            'missing status' => [200, ['message' => 'private-api-secret'], true],
        ];
    }

    #[DataProvider('mutationResponses')]
    public function test_known_rejections_clear_intent_but_uncertain_writes_stay_fenced(int $status, mixed $body, bool $unknown): void
    {
        Http::fake([
            '*get_service_info.json' => Http::response($this->ready()),
            '*get_account_list.json' => Http::response($this->listing([$this->account()])),
            '*modify_account.json' => Http::response($body, $status),
        ]);
        $service = $this->service($this->identity());
        $result = (new AaPanelModule)->suspend($service);
        self::assertFalse($result['success']);
        self::assertSame($unknown, $service->module_data['aapanel_modify_pending'] !== null);
        self::assertStringNotContainsString('private-api-secret', $result['message']);
        Http::assertSentCount(3);
    }

    public function test_reappearing_terminated_identity_is_not_deleted_twice(): void
    {
        Http::fake([
            '*get_service_info.json' => Http::response($this->ready()),
            '*get_account_list.json' => Http::response($this->listing([$this->account()])),
        ]);
        $service = $this->service(array_merge($this->identity(), ['aapanel_terminated' => true]));
        self::assertFalse((new AaPanelModule)->terminate($service)['success']);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/remove_account.'));
    }

    public function test_unknown_password_change_also_blocks_deletion(): void
    {
        Http::fake([
            '*get_service_info.json' => Http::response($this->ready()),
            '*get_account_list.json' => Http::response($this->listing([$this->account()])),
        ]);
        $service = $this->service(array_merge($this->identity(), ['aapanel_modify_pending' => ['action' => 'password', 'target' => []]]));
        self::assertFalse((new AaPanelModule)->terminate($service)['success']);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/remove_account.'));
    }

    public static function malformedPendingChanges(): array
    {
        return [[true], ['pending'], [['action' => 'unknown', 'target' => ['status' => 1]]], [['action' => 'status', 'target' => 'invalid']]];
    }

    #[DataProvider('malformedPendingChanges')]
    public function test_malformed_pending_changes_are_not_silently_discarded(mixed $pending): void
    {
        Http::fake([
            '*get_service_info.json' => Http::response($this->ready()),
            '*get_account_list.json' => Http::response($this->listing([$this->account()])),
        ]);
        $service = $this->service(array_merge($this->identity(), ['aapanel_modify_pending' => $pending]));
        self::assertFalse((new AaPanelModule)->suspend($service)['success']);
        self::assertSame($pending, $service->module_data['aapanel_modify_pending']);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/modify_account.'));
    }

    public function test_confirmed_package_timeout_is_reconciled_without_another_write(): void
    {
        $package = $this->package(['package_id' => 20, 'package_name' => 'Business', 'disk_space_quota' => 2147483648]);
        $target = array_diff_key($package, ['package_name' => true]);
        $service = $this->service(array_merge($this->identity(), ['aapanel_modify_pending' => ['action' => 'package', 'target' => $target]]));
        Http::fake([
            '*get_service_info.json' => Http::response($this->ready()),
            '*get_account_list.json' => Http::response($this->listing([$this->account($target)])),
            '*get_package_list.json' => Http::response($this->listing([$package])),
        ]);
        self::assertTrue((new AaPanelModule)->changePackage($service, ['config_options' => ['package_name' => 'Business']])['success']);
        self::assertNull($service->module_data['aapanel_modify_pending']);
        Http::assertSentCount(3);
    }

    public static function malformedDisks(): array
    {
        return [[[]], [[['mountpoint' => '']]], [[['mountpoint' => ['bad']]]], [[['mountpoint' => 'relative/path']]], [[['mountpoint' => "/www\0bad"]]]];
    }

    #[DataProvider('malformedDisks')]
    public function test_invalid_storage_never_provisions_an_account(array $disks): void
    {
        Http::fake([
            '*get_service_info.json' => Http::response($this->ready()),
            '*get_account_list.json' => Http::response($this->listing([])),
            '*get_package_list.json' => Http::response($this->listing([$this->package()])),
            '*get_disk_list.json' => Http::response(['status' => 0, 'message' => $disks]),
        ]);
        self::assertFalse((new AaPanelModule)->create($this->service())['success']);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/create_account.'));
    }

    public function test_create_chooses_default_disk_and_generates_persisted_credentials(): void
    {
        $service = $this->service();
        $service->forceFill(['username' => null, 'password' => null]);
        $created = false;
        Http::fake(function ($r) use ($service, &$created) {
            $responses = [
                'get_service_info' => $this->ready(),
                'get_package_list' => $this->listing([$this->package()]),
                'get_disk_list' => ['status' => 0, 'message' => [['mountpoint' => '/other'], ['mountpoint' => '/default', 'is_default' => 1]]],
                'get_account_list' => $this->listing($created ? [$this->account(['username' => 'pnlcs42', 'remark' => $service->module_data['aapanel_owner']])] : []),
            ];
            $action = basename(parse_url($r->url(), PHP_URL_PATH), '.json');
            if ($action === 'create_account') {
                self::assertSame('pnlcs42', $r['username']);
                self::assertSame('/default', $r['mountpoint']);
                self::assertSame($service->password, $r['password']);
                self::assertGreaterThanOrEqual(32, strlen($r['password']));
                self::assertNotSame($service->password, $service->getRawOriginal('password'));
                $created = true;

                return Http::response(['status' => 0]);
            }

            return Http::response($responses[$action]);
        });
        self::assertTrue((new AaPanelModule)->create($service)['success']);
        self::assertSame('pnlcs42', $service->username);
        Http::assertSentCount(6);
    }

    public static function invalidPackageNumbers(): array
    {
        return [[-1], ['1e309'], ['99999999999999999999999999'], [1.5], [true]];
    }

    #[DataProvider('invalidPackageNumbers')]
    public function test_invalid_package_limits_do_not_reach_mutation(mixed $limit): void
    {
        Http::fake([
            '*get_service_info.json' => Http::response($this->ready()),
            '*get_account_list.json' => Http::response($this->listing([$this->account()])),
            '*get_package_list.json' => Http::response($this->listing([$this->package(['disk_space_quota' => $limit])])),
        ]);
        self::assertFalse((new AaPanelModule)->changePackage($this->service($this->identity()), ['config_options' => ['package_name' => 'Basic']])['success']);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/modify_account.'));
    }

    public function test_service_must_be_installed_and_running(): void
    {
        Http::fake(['*' => Http::response(['status' => 0, 'message' => ['install_status' => 1, 'run_status' => 0]])]);
        $this->expectException(AaPanelException::class);
        (new AaPanelClient($this->server()))->assertReady();
    }
}

final class AaPanelMemoryService extends Service
{
    public array $savedStates = [];

    public bool $rejectSaves = false;

    public function refresh()
    {
        return $this;
    }

    public function save(array $options = [])
    {
        if ($this->rejectSaves) {
            return false;
        }
        $this->savedStates[] = $this->getAttributes();
        $this->syncOriginal();

        return true;
    }
}
