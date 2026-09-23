<?php

namespace App\Services\Module;

use App\Contracts\GatewayModuleInterface;
use App\Contracts\RegistrarModuleInterface;
use App\Contracts\ServerModuleInterface;
use App\Contracts\SslModuleInterface;
use App\Contracts\TokenizableGatewayInterface;
use App\Models\GatewaySettings;
use App\Models\Setting;

class ModuleRegistry
{
    protected array $serverModules = [];

    protected array $gatewayModules = [];

    protected array $registrarModules = [];

    protected array $sslModules = [];

    /** @var array<string, array<string, string>> type => key => manifest path, for modules found by discovery */
    protected array $discovered = [];

    /**
     * How the vendors write their own names. getModuleName() is the lookup key
     * for most built-in modules ("cpanel", "gogetssl"), and ucfirst() of that
     * is not a name anyone would recognise on a form.
     */
    private const DISPLAY_NAMES = [
        'cpanel' => 'cPanel/WHM',
        'directadmin' => 'DirectAdmin',
        'hestiacp' => 'HestiaCP',
        'proxmox' => 'Proxmox VE',
        'gogetssl' => 'GoGetSSL',
    ];

    /** The interface each module type must implement, keyed by manifest type. */
    public const TYPE_CONTRACTS = [
        'server' => ServerModuleInterface::class,
        'gateway' => GatewayModuleInterface::class,
        'registrar' => RegistrarModuleInterface::class,
        'ssl' => SslModuleInterface::class,
    ];

    /**
     * Module names are matched case-insensitively. Stored values disagree on
     * case with the registration keys — the registrar modules write
     * "Namecheap"/"Enom"/"Manual" onto domains while they register themselves
     * as "namecheap"/"enom"/"manual" — so an exact lookup silently returned
     * null and callers fell back to doing nothing remotely.
     */
    private static function key(string $name): string
    {
        return strtolower(trim($name));
    }

    /**
     * Register a module found through its pnlcs.json manifest.
     *
     * Two rules, both chosen on purpose:
     *
     *  - An explicit registration wins. The built-in modules already ship
     *    manifests; today none carries a "class", but the day one does, it
     *    must not silently replace the entry the core registered for it.
     *  - The class must be the kind of module its manifest says it is. A
     *    gateway manifest pointing at a server class would load, pass
     *    class_exists(), and fail only when a customer reached checkout.
     *
     * Returns whether the module was registered.
     */
    public function registerDiscovered(string $type, string $name, string $class, string $manifestPath): bool
    {
        $contract = self::TYPE_CONTRACTS[$type] ?? null;

        if ($contract === null || $this->has($type, $name) || ! is_a($class, $contract, true)) {
            return false;
        }

        match ($type) {
            'server' => $this->registerServer($name, $class),
            'gateway' => $this->registerGateway($name, $class),
            'registrar' => $this->registerRegistrar($name, $class),
            'ssl' => $this->registerSsl($name, $class),
        };

        $this->discovered[$type][self::key($name)] = $manifestPath;

        return true;
    }

    public function has(string $type, string $name): bool
    {
        return isset($this->classesOf($type)[self::key($name)]);
    }

    /** Whether this module came from a manifest rather than the core's own list. */
    public function isDiscovered(string $type, string $name): bool
    {
        return isset($this->discovered[$type][self::key($name)]);
    }

    /** @return array<string, string> key => class, for one module type */
    public function classesOf(string $type): array
    {
        return match ($type) {
            'server' => $this->serverModules,
            'gateway' => $this->gatewayModules,
            'registrar' => $this->registrarModules,
            'ssl' => $this->sslModules,
            default => [],
        };
    }

    public function registerServer(string $name, string $class): void
    {
        $this->serverModules[self::key($name)] = $class;
    }

    public function registerGateway(string $name, string $class): void
    {
        $this->gatewayModules[self::key($name)] = $class;
    }

    public function registerRegistrar(string $name, string $class): void
    {
        $this->registrarModules[self::key($name)] = $class;
    }

    public function registerSsl(string $name, string $class): void
    {
        $this->sslModules[self::key($name)] = $class;
    }

    /**
     * The server modules this installation has, for the product form to offer.
     *
     * @return array<string, string> registration key => display name
     */
    public function serverModuleNames(bool $includeSwitchedOff = false): array
    {
        $names = [];

        foreach (array_keys($this->serverModules) as $key) {
            if (! $includeSwitchedOff && $this->isSwitchedOff('server', $key)) {
                continue;
            }

            try {
                // getModuleName() is the lookup key for most modules, which
                // makes a poor label; only use it when it says something more.
                $name = (string) ($this->getServerModule($key)?->getModuleName() ?? '');
                $names[$key] = self::DISPLAY_NAMES[$key]
                    ?? (strtolower($name) === $key || $name === '' ? ucfirst($key) : $name);
            } catch (\Throwable) {
                $names[$key] = ucfirst($key);
            }
        }

        ksort($names);

        return $names;
    }

    /**
     * The SSL modules this installation offers, for the product form.
     *
     * @return array<string, string> registration key => display name
     */
    public function sslModuleNames(bool $includeSwitchedOff = false): array
    {
        $names = [];

        foreach (array_keys($this->sslModules) as $key) {
            if (! $includeSwitchedOff && $this->isSwitchedOff('ssl', $key)) {
                continue;
            }
            try {
                $name = (string) ($this->getSslModule($key)?->getModuleName() ?? '');
                $names[$key] = self::DISPLAY_NAMES[$key]
                    ?? (strtolower($name) === $key || $name === '' ? ucfirst($key) : $name);
            } catch (\Throwable) {
                $names[$key] = ucfirst($key);
            }
        }

        ksort($names);

        return $names;
    }

    /**
     * Whether an operator switched this server or SSL module off on the
     * modules screen. Off means "do not offer it when choosing a module";
     * nothing already using it is affected, and ModuleSwitchboard refuses to
     * switch off a module anything uses.
     *
     * Gateways, registrars and addons are not asked here: each has had its own
     * switch for longer, read where it matters (see ModuleSwitchboard).
     */
    public function isSwitchedOff(string $type, string $name): bool
    {
        try {
            return Setting::get(self::switchKey($type, $name), '0') === '1';
        } catch (\Throwable) {
            // Install and migrate run before the settings table exists.
            return false;
        }
    }

    public function switchOff(string $type, string $name, bool $off): void
    {
        Setting::set(self::switchKey($type, $name), $off ? '1' : '0', 'modules');
    }

    private static function switchKey(string $type, string $name): string
    {
        return 'module_'.$type.'_'.self::key($name).'_off';
    }

    /**
     * Which credential a server module signs in with.
     *
     * 'token'  → the API key field, and nothing else will do
     * 'either' → an API key or a password
     * 'none'   → neither (a bare record, no remote calls)
     */
    public function serverCredentialRequirement(?string $type): string
    {
        return match (self::key((string) $type)) {
            'cpanel', 'panelica', 'plesk', 'proxmox', 'vultr' => 'token',
            'directadmin', 'hestiacp' => 'either',
            default => 'none',
        };
    }

    public function getServerModule(string $name): ?ServerModuleInterface
    {
        $class = $this->serverModules[self::key($name)] ?? null;

        return $class ? app($class) : null;
    }

    /**
     * The gateways that are switched on and have what they need to work.
     *
     * Being ticked active is one setting; the keys a gateway authenticates
     * with are others, and offering one without them means the customer picks
     * it and the payment fails at the last step, after the order is placed.
     * The modules already declare their required fields.
     *
     * @return array<int, string>
     */
    public function usableGateways(): array
    {
        $stored = GatewaySettings::all()->groupBy('gateway');

        $usable = [];

        foreach ($stored as $gateway => $rows) {
            $values = $rows->filter(fn ($row) => trim((string) $row->value) !== '')
                ->pluck('value', 'setting');

            if ((string) ($values['active'] ?? '0') !== '1') {
                continue;
            }

            $module = $this->getGatewayModule((string) $gateway);

            if (! $module) {
                continue;
            }

            $missing = collect($module->getConfigFields())
                ->filter(fn ($field) => $field['required'] ?? false)
                ->reject(fn ($field) => $values->has($field['name']))
                ->isNotEmpty();

            if ($missing) {
                continue;
            }

            $usable[] = (string) $gateway;
        }

        return $usable;
    }

    /**
     * The usable gateways that can also store a card and charge it later.
     *
     * Two questions in one place, because the two screens that ask it must
     * never disagree. The charger asks it to decide what it may present a card
     * to; the client portal asks it to decide whether to offer to store a card
     * at all. A portal that offered storage for a gateway the charger cannot
     * use would collect card details for nothing, and a charger that reached
     * for a gateway the portal never offered would charge a card the customer
     * has not agreed to store.
     *
     * isTokenised() is not the test. It says a gateway keeps a handle to a
     * card; it says nothing about charging that handle with nobody watching,
     * which is a different promise with different failure modes, so the
     * capability interface is what is asked.
     *
     * @return array<string, TokenizableGatewayInterface> keyed by lower-case gateway name
     */
    public function tokenisedGateways(): array
    {
        $found = [];

        foreach ($this->usableGateways() as $name) {
            $module = $this->getGatewayModule($name);

            if ($module instanceof TokenizableGatewayInterface) {
                $found[self::key($name)] = $module;
            }
        }

        return $found;
    }

    /**
     * Could this gateway ever detach a stored card, whatever its settings say?
     *
     * A CAPABILITY QUESTION, DELIBERATELY NOT A CONFIGURATION ONE, and the
     * difference is the whole point. tokenisedGateways() answers "is this
     * gateway switched on and does it have its keys", which changes minute to
     * minute: an operator rotating a secret key would, if that were the test,
     * turn every card removed in those five minutes into a card PNLCS never
     * asks the gateway to let go of. This asks whether the module implements
     * the interface at all, which is a fact about the code and cannot be true
     * one morning and false the next.
     *
     * The class string is tested rather than an instance, so asking costs no
     * container resolution and no gateway construction.
     *
     * A gateway with no module registered answers false. Nothing in this
     * installation could carry out a detach for it, and recording a request
     * that nothing can perform is exactly the failure this exists to stop.
     */
    public function canDetachStoredMethods(string $name): bool
    {
        $class = $this->gatewayModules[self::key($name)] ?? null;

        return $class !== null && is_a($class, TokenizableGatewayInterface::class, true);
    }

    public function getGatewayModule(string $name): ?GatewayModuleInterface
    {
        $class = $this->gatewayModules[self::key($name)] ?? null;

        return $class ? app($class) : null;
    }

    public function getRegistrarModule(string $name): ?RegistrarModuleInterface
    {
        $class = $this->registrarModules[self::key($name)] ?? null;

        return $class ? app($class) : null;
    }

    public function getSslModule(string $name): ?SslModuleInterface
    {
        $class = $this->sslModules[self::key($name)] ?? null;

        return $class ? app($class) : null;
    }

    public function resolveServer(string $name): ?ServerModuleInterface
    {
        return $this->getServerModule($name);
    }

    public function resolveGateway(string $name): ?GatewayModuleInterface
    {
        return $this->getGatewayModule($name);
    }

    public function resolveSsl(string $name): ?SslModuleInterface
    {
        return $this->getSslModule($name);
    }

    public function getServerModules(): array
    {
        return array_keys($this->serverModules);
    }

    public function getGatewayModules(): array
    {
        return array_keys($this->gatewayModules);
    }

    public function getRegistrarModules(): array
    {
        return array_keys($this->registrarModules);
    }

    public function getSslModules(): array
    {
        return array_keys($this->sslModules);
    }
}
