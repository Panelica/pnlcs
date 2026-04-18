<?php

namespace App\Services\Module;

class ModuleRegistry
{
    protected array $serverModules = [];
    protected array $gatewayModules = [];
    protected array $registrarModules = [];
    protected array $sslModules = [];

    public function registerServer(string $name, string $class): void
    {
        $this->serverModules[$name] = $class;
    }

    public function registerGateway(string $name, string $class): void
    {
        $this->gatewayModules[$name] = $class;
    }

    public function registerRegistrar(string $name, string $class): void
    {
        $this->registrarModules[$name] = $class;
    }

    public function registerSsl(string $name, string $class): void
    {
        $this->sslModules[$name] = $class;
    }

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

    public function getSslModule(string $name): ?\App\Contracts\SslModuleInterface
    {
        $class = $this->sslModules[$name] ?? null;
        return $class ? app($class) : null;
    }

    public function resolveServer(string $name): ?\App\Contracts\ServerModuleInterface
    {
        return $this->getServerModule($name);
    }

    public function resolveGateway(string $name): ?\App\Contracts\GatewayModuleInterface
    {
        return $this->getGatewayModule($name);
    }

    public function resolveSsl(string $name): ?\App\Contracts\SslModuleInterface
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
