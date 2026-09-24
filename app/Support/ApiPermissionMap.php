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
        // Writes that belong to something other than the settings screen: an
        // address ban is a security decision, an announcement is a public
        // statement in the operator's name, and the admin note on a customer
        // is that customer's record.
        'addbannedip' => Permissions::MANAGE_SECURITY,
        'addannouncement' => Permissions::MANAGE_ANNOUNCEMENTS,
        'updateannouncement' => Permissions::MANAGE_ANNOUNCEMENTS,
        'deleteannouncement' => Permissions::MANAGE_ANNOUNCEMENTS,
        'getannouncements' => Permissions::MANAGE_ANNOUNCEMENTS,
        'updateadminnotes' => Permissions::EDIT_CLIENTS,
        'getemailtemplates' => Permissions::MANAGE_EMAIL_TEMPLATES,
        'getactivitylog' => Permissions::VIEW_ACTIVITY_LOG,
        'logactivity' => Permissions::VIEW_ACTIVITY_LOG,
        'getadminusers' => Permissions::MANAGE_STAFF,
        'getadmindetails' => Permissions::MANAGE_STAFF,
        'getstaffonline' => Permissions::MANAGE_STAFF,
        'getservers' => Permissions::MANAGE_SERVERS,
        'getmodulequeue' => Permissions::LIST_SERVICES,
        // Quotes, projects and affiliates each have their own screen and their
        // own permissions. Only some of their calls were listed here, so the
        // rest fell back to the generic "system" pair: a member of staff with
        // "view system" could read any project, and one without "manage
        // quotes" could create and delete quotes if they could edit settings.
        'getquotes' => Permissions::LIST_QUOTES,
        'createquote' => Permissions::MANAGE_QUOTES,
        'updatequote' => Permissions::MANAGE_QUOTES,
        'deletequote' => Permissions::MANAGE_QUOTES,
        'sendquote' => Permissions::MANAGE_QUOTES,
        'acceptquote' => Permissions::MANAGE_QUOTES,
        'getprojects' => Permissions::LIST_PROJECTS,
        'getproject' => Permissions::LIST_PROJECTS,
        'createproject' => Permissions::MANAGE_PROJECTS,
        'updateproject' => Permissions::MANAGE_PROJECTS,
        'addprojecttask' => Permissions::MANAGE_PROJECTS,
        'updateprojecttask' => Permissions::MANAGE_PROJECTS,
        'deleteprojecttask' => Permissions::MANAGE_PROJECTS,
        'addprojectmessage' => Permissions::MANAGE_PROJECTS,
        'starttasktimer' => Permissions::MANAGE_PROJECTS,
        'endtasktimer' => Permissions::MANAGE_PROJECTS,
        'getaffiliates' => Permissions::MANAGE_AFFILIATES,
        'affiliateactivate' => Permissions::MANAGE_AFFILIATES,
        'getproducts' => Permissions::LIST_PRODUCTS,
        'getpromotions' => Permissions::MANAGE_PROMOTIONS,
        'getregistrars' => Permissions::MANAGE_REGISTRARS,
        // API credentials are issued from the staff screens, behind "manage
        // staff". Here they were behind "manage settings", and a new one was
        // owned by whichever administrator happened to be first in the table.
        'listoauthcredentials' => Permissions::MANAGE_STAFF,
        'createoauthcredential' => Permissions::MANAGE_STAFF,
        'updateoauthcredential' => Permissions::MANAGE_STAFF,
        'deleteoauthcredential' => Permissions::MANAGE_STAFF,
        // Checks a customer's password: a customer-record question.
        'validatelogin' => Permissions::VIEW_CLIENTS,
        // Signing in as a customer, inviting a login to their account and
        // setting what it may do: the "log in as this client" permission.
        'createssotoken' => Permissions::EDIT_CLIENTS,
        'createclientinvite' => Permissions::EDIT_CLIENTS,
        'updateuserpermissions' => Permissions::EDIT_CLIENTS,
        'getuserpermissions' => Permissions::LIST_CLIENTS,
        // Mail to customers and to staff: the mass-mail screen's permission.
        'sendemail' => Permissions::MANAGE_EMAIL_TEMPLATES,
        // A password-reset link is sent to a customer's login: a customer
        // record question, like the one-time sign-in link above. It fell to
        // "manage settings", so staff trusted with the settings could mail
        // any customer a reset link and staff who edit customers could not.
        'resetpassword' => Permissions::EDIT_CLIENTS,
        // Extension prices are set on the domain pricing screen, which asks
        // for "manage servers". Here they fell to the domain controller's
        // "manage domains": the API let through who the screen refused.
        'createorupdatetld' => Permissions::MANAGE_SERVERS,
        'sendadminemail' => Permissions::MANAGE_EMAIL_TEMPLATES,
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
