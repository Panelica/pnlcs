<?php
namespace App\Services\Module;

class ModuleRegistry
{
    protected array $serverModules = [];
    protected array $gatewayModules = [];
    protected array $registrarModules = [];

    public function registerServer(string $name, string $class): void { $this->serverModules[$name] = $class; }
    public function registerGateway(string $name, string $class): void { $this->gatewayModules[$name] = $class; }
    public function registerRegistrar(string $name, string $class): void { $this->registrarModules[$name] = $class; }

    public function getServerModule(string $name): ?\App\Contracts\ServerModuleInterface
    {
        $class = $this->serverModules[$name] ?? null;
        return $class ? app($class) : null;
    }

    public function getGatewayModule(string $name): ?\App\Contracts\GatewayModuleInterface
    {
        $class = $this->gatewayModules[$name] ?? null;
        return $class ? app($class) : null;
    }

    public function getRegistrarModule(string $name): ?\App\Contracts\RegistrarModuleInterface
    {
        $class = $this->registrarModules[$name] ?? null;
        return $class ? app($class) : null;
    }

    public function getServerModules(): array { return array_keys($this->serverModules); }
    public function getGatewayModules(): array { return array_keys($this->gatewayModules); }
    public function getRegistrarModules(): array { return array_keys($this->registrarModules); }
}
