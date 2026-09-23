<?php

namespace App\Services\Module;

use App\Models\GatewaySettings;
use App\Models\Product;
use App\Models\RegistrarSettings;
use App\Models\Server;
use App\Services\AddonManager;
use Illuminate\Support\Collection;

/**
 * Every installed module on one screen, each with its on/off switch.
 *
 * NO NEW SWITCH WHERE ONE ALREADY EXISTS. Gateways, registrars and addons
 * each had an on/off setting long before this screen, read by the code that
 * matters (checkout, domain search, the addon loader). This class reads and
 * writes exactly those settings, so flipping a gateway here and on the
 * gateways page is the same act. Only server and SSL modules had no switch;
 * theirs lives in ModuleRegistry::isSwitchedOff(), next to the lists it
 * filters.
 *
 * A server or SSL module that anything uses cannot be switched off. Switching
 * it off hides it from the forms that choose a module, and a product or server
 * already pointing at it would then fail validation the next time anyone
 * saved it. Gateways, registrars and addons keep their existing behaviour: the
 * pages that switched them off never refused, and existing records keep
 * working when they are off.
 */
class ModuleSwitchboard
{
    public const TYPES = ['server', 'gateway', 'registrar', 'ssl', 'addon'];

    public function __construct(
        private ModuleRegistry $registry,
        private AddonManager $addons,
    ) {}

    /**
     * @return Collection<string, Collection<int, object>> type => rows
     */
    public function rows(): Collection
    {
        $rows = collect();

        foreach (['server', 'gateway', 'registrar', 'ssl'] as $type) {
            $rows[$type] = collect(array_keys($this->registry->classesOf($type)))
                ->map(fn (string $key) => (object) [
                    'type' => $type,
                    'key' => $key,
                    'label' => $this->label($type, $key),
                    'third_party' => $this->registry->isDiscovered($type, $key),
                    'active' => $this->isActive($type, $key),
                    'in_use' => $this->usage($type, $key),
                ])
                ->sortBy(fn ($row) => mb_strtolower($row->label))
                ->values();
        }

        $rows['addon'] = $this->addons->all()
            ->map(fn ($addon, $key) => (object) [
                'type' => 'addon',
                'key' => (string) $key,
                'label' => $addon->getDisplayName(),
                'third_party' => false,
                'active' => $this->addons->isActive((string) $key),
                'in_use' => 0,
            ])
            ->sortBy(fn ($row) => mb_strtolower($row->label))
            ->values();

        return $rows;
    }

    public function exists(string $type, string $key): bool
    {
        return $type === 'addon'
            ? $this->addons->find($key) !== null
            : $this->registry->has($type, $key);
    }

    public function isActive(string $type, string $key): bool
    {
        return match ($type) {
            'gateway' => (string) $this->setting(GatewaySettings::class, 'gateway', $key, 'active', '0') === '1',
            // The manual registrar is offered unless it was explicitly hidden;
            // every other registrar is hidden until switched on. Same rule as
            // DomainController::activeRegistrarKeys().
            'registrar' => (string) $this->setting(RegistrarSettings::class, 'registrar', $key, 'visible', $key === 'manual' ? '1' : '0') === '1',
            'addon' => $this->addons->isActive($key),
            'server', 'ssl' => ! $this->registry->isSwitchedOff($type, $key),
            default => false,
        };
    }

    /**
     * How many records point at this module, for the modules that may not be
     * switched off while used.
     */
    public function usage(string $type, string $key): int
    {
        return match ($type) {
            'server' => Server::whereRaw('LOWER(type) = ?', [$key])->count()
                + Product::whereRaw('LOWER(server_type) = ?', [$key])->count(),
            'ssl' => Product::whereRaw('LOWER(ssl_module) = ?', [$key])->count(),
            default => 0,
        };
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function setActive(string $type, string $key, bool $on): array
    {
        if (! $on && in_array($type, ['server', 'ssl'], true) && ($used = $this->usage($type, $key)) > 0) {
            return ['success' => false, 'message' => __('admin.modules.in_use_cannot_disable', ['count' => $used])];
        }

        switch ($type) {
            case 'gateway':
                GatewaySettings::updateOrCreate(['gateway' => $key, 'setting' => 'active'], ['value' => $on ? '1' : '0']);
                break;
            case 'registrar':
                RegistrarSettings::updateOrCreate(['registrar' => $key, 'setting' => 'visible'], ['value' => $on ? '1' : '0']);
                break;
            case 'addon':
                $result = $on ? $this->addons->activate($key) : $this->addons->deactivate($key);

                return [
                    'success' => (bool) ($result['success'] ?? false),
                    'message' => (string) ($result['message'] ?? __('messages.success.settings_updated')),
                ];
            case 'server':
            case 'ssl':
                $this->registry->switchOff($type, $key, ! $on);
                break;
            default:
                return ['success' => false, 'message' => __('admin.modules.not_found')];
        }

        return ['success' => true, 'message' => __($on ? 'admin.modules.enabled' : 'admin.modules.disabled')];
    }

    private function label(string $type, string $key): string
    {
        try {
            return match ($type) {
                'server' => $this->registry->serverModuleNames(true)[$key] ?? ucfirst($key),
                'gateway' => payment_method_label($key) ?: ($this->registry->getGatewayModule($key)?->getModuleName() ?? ucfirst($key)),
                'registrar' => $this->registry->getRegistrarModule($key)?->getModuleName() ?? ucfirst($key),
                'ssl' => $this->registry->sslModuleNames(true)[$key] ?? ucfirst($key),
                default => ucfirst($key),
            };
        } catch (\Throwable) {
            return ucfirst($key);
        }
    }

    private function setting(string $model, string $column, string $key, string $setting, string $default): string
    {
        $value = $model::where($column, $key)->where('setting', $setting)->first()?->value;

        return $value === null || $value === '' ? $default : (string) $value;
    }
}
