<?php

namespace App\Services\Module;

use App\Contracts\GatewayModuleInterface;
use App\Contracts\RegistrarModuleInterface;
use App\Contracts\ServerModuleInterface;
use App\Contracts\SslModuleInterface;
use App\Models\GatewaySettings;

class ModuleRegistry
{
    protected array $serverModules = [];

    protected array $gatewayModules = [];

    protected array $registrarModules = [];

    protected array $sslModules = [];

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
    public function serverModuleNames(): array
    {
        $names = [];

        foreach (array_keys($this->serverModules) as $key) {
            try {
                // getModuleName() is the lookup key for most modules, which
                // makes a poor label; only use it when it says something more.
                $name = (string) ($this->getServerModule($key)?->getModuleName() ?? '');
                $names[$key] = strtolower($name) === $key || $name === '' ? ucfirst($key) : $name;
            } catch (\Throwable) {
                $names[$key] = ucfirst($key);
            }
        }

        ksort($names);

        return $names;
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
