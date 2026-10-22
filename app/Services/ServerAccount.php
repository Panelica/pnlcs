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

    /** The module could not be asked just now, and the account may be there. */
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
            $answer = $screens->accountOnServer();
            if ($answer !== null) {
                return $answer ? self::LIVE : self::NONE;
            }
        }

        if (in_array(strtolower((string) $service->status), [ServiceStatus::Active->value, ServiceStatus::Suspended->value], true)) {
            return self::LIVE;
        }

        // Asked and not answered: the account the module once created may
        // still be there.
        return $screens->checksAccounts() ? self::UNKNOWN : self::NONE;
    }

    /** Staff have to decide what happens to the account before the record goes. */
    public static function needsDecision(string $state): bool
    {
        return $state === self::LIVE || $state === self::UNKNOWN;
    }
}
