<?php

use App\Contracts\ServerModuleInterface;
use App\Events\ServiceSuspended;
use App\Models\ModuleQueue;
use App\Models\Product;
use App\Models\Server;
use App\Models\Service;
use App\Services\HookManager;
use App\Services\Module\ModuleRegistry;
use App\Services\ProvisioningService;
use Illuminate\Cache\ArrayStore;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\ConnectionResolver;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Encryption\Encrypter;
use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Application;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Http;
use Modules\Servers\AaPanel\AaPanelModule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/** Real persisted models, isolated SQLite memory and fake HTTP only. */
final class AaPanelProvisioningLockTest extends TestCase
{
    private ?Container $oldContainer;

    private mixed $oldFacade;

    private mixed $oldResolver;

    private mixed $oldDispatcher;

    private Dispatcher $events;

    private ProvisioningService $provisioning;

    private array $remote;

    private int $modifications = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->oldContainer = Container::getInstance();
        $this->oldFacade = Facade::getFacadeApplication();
        $this->oldResolver = Service::getConnectionResolver();
        $this->oldDispatcher = Service::getEventDispatcher();
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
        $this->events = new Dispatcher($app);
        $app->instance('events', $this->events);
        $app->instance(HookManager::class, new HookManager);
        Service::setEventDispatcher($this->events);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($app);
        Cache::swap(new Illuminate\Cache\Repository(new ArrayStore));
        Http::swap(new Factory);
        Http::preventStrayRequests();
        $this->seedMemoryDatabase();
        $registry = new ModuleRegistry;
        $registry->registerServer('aapanel', AaPanelModule::class);
        $this->provisioning = new ProvisioningService($registry);
        $this->remote = [
            'account_id' => 77, 'username' => 'shopuser', 'remark' => 'pnlcs-test-owner',
            'status' => 1, 'package_id' => 10, 'email' => 'customer@example.test',
            'expire_date' => '0000-00-00', 'disk_space_quota' => 1073741824,
            'monthly_bandwidth_limit' => 10737418240, 'max_site_limit' => 5,
            'max_database' => 5, 'php_start_children' => 2, 'php_max_children' => 5,
        ];
        Http::fake(fn ($request) => $this->remoteResponse($request));
    }

    private function seedMemoryDatabase(): void
    {
        $db = new SQLiteConnection(new PDO('sqlite::memory:'));
        $resolver = new ConnectionResolver(['aapanel-lock-test' => $db]);
        $resolver->setDefaultConnection('aapanel-lock-test');
        Service::setConnectionResolver($resolver);
        foreach ([
            'CREATE TABLE products (id INTEGER PRIMARY KEY, server_type TEXT, config_options TEXT, deleted_at TEXT, created_at TEXT, updated_at TEXT)',
            'CREATE TABLE servers (id INTEGER PRIMARY KEY, type TEXT, hostname TEXT, ip_address TEXT, port INTEGER, access_hash TEXT, created_at TEXT, updated_at TEXT)',
            'CREATE TABLE services (id INTEGER PRIMARY KEY, server_id INTEGER, product_id INTEGER, status TEXT, username TEXT, password TEXT, module_data TEXT, notes TEXT, registration_date TEXT, suspension_date TEXT, suspension_reason TEXT, termination_date TEXT, created_at TEXT, updated_at TEXT)',
            'CREATE TABLE service_addons (id INTEGER PRIMARY KEY, service_id INTEGER, status TEXT, next_due_date TEXT, updated_at TEXT)',
            'CREATE TABLE module_queue (id INTEGER PRIMARY KEY, service_id INTEGER, action TEXT, status TEXT, completed_at TEXT, last_error TEXT, attempts INTEGER, max_attempts INTEGER, next_attempt_at TEXT, payload TEXT, created_at TEXT, updated_at TEXT)',
        ] as $sql) {
            $db->statement($sql);
        }
        (new Product)->forceFill(['id' => 9, 'server_type' => 'aapanel', 'config_options' => ['package_name' => 'Basic']])->save();
        (new Server)->forceFill(['id' => 7, 'type' => 'aapanel', 'hostname' => 'panel.example.test', 'access_hash' => 'private-api-secret'])->save();
        (new Service)->forceFill([
            'id' => 42, 'server_id' => 7, 'product_id' => 9, 'status' => 'active',
            'username' => 'shopuser', 'password' => 'original-password-123',
            'module_data' => ['aapanel_account_id' => 77, 'aapanel_username' => 'shopuser', 'aapanel_server_id' => 7, 'aapanel_owner' => 'pnlcs-test-owner'],
        ])->save();
    }

    private function remoteResponse($request)
    {
        $action = basename(parse_url($request->url(), PHP_URL_PATH), '.json');
        if ($action === 'get_service_info') {
            return Http::response(['status' => 0, 'message' => ['install_status' => 2, 'run_status' => 1]]);
        }
        if ($action === 'get_account_list') {
            return Http::response(['status' => 0, 'message' => ['list' => [$this->remote], 'page' => ['count' => 1]]]);
        }
        if ($action === 'modify_account') {
            $this->remote = array_merge($this->remote, $request->data());
            $this->modifications++;

            return Http::response(['status' => 0]);
        }
        throw new RuntimeException('Unexpected fake endpoint.');
    }

    protected function tearDown(): void
    {
        if ($this->oldResolver) {
            Service::setConnectionResolver($this->oldResolver);
        } else {
            Service::unsetConnectionResolver();
        }
        if ($this->oldDispatcher) {
            Service::setEventDispatcher($this->oldDispatcher);
        } else {
            Service::unsetEventDispatcher();
        }
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($this->oldFacade);
        Container::setInstance($this->oldContainer);
        parent::tearDown();
    }

    public function test_status_commit_blocks_competing_action_on_an_independent_service_instance(): void
    {
        $first = Service::findOrFail(42);
        $second = Service::findOrFail(42);
        self::assertNotSame($first, $second);
        $competing = null;
        $this->events->listen('eloquent.saving: '.Service::class, function (Service $record) use ($second, &$competing): void {
            if ($record->status === 'suspended' && $record->isDirty('status') && $competing === null) {
                $competing = $this->provisioning->unsuspendAccount($second, false);
            }
        });
        $result = $this->provisioning->suspendAccount($first, 'Unpaid invoice', false);
        self::assertTrue($result['success'], $result['message']);
        self::assertFalse($competing['success'], 'Remote status: '.$this->remote['status'].'; stored status: '.Service::findOrFail(42)->status);
        self::assertStringContainsString('already running', $competing['message']);
        self::assertSame(1, $this->modifications);
        self::assertSame(0, $this->remote['status']);
        self::assertSame('suspended', Service::findOrFail(42)->status);
        self::assertSame('Unpaid invoice', Service::findOrFail(42)->suspension_reason);
        self::assertTrue($this->provisioning->unsuspendAccount($second, false)['success']);
        self::assertSame(1, $this->remote['status']);
        self::assertSame('active', Service::findOrFail(42)->status);
    }

    public function test_password_completion_blocks_competing_changes_until_return(): void
    {
        $first = Service::findOrFail(42);
        $second = Service::findOrFail(42);
        $competing = null;
        $this->events->listen('eloquent.saving: '.Service::class, function (Service $record) use ($second, &$competing): void {
            if (! $record->isDirty('module_data')
                && $record->getOriginal('password') === 'first-password-123' && $competing === null) {
                $competing = $this->provisioning->changePassword($second, 'second-password-456');
            }
        });
        $result = $this->provisioning->changePassword($first, 'first-password-123');
        self::assertTrue($result['success'], $result['message']);
        self::assertFalse($competing['success']);
        self::assertStringContainsString('already running', $competing['message']);
        self::assertSame(1, $this->modifications);
        self::assertSame('first-password-123', Service::findOrFail(42)->password);
        self::assertTrue($this->provisioning->changePassword($second, 'second-password-456')['success']);
        self::assertSame('second-password-456', $this->remote['password']);
        self::assertSame('second-password-456', Service::findOrFail(42)->password);
    }

    public static function lifecycleActions(): array
    {
        return [['createAccount'], ['suspendAccount'], ['unsuspendAccount'], ['terminateAccount'], ['changePassword'], ['changePackage']];
    }

    #[DataProvider('lifecycleActions')]
    public function test_all_actions_share_the_same_nonblocking_lock(string $action): void
    {
        $lock = Cache::lock('provisioning:service:42', 7200);
        self::assertTrue($lock->get());
        try {
            $args = match ($action) {
                'changePassword' => ['new-password-123'],
                'changePackage' => [Product::findOrFail(9)],
                default => [],
            };
            $result = $this->provisioning->{$action}(Service::findOrFail(42), ...$args);
            self::assertFalse($result['success']);
            self::assertStringContainsString('already running', $result['message']);
            Http::assertNothingSent();
            self::assertSame('active', Service::findOrFail(42)->status);
        } finally {
            $lock->release();
        }
    }

    #[DataProvider('lifecycleActions')]
    public function test_non_opt_in_provider_preserves_existing_lifecycle_without_the_new_lock(string $action): void
    {
        $remoteAction = match ($action) {
            'createAccount' => 'create', 'suspendAccount' => 'suspend',
            'unsuspendAccount' => 'unsuspend', 'terminateAccount' => 'terminate',
            default => $action,
        };
        $module = $this->createMock(ServerModuleInterface::class);
        $module->expects(self::once())->method($remoteAction)->willReturn(['success' => true, 'message' => 'ok']);
        $registry = $this->createStub(ModuleRegistry::class);
        $registry->method('getServerModule')->willReturn($module);
        $service = Service::findOrFail(42);
        $newProduct = (new Product)->forceFill(['id' => 10, 'server_type' => 'aapanel']);
        $newProduct->save();
        $args = match ($action) {
            'changePassword' => ['new-password-123'],
            'changePackage' => [$newProduct],
            'suspendAccount' => ['Existing behavior', false],
            default => [false],
        };
        $lock = Cache::lock('provisioning:service:42', 7200);
        self::assertTrue($lock->get());
        try {
            $result = (new ProvisioningService($registry))->{$action}($service, ...$args);
            self::assertTrue($result['success'], $result['message']);
            $this->assertLifecycleCommit($action);
            Http::assertNothingSent();
        } finally {
            $lock->release();
        }
    }

    private function assertLifecycleCommit(string $action): void
    {
        $stored = Service::findOrFail(42);
        $expectedStatus = match ($action) {
            'suspendAccount' => 'suspended',
            'terminateAccount' => 'terminated',
            default => 'active',
        };
        self::assertSame($expectedStatus, $stored->status);
        self::assertSame($action === 'changePassword' ? 'new-password-123' : 'original-password-123', $stored->password);
        self::assertSame($action === 'changePackage' ? 10 : 9, $stored->product_id);
        self::assertSame($action === 'suspendAccount' ? 'Existing behavior' : null, $stored->suspension_reason);
    }

    public function test_lock_is_released_after_module_failure(): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response(['status' => -1])]);
        self::assertFalse($this->provisioning->suspendAccount(Service::findOrFail(42), '', false)['success']);
        $lock = Cache::lock('provisioning:service:42', 7200);
        self::assertTrue($lock->get());
        $lock->release();
    }

    public function test_local_commit_failure_releases_lock_and_retry_does_not_repeat_remote_change(): void
    {
        $this->events->listen('eloquent.saving: '.Service::class, function (Service $record): void {
            if ($record->status === 'suspended' && $record->isDirty('status')) {
                throw new RuntimeException('Simulated persistence failure.');
            }
        });
        self::assertFalse($this->provisioning->suspendAccount(Service::findOrFail(42), '', false)['success']);
        self::assertSame('active', Service::findOrFail(42)->status);
        self::assertSame(0, $this->remote['status']);
        self::assertSame(1, $this->modifications);
        $this->events->forget('eloquent.saving: '.Service::class);
        self::assertTrue($this->provisioning->suspendAccount(Service::findOrFail(42), '', false)['success']);
        self::assertSame('suspended', Service::findOrFail(42)->status);
        self::assertSame(1, $this->modifications);
    }

    public function test_observer_veto_of_final_commit_is_failure_and_safe_to_reconcile(): void
    {
        $this->events->listen('eloquent.saving: '.Service::class, function (Service $record) {
            if ($record->status === 'suspended' && $record->isDirty('status')) {
                return false;
            }
        });
        $result = $this->provisioning->suspendAccount(Service::findOrFail(42), '', false);
        self::assertFalse($result['success']);
        self::assertStringContainsString('could not be saved', $result['message']);
        self::assertSame('active', Service::findOrFail(42)->status);
        self::assertSame(0, $this->remote['status']);
        $this->events->forget('eloquent.saving: '.Service::class);
        self::assertTrue($this->provisioning->suspendAccount(Service::findOrFail(42), '', false)['success']);
        self::assertSame('suspended', Service::findOrFail(42)->status);
        self::assertSame(1, $this->modifications);
    }

    public function test_non_opt_in_observer_veto_retains_existing_success_behavior(): void
    {
        $module = $this->createMock(ServerModuleInterface::class);
        $module->expects(self::once())->method('suspend')->willReturn(['success' => true, 'message' => 'ok']);
        $registry = $this->createStub(ModuleRegistry::class);
        $registry->method('getServerModule')->willReturn($module);
        $this->events->listen('eloquent.saving: '.Service::class, fn () => false);
        self::assertTrue((new ProvisioningService($registry))->suspendAccount(Service::findOrFail(42), '', false)['success']);
        self::assertSame('active', Service::findOrFail(42)->status);
    }

    public static function retryTransitions(): array
    {
        return [
            'pending remains pending' => ['pending', false, [], 'pending', 3, false, ['reason' => 'old']],
            'permanent pending fails' => ['pending', true, ['reason' => 'new'], 'failed', 3, false, ['reason' => 'new']],
            'failed permanent remains failed' => ['failed', true, [], 'failed', 3, false, ['reason' => 'old']],
            'failed transient reopens' => ['failed', false, ['reason' => 'new'], 'pending', 0, true, ['reason' => 'new']],
            'reopen preserves prior payload when empty' => ['failed', false, [], 'pending', 0, true, ['reason' => 'old']],
        ];
    }

    #[DataProvider('retryTransitions')]
    public function test_extracted_queue_update_preserves_existing_transitions(string $before, bool $permanent, array $payload, string $after, int $attempts, bool $reopened, array $expectedPayload): void
    {
        $originalDate = '2020-01-01 00:00:00';
        $entry = ModuleQueue::create([
            'service_id' => 42, 'action' => 'suspend', 'status' => $before,
            'attempts' => 3, 'max_attempts' => 5, 'next_attempt_at' => $originalDate,
            'last_error' => 'Old error', 'payload' => ['reason' => 'old'],
        ]);
        $method = new ReflectionMethod(ProvisioningService::class, 'updateRetry');
        $method->invoke($this->provisioning, $entry, $permanent, 'New error', $payload);
        $entry->refresh();
        self::assertSame($after, $entry->status);
        self::assertSame($attempts, $entry->attempts);
        self::assertSame('New error', $entry->last_error);
        self::assertSame($expectedPayload, $entry->payload);
        self::assertSame(5, $entry->max_attempts);
        if ($reopened) {
            self::assertEqualsWithDelta(300, now()->diffInSeconds($entry->next_attempt_at), 2);
        } else {
            self::assertSame($originalDate, $entry->next_attempt_at->format('Y-m-d H:i:s'));
        }
        self::assertSame(1, ModuleQueue::count());
        Http::assertNothingSent();
    }

    public function test_after_hook_failure_preserves_committed_success_and_releases_lock(): void
    {
        app(HookManager::class)->register('AfterModuleSuspend', 1, function (): void {
            throw new RuntimeException('Simulated extension hook failure.');
        });
        self::assertTrue($this->provisioning->suspendAccount(Service::findOrFail(42), '', false)['success']);
        self::assertSame('suspended', Service::findOrFail(42)->status);
        self::assertSame(0, $this->remote['status']);
        self::assertTrue($this->provisioning->unsuspendAccount(Service::findOrFail(42), false)['success']);
        self::assertSame('active', Service::findOrFail(42)->status);
    }

    public function test_after_event_failure_does_not_replay_remote_mutation_on_retry(): void
    {
        $this->events->listen(ServiceSuspended::class, function (): void {
            throw new RuntimeException('Simulated event failure.');
        });
        self::assertFalse($this->provisioning->suspendAccount(Service::findOrFail(42), '', false)['success']);
        self::assertSame('suspended', Service::findOrFail(42)->status);
        self::assertSame(0, $this->remote['status']);
        $this->events->forget(ServiceSuspended::class);
        self::assertTrue($this->provisioning->suspendAccount(Service::findOrFail(42), '', false)['success']);
        self::assertSame(1, $this->modifications);
    }

    public function test_stale_provider_resolution_is_rejected_after_refresh(): void
    {
        $stale = Service::findOrFail(42)->load('product', 'server');
        Server::whereKey(7)->update(['type' => 'changed-provider']);
        $result = $this->provisioning->suspendAccount($stale, '', false);
        self::assertFalse($result['success']);
        self::assertStringContainsString('module changed', $result['message']);
        Http::assertNothingSent();
    }
}
