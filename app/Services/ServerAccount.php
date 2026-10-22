<?php

namespace App\Services;

use App\Enums\ServiceStatus;
use App\Models\Service;
use App\Services\Module\ModuleScreens;

/**
 * Whether a service still has an account on its server, asked before staff
 * close the record (#212).
 *
 * The status buttons on the service page only change the record, and Delete
 * only checked that record: marking a service Terminated and then deleting it
 * left the account, its domain and its app running on the server with nothing
 * to bill them, and the domain could not be ordered again. Closing a record
 * now asks first when its account may still be there.
 *
 * A module that can tell (accountOnServer(), Panelica) is asked; one that
 * cannot falls back to the rule the page always used: an active or suspended
 * service has its account.
 */
final class ServerAccount
{
    /** The account is on the server. */
    public const LIVE = 'live';

    /** The module could not tell (not reachable, or the account was never linked), and the account may be there. */
    public const UNKNOWN = 'unknown';

    /** There is nothing on a server to look after. */
    public const NONE = 'none';

    public static function state(Service $service): string
    {
        if (app(ProvisioningService::class)->resolveModule($service) === null) {
            return self::NONE;
        }

        $screens = ModuleScreens::for($service);
        if ($screens->checksAccounts()) {
            // Asked and not answered: the account may still be there, and
            // the page says it could not check rather than that it is.
            return match ($screens->accountOnServer()) {
                true => self::LIVE,
                false => self::NONE,
                null => self::UNKNOWN,
            };
        }

        return in_array(strtolower((string) $service->status), [ServiceStatus::Active->value, ServiceStatus::Suspended->value], true)
            ? self::LIVE
            : self::NONE;
    }

    /** Staff have to decide what happens to the account before the record goes. */
    public static function needsDecision(string $state): bool
    {
        return $state === self::LIVE || $state === self::UNKNOWN;
    }
}
