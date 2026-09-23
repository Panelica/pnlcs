<?php

namespace App\Support;

use App\Models\Client;
use App\Models\User;

/**
 * What a login may do on an account it belongs to.
 *
 * A login can be added to an account by invitation with a subset of these
 * (the WHMCS permission names). The account's owner, and any login whose set
 * was never restricted, may do everything - so every login that existed
 * before this, and every owner, keeps exactly the access it had.
 */
class ClientPermissions
{
    public const ALL = [
        'profile', 'contacts', 'products', 'manageproducts', 'productsso',
        'domains', 'managedomains', 'invoices', 'quotes', 'tickets',
        'affiliates', 'emails', 'orders',
    ];

    /**
     * The permission a client-area route needs, by its name. Null: open to
     * every login on the account (the dashboard, signing out, the login's own
     * password and security, switching accounts, downloads).
     */
    public static function forRoute(?string $name): ?string
    {
        $name = (string) $name;

        return match (true) {
            $name === 'client.services.login' => 'productsso',
            in_array($name, ['client.services.index', 'client.services.show', 'client.services.usage'], true) => 'products',
            str_starts_with($name, 'client.services.') => 'manageproducts',
            in_array($name, ['client.ssl.index', 'client.ssl.show'], true) => 'products',
            str_starts_with($name, 'client.ssl.') => 'manageproducts',
            in_array($name, ['client.domains.index', 'client.domains.show'], true) => 'domains',
            str_starts_with($name, 'client.domains.') => 'managedomains',
            str_starts_with($name, 'client.invoices.'),
            str_starts_with($name, 'client.payment-methods.'),
            str_starts_with($name, 'client.funds.'),
            $name === 'client.account.payment_methods' => 'invoices',
            str_starts_with($name, 'client.quotes.') => 'quotes',
            str_starts_with($name, 'client.tickets.') => 'tickets',
            str_starts_with($name, 'client.affiliates.') => 'affiliates',
            str_starts_with($name, 'client.emails.') => 'emails',
            in_array($name, ['client.account.profile', 'client.account.update'], true) => 'profile',
            str_starts_with($name, 'client.account.contacts') => 'contacts',
            in_array($name, ['client.cart.checkout', 'client.cart.process'], true) => 'orders',
            default => null,
        };
    }

    /**
     * The permissions a login holds on an account: every one of them, or the
     * restricted set it was given. Null when the login is not on the account.
     *
     * @return list<string>|null
     */
    public static function granted(User $user, Client $client): ?array
    {
        $pivot = $user->clients()->whereKey($client->id)->first()?->pivot;
        if (! $pivot) {
            return null;
        }

        if ($pivot->owner) {
            return self::ALL;
        }

        $stored = is_string($pivot->permissions) ? json_decode($pivot->permissions, true) : $pivot->permissions;

        return is_array($stored) ? array_values(array_intersect(self::ALL, $stored)) : self::ALL;
    }

    public static function allows(User $user, Client $client, string $permission): bool
    {
        return in_array($permission, self::granted($user, $client) ?? [], true);
    }

    /**
     * Read "all" or a comma list (or an array) into a clean list.
     *
     * @return list<string>|null null when something in it is not a permission
     */
    public static function parse(string|array|null $value): ?array
    {
        if ($value === null || $value === '' || $value === 'all') {
            return self::ALL;
        }

        $list = array_values(array_unique(array_filter(array_map('trim', is_array($value) ? $value : explode(',', $value)))));

        return array_diff($list, self::ALL) === [] ? $list : null;
    }
}
