<?php

namespace App\Support;

use App\Constants\Permissions;

/**
 * Which permission an API call needs.
 *
 * The panel puts every screen behind a permission; the API used to be behind
 * none of them. A call is answered by the same permissions its caller would
 * need to do the same thing by hand, worked out from the controller and the
 * verb: GET reads, POST writes, and the few actions that create or delete are
 * named as such.
 *
 * An endpoint whose controller is not listed here needs a full administrator.
 * That way a controller added later is closed until somebody decides what it
 * should need, rather than open because nobody remembered.
 */
class ApiPermissionMap
{
    /** @var array<string, array<string, string>> */
    private const CONTROLLERS = [
        'ClientApiController' => [
            'read' => Permissions::LIST_CLIENTS,
            'write' => Permissions::EDIT_CLIENTS,
            'create' => Permissions::CREATE_CLIENTS,
            'delete' => Permissions::DELETE_CLIENTS,
        ],
        'InvoiceApiController' => [
            'read' => Permissions::LIST_INVOICES,
            'write' => Permissions::MANAGE_INVOICES,
            'create' => Permissions::CREATE_INVOICES,
            'delete' => Permissions::MANAGE_INVOICES,
        ],
        'OrderApiController' => [
            'read' => Permissions::LIST_ORDERS,
            'write' => Permissions::MANAGE_ORDERS,
            'create' => Permissions::MANAGE_ORDERS,
            'delete' => Permissions::MANAGE_ORDERS,
        ],
        'ServiceApiController' => [
            'read' => Permissions::LIST_SERVICES,
            'write' => Permissions::MANAGE_SERVICES,
            'create' => Permissions::MANAGE_SERVICES,
            'delete' => Permissions::MANAGE_SERVICES,
        ],
        'DomainApiController' => [
            'read' => Permissions::LIST_DOMAINS,
            'write' => Permissions::MANAGE_DOMAINS,
            'create' => Permissions::MANAGE_DOMAINS,
            'delete' => Permissions::MANAGE_DOMAINS,
        ],
        'TicketApiController' => [
            'read' => Permissions::LIST_TICKETS,
            'write' => Permissions::MANAGE_TICKETS,
            'create' => Permissions::MANAGE_TICKETS,
            'delete' => Permissions::MANAGE_TICKETS,
            'reply' => Permissions::REPLY_TICKETS,
        ],
        'SystemApiController' => [
            'read' => Permissions::VIEW_SYSTEM,
            'write' => Permissions::MANAGE_SETTINGS,
            'create' => Permissions::MANAGE_SETTINGS,
            'delete' => Permissions::MANAGE_SETTINGS,
        ],
    ];

    /**
     * Calls that answer for something other than their controller.
     *
     * SystemApiController is where the odds and ends live, and some of them
     * are customer records rather than system information: the mail history
     * and the activity log are what a customer was sent and what was done to
     * their account. Read under "view system", a member of staff trusted with
     * the version number could read every customer's mail.
     *
     * @var array<string, string>
     */
    private const ACTIONS = [
        'getemails' => Permissions::LIST_CLIENTS,
        'getactivitylog' => Permissions::VIEW_ACTIVITY_LOG,
        'logactivity' => Permissions::VIEW_ACTIVITY_LOG,
        'getadminusers' => Permissions::MANAGE_STAFF,
        'getadmindetails' => Permissions::MANAGE_STAFF,
        'getstaffonline' => Permissions::MANAGE_STAFF,
        'getservers' => Permissions::MANAGE_SERVERS,
        'getmodulequeue' => Permissions::LIST_SERVICES,
        'getquotes' => Permissions::MANAGE_QUOTES,
        'sendquote' => Permissions::MANAGE_QUOTES,
        'acceptquote' => Permissions::MANAGE_QUOTES,
        'getprojects' => Permissions::MANAGE_PROJECTS,
        'addprojecttask' => Permissions::MANAGE_PROJECTS,
        'addprojectmessage' => Permissions::MANAGE_PROJECTS,
    ];

    /**
     * The permission this call needs, or null when only a full administrator
     * will do.
     */
    public static function required(?string $controller, string $method, string $action): ?string
    {
        $named = self::ACTIONS[strtolower($action)] ?? null;

        if ($named !== null) {
            return $named;
        }

        $map = self::CONTROLLERS[class_basename((string) $controller)] ?? null;

        if ($map === null) {
            return null;
        }

        $action = strtolower($action);

        if (isset($map['reply']) && (str_contains($action, 'reply') || str_contains($action, 'openticket'))) {
            return $map['reply'];
        }

        if (strtoupper($method) === 'GET') {
            return $map['read'];
        }

        foreach (['delete', 'create', 'add'] as $verb) {
            if (str_starts_with($action, $verb)) {
                return $map[$verb === 'add' ? 'create' : $verb];
            }
        }

        return $map['write'];
    }
}
