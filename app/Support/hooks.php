<?php

use App\Services\HookManager;

if (!function_exists('add_hook')) {
    /**
     * Register a hook callback (WHMCS-compatible signature).
     *
     *   add_hook('InvoicePaid', 1, function (array $vars) { ... });
     *   add_hook('InvoicePaid', function (array $vars) { ... });   // priority 10
     *
     * @param string       $hookPoint
     * @param int|callable $priority   Priority (lower runs first) or the callback itself.
     * @param callable|null $callback
     */
    function add_hook(string $hookPoint, $priority, $callback = null): void
    {
        if (is_callable($priority) && $callback === null) {
            $callback = $priority;
            $priority = 10;
        }

        if (!is_callable($callback)) {
            throw new InvalidArgumentException("add_hook('{$hookPoint}'): callback is not callable");
        }

        app(HookManager::class)->register($hookPoint, (int) $priority, $callback);
    }
}

if (!function_exists('run_hook')) {
    /**
     * Fire a hook point and return the callbacks' return values.
     */
    function run_hook(string $hookPoint, array $params = []): array
    {
        return app(HookManager::class)->run($hookPoint, $params);
    }
}

if (!function_exists('hook_output')) {
    /**
     * Fire an output hook and join the HTML its callbacks return, as WHMCS's
     * *Output hooks do (ClientAreaHeadOutput, ClientAreaFooterOutput,
     * ClientAreaProductDetailsOutput, AdminAreaHeadOutput,
     * AdminAreaFooterOutput). Only strings are kept; a callback that throws
     * is logged and skipped by the hook manager. The HTML comes from
     * installed code and is printed as it is.
     */
    function hook_output(string $hookPoint, array $params = []): string
    {
        return implode("\n", array_filter(run_hook($hookPoint, $params), fn ($out) => is_string($out) && $out !== ''));
    }
}
