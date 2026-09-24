<?php

namespace App\Support;

use Illuminate\Routing\Route;

/**
 * The API reference as data: the routed endpoints joined to config/api_docs.php.
 *
 * Read by the admin panel's API Documentation screen and by the command that
 * writes the public reference (pnlcs:api-docs), so the two always say the same
 * thing about the same call.
 */
class ApiReference
{
    /** Public reference sections, in the order the reference lists them. */
    public const SECTIONS = [
        'clients' => 'Clients',
        'invoices' => 'Invoices & Billing',
        'orders' => 'Orders',
        'services' => 'Services & Products',
        'domains' => 'Domains',
        'tickets' => 'Support Tickets',
        'quotes' => 'Quotes',
        'projects' => 'Projects',
        'affiliates' => 'Affiliates',
        'ssl' => 'SSL Certificates',
        'credentials' => 'API Credentials',
        'system' => 'System',
    ];

    /**
     * Every routed endpoint under /api/v1, keyed by action name.
     *
     * @return array<string, array{action: string, method: string, controller: ?string, handler: string, spec: array}>
     */
    public static function endpoints(): array
    {
        $spec = config('api_docs', []);
        $out = [];

        foreach (app('router')->getRoutes() as $route) {
            /** @var Route $route */
            if (! str_starts_with($route->uri(), 'api/v1/')) {
                continue;
            }

            $action = basename($route->uri());
            $out[$action] = [
                'action' => $action,
                'method' => in_array('POST', $route->methods(), true) ? 'POST' : 'GET',
                'controller' => $route->getControllerClass(),
                'handler' => $route->getActionMethod(),
                'spec' => $spec[$action] ?? [],
            ];
        }

        ksort($out);

        return $out;
    }

    /**
     * The parameters of an entry, required first as written.
     *
     * @return list<array{name: string, type: string, required: bool, description: string}>
     */
    public static function params(array $spec): array
    {
        $params = [];

        foreach ($spec['params'] ?? [] as $name => [$type, $description]) {
            $params[] = [
                'name' => ltrim($name, '*'),
                'type' => $type,
                'required' => str_starts_with($name, '*'),
                'description' => $description,
            ];
        }

        if (! empty($spec['list'])) {
            $params[] = ['name' => 'limitstart', 'type' => 'integer', 'required' => false, 'description' => 'Where the page starts. Default 0.'];
            $params[] = ['name' => 'limitnum', 'type' => 'integer', 'required' => false, 'description' => 'Page size, 1 to 250. Default 25.'];
        }

        return $params;
    }

    /** The short parameter line the admin screen shows next to an endpoint. */
    public static function hint(array $spec): string
    {
        if (isset($spec['unavailable'])) {
            return $spec['unavailable'];
        }

        $hint = implode(', ', array_map(
            fn (array $p) => $p['name'].($p['required'] ? ' (required)' : ''),
            self::params($spec)
        ));

        return isset($spec['one_of'])
            ? $hint.' - one of '.implode(' or ', $spec['one_of']).' is required'
            : $hint;
    }

    /**
     * Values for a call that reaches the endpoint: the entry's own example, or
     * one placeholder per required parameter (and the first of one_of).
     *
     * @return array<string, string>
     */
    public static function example(array $spec): array
    {
        if (isset($spec['example'])) {
            return array_map('strval', $spec['example']);
        }

        $wanted = array_filter(self::params($spec), fn (array $p) => $p['required'] || $p['name'] === ($spec['one_of'][0] ?? null));
        $values = [];
        foreach ($wanted as $p) {
            $values[$p['type'] === 'array' ? $p['name'].'[0]' : $p['name']] = match ($p['type']) {
                'integer', 'array', 'boolean' => '1',
                'number' => '10.00',
                'date' => '2026-10-01',
                'email' => 'client@example.com',
                'url' => 'https://example.com',
                default => 'example',
            };
        }

        return $values;
    }

    /** The permission key a call needs, or null when only a full administrator will do. */
    public static function permission(array $endpoint): ?string
    {
        return ApiPermissionMap::required($endpoint['controller'], $endpoint['method'], $endpoint['handler']);
    }
}
