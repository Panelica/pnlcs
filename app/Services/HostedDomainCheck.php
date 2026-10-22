<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Server;
use App\Services\Module\ModuleRegistry;
use Illuminate\Support\Collection;

/**
 * A hosting order for a domain that is already set up on our servers.
 *
 * Provisioning refuses such a domain before it creates anything, which is
 * right, but by then the order is placed: the service sat in Pending and the
 * customer was told nothing (#212). The customer now hears it while ordering.
 *
 * Asked of the servers the order could be set up on, chosen the way the
 * module chooses at setup (AbstractServerModule::getServer): the product's
 * server group, or else the first active server of its type. A module that
 * cannot tell (no domainHostedOn()), or a server that does not answer, lets
 * the order through as before; setup still checks.
 */
final class HostedDomainCheck
{
    public static function problem(Product $product, ?string $domain): ?string
    {
        $domain = trim((string) $domain);
        if ($domain === '' || ! $product->server_type) {
            return null;
        }

        $module = app(ModuleRegistry::class)->getServerModule((string) $product->server_type);
        if (! $module || ! method_exists($module, 'domainHostedOn')) {
            return null;
        }

        foreach (self::candidates($product, $module->getModuleName()) as $server) {
            if ($module->domainHostedOn($server, $domain) === true) {
                return __('client.cart.domain_already_hosted', ['domain' => $domain]);
            }
        }

        return null;
    }

    /** @return Collection<int, Server> */
    private static function candidates(Product $product, string $type): Collection
    {
        if ($group = $product->serverGroup) {
            return $group->servers()
                ->where('active', true)
                ->where('disabled', false)
                ->where('type', $type)
                ->get();
        }

        $first = Server::where('type', $type)->where('active', true)->first();

        return $first ? collect([$first]) : collect();
    }
}
