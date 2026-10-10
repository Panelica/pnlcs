<?php

use App\Http\Controllers\Admin\ConfigController;
use App\Models\Server;
use App\Providers\ModuleServiceProvider;
use App\Services\Module\ModuleRegistry;
use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Modules\Servers\AaPanel\AaPanelModule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/** Isolated setup checks; no application boot, database, or remote requests. */
final class AaPanelSetupTest extends TestCase
{
    private ?Container $previousContainer;

    private Application $app;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousContainer = Container::getInstance();
        $this->app = new Application(dirname(__DIR__, 2));
        $this->app->instance('translator', new Translator(new ArrayLoader, 'en'));
        (new ModuleServiceProvider($this->app))->register();
    }

    protected function tearDown(): void
    {
        Container::setInstance($this->previousContainer);
        parent::tearDown();
    }

    public function test_provider_discovers_aapanel_without_manual_registration(): void
    {
        $registry = $this->app->make(ModuleRegistry::class);
        self::assertTrue($registry->isDiscovered('server', 'aapanel'));
        self::assertInstanceOf(AaPanelModule::class, $registry->getServerModule('AaPanel'));
        self::assertSame('aaPanel', $registry->serverModuleNames(true)['aapanel']);
        self::assertSame('token', $registry->serverCredentialRequirement(' AaPanel '));
    }

    public function test_discovery_rejects_unknown_type_and_wrong_contract_before_registration(): void
    {
        $registry = new ModuleRegistry;
        $manifest = dirname(__DIR__, 2).'/modules/Servers/AaPanel/pnlcs.json';
        self::assertFalse($registry->registerDiscovered('unknown', 'aapanel', AaPanelModule::class, $manifest));
        self::assertFalse($registry->registerDiscovered('gateway', 'aapanel', AaPanelModule::class, $manifest));
        self::assertSame([], $registry->getServerModules());
        self::assertSame([], $registry->getGatewayModules());
    }

    public static function credentialCases(): array
    {
        return [
            'active password only' => [true, '', 'password-only', true],
            'active API secret' => [true, 'api-secret', '', false],
            'inactive unfinished record' => [false, '', '', false],
        ];
    }

    #[DataProvider('credentialCases')]
    public function test_server_form_requires_api_secret_when_active(bool $active, string $token, string $password, bool $fails): void
    {
        $request = Request::create('/', 'POST', [
            'type' => 'aapanel', 'active' => $active, 'access_hash' => $token, 'password' => $password,
        ]);
        $method = new ReflectionMethod(ConfigController::class, 'credentialError');
        $error = $method->invoke(new ConfigController, $request);
        self::assertSame($fails, $error !== null);
    }

    public function test_blank_edit_secret_keeps_existing_credential(): void
    {
        $server = new class extends Server
        {
            public function getAccessHashAttribute($value): string
            {
                return 'existing-api-secret';
            }
        };
        $request = Request::create('/', 'POST', ['type' => 'aapanel', 'active' => true, 'access_hash' => '']);
        $method = new ReflectionMethod(ConfigController::class, 'credentialError');
        self::assertNull($method->invoke(new ConfigController, $request, $server));
    }

    public function test_edit_form_preserves_nullable_port_until_type_default_is_applied(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/admin/config/servers.blade.php');
        self::assertStringContainsString('{{ json_encode($server->port) }}', $view);
        self::assertStringContainsString("document.getElementById('edit-port').value = serverPort(type, port);", $view);
        self::assertStringNotContainsString('(int)($server->port ?? 8443)', $view);
    }

    public function test_edit_port_defaults_and_explicit_ports_in_actual_javascript_helper(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/admin/config/servers.blade.php');
        preg_match_all('/^    ([a-z]+): \{\s*port: (\d+)/m', $view, $ports, PREG_SET_ORDER);
        $tuning = [];
        foreach ($ports as $port) {
            $tuning[$port[1]] = ['port' => (int) $port[2]];
        }
        preg_match('/function serverPort\(type, port\) \{[^}]+\}/', $view, $helper);
        self::assertNotEmpty($helper);
        $script = 'const SERVER_TYPE_TUNING = '.json_encode($tuning, JSON_THROW_ON_ERROR).';'.$helper[0];
        $script .= 'console.log(JSON.stringify([serverPort("aapanel", null), serverPort("aapanel", 9443), serverPort("cpanel", null), serverPort("proxmox", null), serverPort("custom", null), serverPort("unknown", null)]));';
        $process = new Process(['node', '-e', $script]);
        $process->mustRun();
        self::assertSame([8888, 9443, 2087, 8006, 8443, 8443], json_decode(trim($process->getOutput()), true, 512, JSON_THROW_ON_ERROR));
    }

    public static function localeCases(): array
    {
        return [['en'], ['tr'], ['de'], ['pl'], ['zh']];
    }

    #[DataProvider('localeCases')]
    public function test_aapanel_setup_translations_have_all_keys(string $locale): void
    {
        $root = dirname(__DIR__, 2).'/lang/';
        $english = require $root.'en/aapanel.php';
        $translated = require $root.$locale.'/aapanel.php';
        self::assertSame(array_keys($english), array_keys($translated));
        foreach ($translated as $value) {
            self::assertIsString($value);
            self::assertNotSame('', trim($value));
        }
    }
}
