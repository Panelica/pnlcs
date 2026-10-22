<?php

namespace App\Services\Module;

use App\Contracts\ServerModuleInterface;
use App\Models\Service;
use App\Services\ProvisioningService;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;

/**
 * What a server module adds to a service's screens and mail.
 *
 * Every method read here is optional on the module, and the default is what
 * the screens did before: a module that offers none of them, or one that
 * throws, changes nothing. A module that sells something other than an
 * account on a machine (a software license, a resold service) uses them to
 * show its own section instead of a server name and IP that mean nothing to
 * the customer.
 *
 *   clientAreaPanel(Service): ?Htmlable   a section on the customer's service page
 *   adminAreaPanel(Service): ?Htmlable    a section on the staff service page
 *   customFunctions(?Service): array      staff buttons, method => label (also the API's modulecustom)
 *   clientFunctions(?Service): array      customer buttons, method => label
 *   servicePageHides(): array             'server_details', 'change_password'
 *   isProvisioned(Service): ?bool         whether Create has anything left to do
 *   accountOnServer(Service): ?bool       whether the account still exists on the server
 *                                         (null: the server could not be asked just now)
 *   emailMergeFields(Service): array      extra {fields} for the service's mail
 *
 * A function named in customFunctions() or clientFunctions() takes the service
 * and answers like any module action: ['success' => bool, 'message' => string].
 */
final class ModuleScreens
{
    private function __construct(private ?ServerModuleInterface $module, private Service $service) {}

    public static function for(Service $service): self
    {
        try {
            $module = app(ProvisioningService::class)->resolveModule($service);
        } catch (\Throwable) {
            $module = null;
        }

        return new self($module, $service);
    }

    public function clientPanel(): ?Htmlable
    {
        return $this->panel('clientAreaPanel');
    }

    public function adminPanel(): ?Htmlable
    {
        return $this->panel('adminAreaPanel');
    }

    /** @return array<string, string> method => label */
    public function adminButtons(): array
    {
        return $this->functions('customFunctions');
    }

    /** @return array<string, string> method => label */
    public function clientButtons(): array
    {
        return $this->functions('clientFunctions');
    }

    /** Whether the service page shows this part: 'server_details' or 'change_password'. */
    public function shows(string $part): bool
    {
        $hidden = $this->ask('servicePageHides', []);

        return ! in_array($part, is_array($hidden) ? $hidden : [], true);
    }

    /** True/false when the module knows, null when the page should decide as before. */
    public function isProvisioned(): ?bool
    {
        $answer = $this->ask('isProvisioned', null, [$this->service]);

        return is_bool($answer) ? $answer : null;
    }

    /** Whether the module can say if the service's account is still on its server. */
    public function checksAccounts(): bool
    {
        return $this->module !== null && method_exists($this->module, 'accountOnServer');
    }

    /** True/false when the module could ask the server, null when it could not. */
    public function accountOnServer(): ?bool
    {
        $answer = $this->ask('accountOnServer', null, [$this->service]);

        return is_bool($answer) ? $answer : null;
    }

    /** @return array<string, string> */
    public function mergeFields(): array
    {
        $fields = $this->ask('emailMergeFields', [], [$this->service]);

        return is_array($fields)
            ? array_map(fn ($value) => (string) $value, array_filter($fields, fn ($value) => is_scalar($value)))
            : [];
    }

    /**
     * Run one of the module's own functions on the service.
     *
     * $kind is 'admin' (customFunctions) or 'client' (clientFunctions). Only a
     * function the module lists for that side is run, so a customer cannot
     * reach a staff button by guessing its name.
     */
    public function run(string $kind, string $function): ?array
    {
        $offered = $kind === 'client' ? $this->clientButtons() : $this->adminButtons();

        if (! array_key_exists($function, $offered) || ! method_exists($this->module, $function)) {
            return null;
        }

        try {
            return (array) $this->module->{$function}($this->service);
        } catch (\Throwable $e) {
            Log::warning("Module function {$function} failed for service #{$this->service->id}: {$e->getMessage()}");

            // No message: each screen says it failed in its own words.
            return ['success' => false];
        }
    }

    /**
     * Rendered here rather than on the page, so a section that fails to draw
     * leaves the rest of the service page standing.
     */
    private function panel(string $method): ?Htmlable
    {
        $panel = $this->ask($method, null, [$this->service]);
        if (! $panel instanceof Htmlable) {
            return null;
        }

        try {
            return new HtmlString($panel->toHtml());
        } catch (\Throwable $e) {
            Log::warning("Module {$method} could not be drawn for service #{$this->service->id}: {$e->getMessage()}");

            return null;
        }
    }

    /** @return array<string, string> */
    private function functions(string $method): array
    {
        // The service is offered so a module can list only what applies to
        // it; a module that declares no parameter simply does not read it.
        $functions = $this->ask($method, [], [$this->service]);
        if (! is_array($functions)) {
            return [];
        }

        return array_filter(
            array_map(fn ($label) => (string) $label, array_filter($functions, fn ($label) => is_scalar($label))),
            fn ($label, $name) => is_string($name) && $name !== '' && method_exists($this->module, $name),
            ARRAY_FILTER_USE_BOTH
        );
    }

    private function ask(string $method, mixed $default, array $arguments = []): mixed
    {
        if (! $this->module || ! method_exists($this->module, $method)) {
            return $default;
        }

        try {
            return $this->module->{$method}(...$arguments);
        } catch (\Throwable $e) {
            Log::warning("Module {$method} failed for service #{$this->service->id}: {$e->getMessage()}");

            return $default;
        }
    }
}
