<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * WHMCS-style hook system.
 *
 * Modules (addons, gateways, servers, registrars) and project code register
 * callbacks against named hook points with add_hook(); core code fires them
 * with run_hook(). Laravel events are bridged to hook points by
 * HookServiceProvider, so every domain event is also a hook point.
 *
 * Guarantees:
 *  - Callbacks run in priority order (lower first), FIFO within a priority.
 *  - A throwing callback is logged and skipped — a broken addon can never
 *    break billing or provisioning.
 *  - Hook names are case-insensitive.
 */
class HookManager
{
    /** @var array<string, array<int, array{priority: int, seq: int, callback: callable}>> */
    protected array $hooks = [];

    protected int $seq = 0;

    /** Fired hook log for the current request — [name, paramKeys, callbackCount] */
    protected array $fired = [];

    public function register(string $hookPoint, int $priority, callable $callback): void
    {
        $key = strtolower($hookPoint);
        $this->hooks[$key][] = [
            'priority' => $priority,
            'seq'      => $this->seq++,
            'callback' => $callback,
        ];
    }

    /**
     * Run all callbacks registered for a hook point.
     *
     * @param  array $params  Named parameters passed to each callback.
     * @return array          Non-null return values, in execution order.
     *                        Array returns are appended as-is (WHMCS behavior).
     */
    public function run(string $hookPoint, array $params = []): array
    {
        $key = strtolower($hookPoint);
        $entries = $this->hooks[$key] ?? [];

        $this->fired[] = ['hook' => $hookPoint, 'callbacks' => count($entries)];

        if ($entries === []) {
            return [];
        }

        usort($entries, fn ($a, $b) => [$a['priority'], $a['seq']] <=> [$b['priority'], $b['seq']]);

        $results = [];
        foreach ($entries as $entry) {
            try {
                $return = ($entry['callback'])($params);
                if ($return !== null) {
                    $results[] = $return;
                }
            } catch (\Throwable $e) {
                Log::error("Hook callback failed [{$hookPoint}]: " . $e->getMessage(), [
                    'exception' => get_class($e),
                ]);
            }
        }

        return $results;
    }

    public function has(string $hookPoint): bool
    {
        return !empty($this->hooks[strtolower($hookPoint)]);
    }

    /** @return array<string, int> hook point => callback count */
    public function registered(): array
    {
        return array_map('count', $this->hooks);
    }

    /** Hook points fired during this request (debugging aid). */
    public function firedLog(): array
    {
        return $this->fired;
    }

    /**
     * Load every PHP hook file in a directory (each file calls add_hook()).
     * Used for app/Hooks/ and modules/* hook files.
     */
    public function loadHookFilesFrom(string $directory): int
    {
        if (!is_dir($directory)) {
            return 0;
        }

        $loaded = 0;
        foreach (glob($directory . '/*.php') ?: [] as $file) {
            try {
                require_once $file;
                $loaded++;
            } catch (\Throwable $e) {
                Log::error("Hook file failed to load: {$file} — " . $e->getMessage());
            }
        }

        return $loaded;
    }
}
