<?php

/*
 * The API reference: one entry per endpoint, read by two readers.
 *
 * - The admin panel's API Documentation screen (ConfigController::apiDocs)
 *   shows the parameter names from here next to the live route table.
 * - `php artisan pnlcs:api-docs` writes the public reference under docs/api/
 *   from here, the route table, the permission map and the English
 *   descriptions (lang/en/admin.php, api_docs.desc_*).
 *
 * The endpoint LIST is never written by hand: it is the route table, and
 * ApiReferenceTest fails when a route has no entry here or an entry has no
 * route. What is curated is what the route table cannot say - the parameters,
 * what comes back, and the errors - and each of those was written from the
 * controller that answers the call.
 *
 * Shape of an entry:
 *   section  where the public reference files it (see SECTIONS below)
 *   list     true for WHMCS-style lists: limitstart / limitnum in, and
 *            totalresults, startnumber, numreturned, data out
 *   params   name => [type, description]; a leading * marks it required
 *   one_of   parameters of which at least one must be sent
 *   returns  field => description, beside "result": "success"
 *   errors   HTTP status => when it happens (401/403/429 apply to every call
 *            and are described once, on the overview page)
 *   notes    anything else a caller must know
 *   unavailable  set on the calls that always refuse, with the reason
 */

return [

    // ─────────────────────────────── SYSTEM ────────────────────────────────

    'getstats' => [
        'section' => 'system',
        'returns' => [
            'stats' => 'object: total_clients, active_clients, total_services, active_services, total_domains, total_invoices, unpaid_invoices, total_orders, pending_orders, total_tickets, open_tickets, total_admins',
        ],
    ],
    'gethealthstatus' => [
        'section' => 'system',
        'returns' => [
            'health' => 'object: status (ok|degraded), version (the API version, 1.0.0), laravel, php, database (ok, or the connection error), disk {total_bytes, free_bytes, used_bytes, used_percent}, memory {limit, current, peak}, timestamp',
        ],
        'notes' => 'For an uptime monitor use GET /api/health instead: it needs no credential and answers only up or down.',
    ],
    'pnlcsdetails' => [
        'section' => 'system',
        'returns' => ['pnlcs' => 'object: version (the API version, 1.0.0), company_name'],
    ],
    'whmcsdetails' => [
        'section' => 'system',
        'returns' => ['pnlcs' => 'object: version, company_name'],
        'notes' => 'The same call as pnlcsdetails, under the name WHMCS clients use.',
    ],
    'getactivitylog' => [
        'section' => 'system',
        'list' => true,
        'params' => [
            'date' => ['date', 'Only entries from this day (YYYY-MM-DD).'],
            'user' => ['string', 'Only entries written by this user name.'],
        ],
    ],
    'getautomationlog' => [
        'section' => 'system',
        'list' => true,
        'params' => [
            'date' => ['date', 'Only entries from this day (YYYY-MM-DD).'],
            'user' => ['string', 'Only entries written by this user name.'],
        ],
        'notes' => 'The same log as getactivitylog.',
    ],
    'logactivity' => [
        'section' => 'system',
        'params' => [
            '*description' => ['string', 'The entry, up to 1000 characters.'],
            'clientid' => ['integer', 'The client the entry is about.'],
        ],
        'notes' => 'The entry is signed with the user name of the staff member the credential belongs to.',
    ],
    'getadminusers' => [
        'section' => 'system',
        'returns' => ['admins' => 'array of staff accounts, each with its role'],
    ],
    'getadmindetails' => [
        'section' => 'system',
        'params' => ['adminid' => ['integer', 'The staff account. Left out: the owner of the credential.']],
        'returns' => ['admin' => 'object: the staff account with its role'],
        'errors' => [404 => 'No staff account with that id.'],
    ],
    'getstaffonline' => [
        'section' => 'system',
        'returns' => ['staff' => 'array of staff accounts that signed in during the last 15 minutes'],
    ],
    'getconfigurationvalue' => [
        'section' => 'system',
        'params' => ['*setting' => ['string', 'The setting name, as stored (for example CompanyName).']],
        'returns' => ['setting' => 'string', 'value' => 'string or null'],
        'errors' => [403 => 'The setting holds a credential: any name containing password, secret, token, key, access hash, credential or sid.'],
    ],
    'setconfigurationvalue' => [
        'section' => 'system',
        'params' => [
            '*setting' => ['string', 'The setting name.'],
            '*value' => ['string', 'The new value.'],
        ],
        'errors' => [403 => 'The setting holds a credential (see getconfigurationvalue).'],
        'notes' => 'The setting stays in the group its settings screen reads it from; a new name is created in the general group.',
    ],
    'getannouncements' => [
        'section' => 'system',
        'list' => true,
        'notes' => 'Published announcements only, newest first.',
    ],
    'addannouncement' => [
        'section' => 'system',
        'params' => [
            '*title' => ['string', 'Up to 255 characters.'],
            '*announcement' => ['string', 'The text.'],
            'published' => ['boolean', 'Default true. Send 0 to save it unpublished.'],
        ],
        'returns' => ['announcementid' => 'integer'],
    ],
    'updateannouncement' => [
        'section' => 'system',
        'params' => [
            '*announcementid' => ['integer', 'The announcement.'],
            'title' => ['string', 'Up to 255 characters.'],
            'announcement' => ['string', 'The text.'],
            'published' => ['boolean', 'Publish or unpublish.'],
        ],
        'errors' => [404 => 'No announcement with that id.'],
    ],
    'deleteannouncement' => [
        'section' => 'system',
        'params' => ['*announcementid' => ['integer', 'The announcement.']],
        'errors' => [404 => 'No announcement with that id.'],
    ],
    'getemailtemplates' => [
        'section' => 'system',
        'returns' => ['templates' => 'array of every email template'],
    ],
    'getemails' => [
        'section' => 'system',
        'list' => true,
        'params' => ['userid' => ['integer', 'Only mail sent to this client.']],
        'notes' => 'The log of mail sent to clients, newest first.',
    ],
    'getservers' => [
        'section' => 'system',
        'returns' => ['servers' => 'array of servers, each with its server groups'],
    ],
    'getregistrars' => [
        'section' => 'system',
        'returns' => ['registrars' => 'array of {module, displayname, active}: every installed registrar module and whether the domain search offers it'],
    ],
    'getproducts' => [
        'section' => 'system',
        'params' => [
            'pid' => ['integer', 'One product.'],
            'gid' => ['integer', 'Only products in this product group.'],
            'module' => ['string', 'Only products provisioned by this server module (for example panelica).'],
        ],
        'returns' => ['products' => 'array of products, each with its group and pricing'],
    ],
    'getpromotions' => [
        'section' => 'system',
        'returns' => ['promotions' => 'array of every promotion code'],
    ],
    'gettodoitems' => [
        'section' => 'system',
        'params' => ['status' => ['string', 'Only items with this status.']],
        'returns' => ['items' => 'array of to-do items, newest first'],
    ],
    'gettodoitemstatuses' => [
        'section' => 'system',
        'returns' => ['statuses' => 'array: New, In Progress, Completed, Deferred'],
    ],
    'updatetodoitem' => [
        'section' => 'system',
        'params' => [
            '*itemid' => ['integer', 'The to-do item.'],
            'title' => ['string', 'Up to 255 characters.'],
            'description' => ['string', 'The details.'],
            'status' => ['string', 'New, In Progress, Completed or Deferred (any letter case).'],
            'due_date' => ['date', 'YYYY-MM-DD, or empty to clear it.'],
            'admin' => ['string', 'The staff member it is assigned to.'],
        ],
        'errors' => [404 => 'No item with that id.'],
    ],
    'getpaymentmethods' => [
        'section' => 'system',
        'returns' => [
            'totalresults' => 'integer',
            'paymentmethods' => 'array of {module, displayname}: the gateways that are switched on and hold the keys they need',
        ],
    ],
    'getorderstatuses' => [
        'section' => 'system',
        'returns' => ['statuses' => 'array of the order statuses configured here'],
    ],
    'addbannedip' => [
        'section' => 'system',
        'params' => [
            '*ip' => ['string', 'An IPv4 or IPv6 address, or a prefix ending in * such as 203.0.113.*'],
            'reason' => ['string', 'Up to 255 characters.'],
        ],
    ],
    'validatelogin' => [
        'section' => 'system',
        'params' => [
            '*email' => ['email', 'The client login.'],
            '*password2' => ['string', 'The password to check.'],
        ],
        'returns' => ['userid' => 'integer: the login that matched'],
        'errors' => [401 => 'The address and password do not match a client login.'],
    ],
    'sendemail' => [
        'section' => 'system',
        'one_of' => ['messagename', 'customsubject'],
        'params' => [
            'messagename' => ['string', 'A template, one of: invoice created, invoice payment confirmation, invoice reminder, invoice overdue, service welcome email, service suspension, service unsuspension, service termination, order confirmation, domain registration confirmation, domain renewal reminder, account signup email. Required unless customsubject is sent.'],
            '*id' => ['integer', 'The record the mail is about: an invoice, service, order, domain or client id, as the template or customtype needs.'],
            'customsubject' => ['string', 'Write the mail yourself instead of using a template. Required unless messagename is sent.'],
            'custommessage' => ['string', 'The body, when customsubject is sent.'],
            'customtype' => ['string', 'What id points at for a custom mail: general (a client, the default), invoice, product (a service) or domain.'],
        ],
        'returns' => ['recipient' => 'string: the address the mail was queued for'],
        'errors' => [
            404 => 'No record with that id.',
            422 => 'The template cannot be sent on demand, or the client has no email address.',
        ],
        'notes' => 'A template mail is built exactly as the event that normally sends it builds it, in the client language. Mail switched off in the settings stays off.',
        'example' => ['messagename' => 'invoice reminder', 'id' => 42],
    ],
    'sendadminemail' => [
        'section' => 'system',
        'params' => [
            '*customsubject' => ['string', 'Up to 255 characters.'],
            '*custommessage' => ['string', 'The body.'],
            'deptid' => ['integer', 'Only the staff who handle this support department.'],
        ],
        'returns' => ['recipients' => 'integer: how many staff members it was queued for'],
        'notes' => 'Goes to every active staff member with an email address, or to those assigned to deptid.',
    ],
    'resetpassword' => [
        'section' => 'system',
        'one_of' => ['email', 'id'],
        'params' => [
            'email' => ['email', 'The client login. Required unless id is sent.'],
            'id' => ['integer', 'The login (user) id. Required unless email is sent.'],
        ],
        'returns' => ['email' => 'string: the address the reset link was sent to'],
        'errors' => [404 => 'No client login has that address.'],
        'notes' => 'Sends the same link as the Forgot password form. The password itself is never set or returned.',
    ],
    'activatemodule' => [
        'section' => 'system',
        'params' => [
            '*moduleType' => ['string', 'server, gateway, registrar, ssl or addon.'],
            '*moduleName' => ['string', 'The module name, for example stripe.'],
        ],
        'returns' => ['moduleType' => 'string', 'moduleName' => 'string', 'active' => 'boolean'],
        'errors' => [404 => 'No such module is installed.', 422 => 'The switch refused the change.'],
        'notes' => 'The same switch as Setup > Modules in the admin area.',
    ],
    'deactivatemodule' => [
        'section' => 'system',
        'params' => [
            '*moduleType' => ['string', 'server, gateway, registrar, ssl or addon.'],
            '*moduleName' => ['string', 'The module name.'],
        ],
        'returns' => ['moduleType' => 'string', 'moduleName' => 'string', 'active' => 'boolean'],
        'errors' => [404 => 'No such module is installed.', 422 => 'A server or SSL module that is in use stays on.'],
    ],
    'getmoduleconfigurationparameters' => [
        'section' => 'system',
        'params' => [
            '*moduleType' => ['string', 'server, gateway, registrar, ssl or addon.'],
            '*moduleName' => ['string', 'The module name.'],
        ],
        'returns' => [
            'moduleType' => 'string',
            'moduleName' => 'string',
            'parameters' => 'array of {name, label, type, required, options}: the settings the module asks for. Stored values are never returned.',
        ],
        'errors' => [404 => 'No such module is installed.'],
    ],
    'updatemoduleconfiguration' => [
        'section' => 'system',
        'params' => [
            '*moduleType' => ['string', 'gateway, registrar, ssl or addon. Server modules keep their settings on each server.'],
            '*moduleName' => ['string', 'The module name.'],
            '*parameters' => ['object', 'parameters[name]=value for each setting to change, up to 50, each a string of at most 8192 characters.'],
        ],
        'returns' => ['moduleType' => 'string', 'moduleName' => 'string', 'updated' => 'array of the setting names written'],
        'errors' => [
            403 => 'The staff account lacks the permission the module settings screen asks for: manage_gateways, manage_registrars, manage_servers (SSL) or manage_products (addons).',
            404 => 'No such module is installed.',
            422 => 'moduleType is server.',
        ],
        'notes' => 'A blank value for a password field keeps the stored one, as on the settings screen.',
        'example' => ['moduleType' => 'gateway', 'moduleName' => 'stripe', 'parameters[testMode]' => '0'],
    ],
    'getmodulequeue' => [
        'section' => 'system',
        'list' => true,
        'params' => ['status' => ['string', 'pending, completed, failed or cancelled. Default: pending and failed.']],
        'notes' => 'Server actions waiting to be retried. Each row carries id, service_id, action, status, attempts, max_attempts, next_attempt_at, last_error, completed_at, created_at; the payload is never returned.',
    ],
    'getpermissionslist' => [
        'section' => 'system',
        'returns' => ['permissions' => 'array of every permission key a staff role can be given'],
    ],
    'triggernotificationevent' => [
        'section' => 'system',
        'params' => [
            '*title' => ['string', 'Up to 255 characters.'],
            '*message' => ['string', 'Up to 4000 characters.'],
            'url' => ['url', 'A link to add to the notification.'],
            'notification_identifier' => ['string', 'Your own label for the notification.'],
        ],
        'returns' => ['event' => 'string: api.custom', 'rules' => 'integer: how many active notification rules the event reached'],
        'notes' => 'Delivered through the channels set up for the api.custom event under Setup > Notification Channels (email, Slack, webhook, Telegram). With no rule for it, nothing is sent and rules is 0.',
    ],
    'encryptpassword' => [
        'section' => 'system',
        'unavailable' => 'Always answers 501. Encrypting with the application key through the API was withdrawn: the same key protects the database.',
    ],
    'decryptpassword' => [
        'section' => 'system',
        'unavailable' => 'Always answers 501, for the same reason as encryptpassword.',
    ],
    'updateadminnotes' => [
        'section' => 'system',
        'params' => [
            '*clientid' => ['integer', 'The client.'],
            'notes' => ['string', 'The admin notes. Replaces what was there.'],
        ],
        'returns' => ['clientid' => 'integer'],
        'errors' => [404 => 'No client with that id.'],
    ],

    // ─────────────────────────────── CLIENTS ───────────────────────────────

    'getclients' => [
        'section' => 'clients',
        'list' => true,
        'params' => [
            'search' => ['string', 'Matches first name, last name, email or company name.'],
            'status' => ['string', 'active, inactive or closed.'],
            'group_id' => ['integer', 'Only clients in this client group.'],
            'orderby' => ['string', 'id (default), first_name, last_name, email, company_name, status or created_at.'],
            'sorting' => ['string', 'ASC (default) or DESC.'],
        ],
    ],
    'getclientsdetails' => [
        'section' => 'clients',
        'one_of' => ['clientid', 'email'],
        'params' => [
            'clientid' => ['integer', 'The client. Required unless email is sent.'],
            'email' => ['email', 'Find the client by address instead.'],
        ],
        'returns' => ['client' => 'object: the client with its contacts'],
        'errors' => [400 => 'Neither clientid nor email was sent.', 404 => 'No such client.'],
    ],
    'addclient' => [
        'section' => 'clients',
        'params' => [
            '*firstname' => ['string', 'Up to 255 characters.'],
            '*lastname' => ['string', 'Up to 255 characters.'],
            '*email' => ['email', 'Must not belong to another client.'],
            'password2' => ['string', 'At least 8 characters. When sent, a client-area login is created with it. password is accepted too.'],
            'companyname' => ['string', 'Company name.'],
            'address1' => ['string', 'Street address.'],
            'city' => ['string', 'City.'],
            'state' => ['string', 'State or region.'],
            'postcode' => ['string', 'Postal code.'],
            'country' => ['string', 'Two-letter country code. Default US.'],
            'phonenumber' => ['string', 'Phone number.'],
        ],
        'returns' => ['clientid' => 'integer'],
        'errors' => [422 => 'A required field is missing, or the address is already a client or a login.'],
        'example' => ['firstname' => 'Ada', 'lastname' => 'Lovelace', 'email' => 'ada@example.com', 'country' => 'GB'],
    ],
    'updateclient' => [
        'section' => 'clients',
        'params' => [
            '*clientid' => ['integer', 'The client.'],
            'firstname' => ['string', 'Cannot be empty.'],
            'lastname' => ['string', 'Cannot be empty.'],
            'email' => ['email', 'Must not belong to another client.'],
            'companyname' => ['string', 'Company name.'],
            'address1' => ['string', 'Street address.'],
            'city' => ['string', 'City.'],
            'state' => ['string', 'State or region.'],
            'postcode' => ['string', 'Postal code.'],
            'country' => ['string', 'Two-letter country code.'],
            'phonenumber' => ['string', 'Phone number.'],
            'status' => ['string', 'active, inactive or closed.'],
        ],
        'returns' => ['clientid' => 'integer'],
        'errors' => [404 => 'No such client.'],
        'notes' => 'Only the fields you send are changed.',
    ],
    'deleteclient' => [
        'section' => 'clients',
        'params' => ['*clientid' => ['integer', 'The client.']],
        'returns' => ['clientid' => 'integer'],
        'errors' => [404 => 'No such client.', 422 => 'The client still has services that are not terminated, or registered domains.'],
        'notes' => 'Terminate the services first, as in the admin area, so the accounts are really closed on the servers.',
    ],
    'closeclient' => [
        'section' => 'clients',
        'params' => ['*clientid' => ['integer', 'The client.']],
        'returns' => ['clientid' => 'integer'],
        'errors' => [404 => 'No such client.'],
        'notes' => 'Sets the status to closed. Nothing is deleted.',
    ],
    'addclientnote' => [
        'section' => 'clients',
        'params' => [
            '*userid' => ['integer', 'The client. clientid is accepted too.'],
            '*note' => ['string', 'The note. notes and message are accepted too.'],
            'sticky' => ['boolean', 'Pin it to the top of the client summary.'],
        ],
        'errors' => [404 => 'No such client.', 422 => 'The note is empty.'],
        'notes' => 'Signed with the user name of the staff member the credential belongs to.',
    ],
    'getcontacts' => [
        'section' => 'clients',
        'list' => true,
        'params' => ['userid' => ['integer', 'Only this client\'s contacts.']],
    ],
    'addcontact' => [
        'section' => 'clients',
        'params' => [
            '*clientid' => ['integer', 'The client.'],
            '*firstname' => ['string', 'First name.'],
            '*lastname' => ['string', 'Last name.'],
            '*email' => ['email', 'Address.'],
            'phonenumber' => ['string', 'Phone number.'],
        ],
        'returns' => ['contactid' => 'integer'],
    ],
    'updatecontact' => [
        'section' => 'clients',
        'params' => [
            '*contactid' => ['integer', 'The contact.'],
            'firstname' => ['string', 'Cannot be empty.'],
            'lastname' => ['string', 'Cannot be empty.'],
            'email' => ['email', 'Address.'],
        ],
        'returns' => ['contactid' => 'integer'],
        'errors' => [404 => 'No such contact.'],
    ],
    'deletecontact' => [
        'section' => 'clients',
        'params' => ['*contactid' => ['integer', 'The contact.']],
        'errors' => [404 => 'No such contact.'],
    ],
    'getclientgroups' => [
        'section' => 'clients',
        'returns' => ['groups' => 'array of every client group'],
    ],
    'getcredits' => [
        'section' => 'clients',
        'params' => ['clientid' => ['integer', 'Only this client\'s credit history.']],
        'returns' => ['credits' => 'array of credit entries, newest first'],
    ],
    'addcredit' => [
        'section' => 'clients',
        'params' => [
            '*clientid' => ['integer', 'The client.'],
            '*description' => ['string', 'What the credit is for.'],
            '*amount' => ['number', 'At least 0.01.'],
        ],
        'notes' => 'Adds to the client credit balance and records the entry in one step.',
    ],
    'applycredit' => [
        'section' => 'clients',
        'params' => [
            '*invoiceid' => ['integer', 'The invoice to pay from credit.'],
            '*amount' => ['number', 'At least 0.01, and no more than the client credit balance.'],
            'clientid' => ['integer', 'When sent, must be the owner of the invoice.'],
        ],
        'returns' => ['invoiceid' => 'integer', 'amount' => 'number', 'remaining_credit' => 'number'],
        'errors' => [400 => 'clientid is not the owner of the invoice, or the amount is more than the balance.', 404 => 'No such invoice.'],
        'notes' => 'Spends existing credit on an invoice. To give a client credit, use addcredit.',
    ],
    'getusers' => [
        'section' => 'clients',
        'params' => [
            'clientid' => ['integer', 'Only the logins on this client account.'],
            'limitstart' => ['integer', 'Where the page starts. Default 0.'],
            'limitnum' => ['integer', 'Page size, 1 to 250. Default 25.'],
        ],
        'returns' => ['users' => 'array of client-area logins (one page)'],
        'notes' => 'A login (user) can open several client accounts; a client account can have several logins.',
    ],
    'adduser' => [
        'section' => 'clients',
        'params' => [
            '*email' => ['email', 'Must not belong to another login.'],
            '*password' => ['string', 'At least 8 characters.'],
            '*first_name' => ['string', 'First name.'],
            '*last_name' => ['string', 'Last name.'],
            'clientid' => ['integer', 'Attach the new login to this client account.'],
        ],
        'returns' => ['userid' => 'integer'],
    ],
    'updateuser' => [
        'section' => 'clients',
        'params' => [
            '*userid' => ['integer', 'The login.'],
            'email' => ['email', 'Must not belong to another login.'],
            'first_name' => ['string', 'First name.'],
            'last_name' => ['string', 'Last name.'],
            'password' => ['string', 'At least 8 characters.'],
        ],
        'returns' => ['userid' => 'integer'],
        'errors' => [404 => 'No such login.'],
    ],
    'deleteuserclient' => [
        'section' => 'clients',
        'params' => [
            '*userid' => ['integer', 'The login.'],
            '*clientid' => ['integer', 'The client account to take it off.'],
        ],
        'returns' => ['userid' => 'integer', 'clientid' => 'integer'],
        'errors' => [404 => 'No such login, or it is not on that account.'],
        'notes' => 'Only the link goes: the login keeps any other accounts it belongs to.',
    ],
    'createclientinvite' => [
        'section' => 'clients',
        'params' => [
            '*clientid' => ['integer', 'The client account. client_id is accepted too.'],
            '*email' => ['email', 'Who to invite.'],
            'permissions' => ['string', 'all (the default), or a comma-separated list of: profile, contacts, products, manageproducts, productsso, domains, managedomains, invoices, quotes, tickets, affiliates, emails, orders.'],
        ],
        'returns' => ['inviteid' => 'integer', 'email' => 'string', 'permissions' => 'array or "all"'],
        'errors' => [409 => 'That address already has a login on the account.', 422 => 'An unknown permission was named.'],
        'notes' => 'The person gets an email with a link that is valid for 7 days. They create a login or sign in with theirs, and join the account with these permissions.',
        'example' => ['clientid' => 7, 'email' => 'accounts@example.com', 'permissions' => 'invoices,tickets'],
    ],
    'getuserpermissions' => [
        'section' => 'clients',
        'params' => [
            '*userid' => ['integer', 'The login. user_id is accepted too.'],
            '*clientid' => ['integer', 'The client account. client_id is accepted too.'],
        ],
        'returns' => ['userid' => 'integer', 'clientid' => 'integer', 'owner' => 'boolean', 'permissions' => 'array or "all"'],
        'errors' => [404 => 'The login is not on that account.'],
    ],
    'updateuserpermissions' => [
        'section' => 'clients',
        'params' => [
            '*userid' => ['integer', 'The login. user_id is accepted too.'],
            '*clientid' => ['integer', 'The client account. client_id is accepted too.'],
            '*permissions' => ['string', 'all, or a comma-separated list (see createclientinvite).'],
        ],
        'returns' => ['userid' => 'integer', 'clientid' => 'integer', 'permissions' => 'array or "all"'],
        'errors' => [404 => 'The login is not on that account.', 422 => 'The login is the account owner (who always has everything), or an unknown permission was named.'],
        'notes' => 'Enforced in the client area: a login without a permission cannot open that part of the account.',
    ],
    'getclientpassword' => [
        'section' => 'clients',
        'unavailable' => 'Always answers 403: passwords are stored hashed and cannot be read back. Use resetpassword to send a reset link.',
    ],
    'createssotoken' => [
        'section' => 'clients',
        'params' => [
            '*client_id' => ['integer', 'The client account. clientid is accepted too.'],
            'user_id' => ['integer', 'Which login to sign in as. Default: the account owner. userid is accepted too.'],
            'destination' => ['string', 'clientarea:homepage (default), clientarea:invoices, clientarea:services, clientarea:domains, clientarea:tickets, clientarea:product_details (with service_id), clientarea:domain_details (with domain_id) or sso:custom_redirect (with sso_redirect_path).'],
            'service_id' => ['integer', 'For clientarea:product_details; must be on the account.'],
            'domain_id' => ['integer', 'For clientarea:domain_details; must be on the account.'],
            'sso_redirect_path' => ['string', 'For sso:custom_redirect: a path inside the client area, such as /client/invoices.'],
        ],
        'returns' => ['access_token' => 'string', 'redirect_url' => 'string: open this to be signed in', 'expires_in' => 'integer: 60 (seconds)'],
        'errors' => [404 => 'The account has no login to sign in as.', 422 => 'Unknown destination, or it is not on this account.'],
        'notes' => 'The link works once and for 60 seconds. Only its hash is stored. Needs the same permission as signing in as a client from the admin area (edit_clients).',
        'example' => ['client_id' => 7, 'destination' => 'clientarea:invoices'],
    ],

    // ─────────────────────────── INVOICES & BILLING ────────────────────────

    'getinvoices' => [
        'section' => 'invoices',
        'list' => true,
        'params' => [
            'userid' => ['integer', 'Only this client\'s invoices.'],
            'status' => ['string', 'draft, unpaid, paid, partially_paid, overdue, cancelled, refunded, collections or payment_pending.'],
        ],
        'notes' => 'Newest first. Each invoice carries its client.',
    ],
    'getinvoice' => [
        'section' => 'invoices',
        'params' => ['*invoiceid' => ['integer', 'The invoice.']],
        'returns' => ['invoice' => 'object: the invoice with its client and line items'],
        'errors' => [404 => 'No such invoice.'],
    ],
    'createinvoice' => [
        'section' => 'invoices',
        'params' => [
            '*userid' => ['integer', 'The client.'],
            'items' => ['array', 'The lines: items[0][description], items[0][amount], items[0][taxed] (default true). Or send itemdescription1, itemamount1, itemtaxed1, itemdescription2, ... (up to 50). At least one line is required.'],
            'date' => ['date', 'Invoice date. Default today.'],
            'duedate' => ['date', 'Due date.'],
            'paymentmethod' => ['string', 'A gateway module name.'],
            'status' => ['string', 'draft, unpaid or paid.'],
            'notes' => ['string', 'Notes printed on the invoice.'],
        ],
        'returns' => ['invoiceid' => 'integer', 'total' => 'number'],
        'errors' => [422 => 'No line was sent, or a line has no amount.'],
        'notes' => 'Totals, tax and the client group discount are worked out as for any other invoice.',
        'example' => ['userid' => 7, 'items[0][description]' => 'Hosting - October', 'items[0][amount]' => '9.99', 'duedate' => '2026-10-15'],
    ],
    'updateinvoice' => [
        'section' => 'invoices',
        'params' => [
            '*invoiceid' => ['integer', 'The invoice.'],
            'status' => ['string', 'draft, unpaid, paid, partially_paid, overdue, cancelled, refunded, collections or payment_pending.'],
            'date' => ['date', 'Invoice date.'],
            'duedate' => ['date', 'Due date.'],
            'paymentmethod' => ['string', 'A gateway installed here.'],
            'notes' => ['string', 'Notes.'],
            'transid' => ['string', 'With status=paid: the payment reference.'],
            'gateway' => ['string', 'With status=paid: the gateway it was paid through. Default manual.'],
        ],
        'returns' => ['invoiceid' => 'integer'],
        'errors' => [404 => 'No such invoice.'],
        'notes' => 'status=paid records the payment the way the admin area does (transaction, paid event, provisioning). status=cancelled hands back any credit applied. Other statuses only change the label.',
    ],
    'addinvoicepayment' => [
        'section' => 'invoices',
        'params' => [
            '*invoiceid' => ['integer', 'The invoice.'],
            '*transid' => ['string', 'The payment reference. The same reference is never recorded twice.'],
            '*amount' => ['number', 'At least 0.01.'],
            'gateway' => ['string', 'The gateway it came through. Default banktransfer.'],
        ],
        'returns' => [
            'transactionid' => 'integer',
            'status' => 'string: the invoice status afterwards',
            'balance' => 'number: what is still owed',
            'duplicate' => 'boolean: true when transid had already been recorded',
        ],
        'errors' => [404 => 'No such invoice.', 422 => 'The payment could not be recorded.'],
        'notes' => 'A part payment leaves the invoice partly paid; an overpayment becomes credit. When the invoice is paid, whatever waits on it (provisioning, unsuspension, an upgrade) runs.',
    ],
    'addtransaction' => [
        'section' => 'invoices',
        'params' => [
            '*userid' => ['integer', 'The client.'],
            '*description' => ['string', 'What the transaction is.'],
            'amountin' => ['number', 'Money received.'],
            'amountout' => ['number', 'Money paid out.'],
            'invoiceid' => ['integer', 'An invoice of this client.'],
            'transid' => ['string', 'The reference.'],
            'gateway' => ['string', 'The gateway.'],
        ],
        'returns' => ['transactionid' => 'integer'],
        'notes' => 'A ledger line only. To pay an invoice, use addinvoicepayment.',
    ],
    'gettransactions' => [
        'section' => 'invoices',
        'list' => true,
        'params' => [
            'clientid' => ['integer', 'Only this client\'s transactions. userid is accepted too.'],
            'invoiceid' => ['integer', 'Only this invoice\'s transactions.'],
        ],
    ],
    'updatetransaction' => [
        'section' => 'invoices',
        'params' => [
            '*transactionid' => ['integer', 'The transaction.'],
            'description' => ['string', 'What the transaction is.'],
            'amount' => ['number', 'The amount received.'],
        ],
        'returns' => ['transactionid' => 'integer'],
        'errors' => [404 => 'No such transaction.'],
    ],
    'getcurrencies' => [
        'section' => 'invoices',
        'returns' => ['currencies' => 'array of every currency'],
    ],
    'geninvoices' => [
        'section' => 'invoices',
        'returns' => ['generated' => 'integer', 'skipped' => 'integer', 'errors' => 'integer', 'invoice_ids' => 'array of integers'],
        'errors' => [422 => 'A filter (clientid, serviceids, domainids, addonids) was sent.'],
        'notes' => 'Runs the invoice generation for every service that is due, like the daily cron. It cannot be limited to one client; to bill one client, use createinvoice.',
    ],
    'capturepayment' => [
        'section' => 'invoices',
        'unavailable' => 'Always answers 501: charging a stored card from the API is not implemented. The payment is taken in the client area or by the automatic payment run.',
    ],
    'addbillableitem' => [
        'section' => 'invoices',
        'params' => [
            '*clientid' => ['integer', 'The client.'],
            '*description' => ['string', 'Up to 255 characters.'],
            '*amount' => ['number', 'The amount.'],
            'duedate' => ['date', 'When to bill it.'],
        ],
        'returns' => ['billableitemid' => 'integer'],
    ],
    'getpaymethods' => [
        'section' => 'invoices',
        'params' => ['*clientid' => ['integer', 'The client.']],
        'returns' => [
            'clientid' => 'integer',
            'paymethods' => 'array of {id, type, description, gateway_name, card_type, card_last_four, expiry_date, is_default, status}. Tokens and gateway customer ids are never returned.',
        ],
        'errors' => [404 => 'No such client.'],
    ],
    'addpaymethod' => [
        'section' => 'invoices',
        'unavailable' => 'Always answers 501: a card is stored through the gateway\'s own form, with the customer there for 3-D Secure. Card numbers never pass through PNLCS. The customer adds it in the client area.',
    ],
    'updatepaymethod' => [
        'section' => 'invoices',
        'params' => [
            '*clientid' => ['integer', 'The client.'],
            '*paymethodid' => ['integer', 'One of that client\'s stored methods.'],
            'set_as_default' => ['boolean', 'Make it the default.'],
            'description' => ['string', 'Rename it.'],
        ],
        'returns' => ['paymethodid' => 'integer', 'is_default' => 'boolean'],
        'errors' => [404 => 'The client has no such payment method.'],
    ],
    'deletepaymethod' => [
        'section' => 'invoices',
        'params' => [
            '*clientid' => ['integer', 'The client.'],
            '*paymethodid' => ['integer', 'One of that client\'s stored methods.'],
        ],
        'returns' => ['paymethodid' => 'integer'],
        'errors' => [404 => 'The client has no such payment method.'],
        'notes' => 'PNLCS stops using it at once; the gateway is asked to forget it on the next run, as when the customer removes it.',
    ],

    // ──────────────────────────────── ORDERS ───────────────────────────────

    'getorders' => [
        'section' => 'orders',
        'list' => true,
        'params' => [
            'id' => ['integer', 'One order.'],
            'userid' => ['integer', 'Only this client\'s orders.'],
            'status' => ['string', 'pending, active, cancelled or fraud.'],
        ],
    ],
    'addorder' => [
        'section' => 'orders',
        'params' => [
            '*clientid' => ['integer', 'The client.'],
            '*pid' => ['array', 'Product ids: pid[0], pid[1], ... At least one.'],
            'billingcycle' => ['array', 'One per product: monthly (default), quarterly, semiannually, annually, biennially, triennially or onetime. A cycle the product is not sold on is skipped.'],
            'domain' => ['array', 'One per product: the domain the service is for.'],
            'priceoverride' => ['array', 'One per product: a price instead of the product price (0 or more).'],
            'paymentmethod' => ['string', 'A gateway installed here. Default banktransfer.'],
            'promocode' => ['string', 'A promotion code.'],
        ],
        'returns' => ['orderid' => 'integer', 'ordernum' => 'string', 'invoiceid' => 'integer'],
        'errors' => [422 => 'No product could be ordered, or a field is not valid.'],
        'notes' => 'Built the way the shop builds an order: the services, the invoice and the order confirmation.',
        'example' => ['clientid' => 7, 'pid[0]' => 3, 'billingcycle[0]' => 'annually', 'domain[0]' => 'example.com'],
    ],
    'acceptorder' => [
        'section' => 'orders',
        'params' => ['*orderid' => ['integer', 'The order.']],
        'returns' => ['orderid' => 'integer', 'status' => 'string'],
        'errors' => [404 => 'No such order.'],
        'notes' => 'What the Accept button does: every service on the order is created on its server (products set to manual setup included), ordered domains are registered, and the order becomes Active. A service whose server refuses stays pending and is queued for retry.',
    ],
    'cancelorder' => [
        'section' => 'orders',
        'params' => ['*orderid' => ['integer', 'The order.']],
        'returns' => ['orderid' => 'integer', 'status' => 'string'],
        'errors' => [404 => 'No such order.'],
        'notes' => 'What the Cancel button does: live accounts are terminated on their servers, the other services and the domains are cancelled, and the invoice is cancelled unless it was paid in full (what was paid becomes credit).',
    ],
    'pendingorder' => [
        'section' => 'orders',
        'params' => ['*orderid' => ['integer', 'The order.']],
        'returns' => ['orderid' => 'integer'],
        'errors' => [404 => 'No such order.'],
        'notes' => 'Puts the order back to Pending.',
    ],
    'fraudorder' => [
        'section' => 'orders',
        'params' => ['*orderid' => ['integer', 'The order.']],
        'returns' => ['orderid' => 'integer', 'status' => 'string'],
        'errors' => [404 => 'No such order.'],
        'notes' => 'What the Fraud button does: active services are suspended (on their servers), and the invoice is cancelled unless it was paid in full.',
    ],
    'deleteorder' => [
        'section' => 'orders',
        'params' => ['*orderid' => ['integer', 'The order.']],
        'errors' => [404 => 'No such order.'],
    ],
    'orderfraudcheck' => [
        'section' => 'orders',
        'params' => ['*orderid' => ['integer', 'The order.']],
        'returns' => [
            'orderid' => 'integer',
            'fraud' => 'boolean: score of 60 or more',
            'fraud_score' => 'integer',
            'risk_level' => 'string',
            'reasons' => 'array of strings',
        ],
        'errors' => [404 => 'No such order.'],
    ],

    // ─────────────────────────────── SERVICES ──────────────────────────────

    'getclientsproducts' => [
        'section' => 'services',
        'list' => true,
        'params' => [
            'clientid' => ['integer', 'Only this client\'s services. userid is accepted too.'],
            'serviceid' => ['integer', 'One service.'],
            'pid' => ['integer', 'Only services of this product.'],
            'domain' => ['string', 'Only the service for this exact domain.'],
            'status' => ['string', 'pending, active, suspended, terminated, cancelled, fraud or completed.'],
        ],
        'notes' => 'Each service carries its client and product.',
    ],
    'updateclientproduct' => [
        'section' => 'services',
        'params' => [
            '*serviceid' => ['integer', 'The service.'],
            'status' => ['string', 'pending, active, suspended, terminated, cancelled, fraud or completed.'],
            'domain' => ['string', 'A domain name.'],
            'username' => ['string', 'The account user name on the server.'],
            'password' => ['string', 'The account password as stored in PNLCS. This does not change it on the server; use modulechangepw for that.'],
            'next_due_date' => ['date', 'Next due date.'],
            'billing_cycle' => ['string', 'onetime, monthly, quarterly, semiannually, annually, biennially or triennially.'],
            'notes' => ['string', 'Admin notes.'],
        ],
        'returns' => ['serviceid' => 'integer'],
        'errors' => [404 => 'No such service.'],
        'notes' => 'Changes the record only; nothing is sent to the server.',
    ],
    'getclientsaddons' => [
        'section' => 'services',
        'params' => [
            'clientid' => ['integer', 'Only this client\'s addons. userid is accepted too.'],
            'serviceid' => ['integer', 'Only this service\'s addons.'],
        ],
        'returns' => ['addons' => 'array of service addons, each with its service and addon'],
    ],
    'updateclientaddon' => [
        'section' => 'services',
        'params' => [
            '*id' => ['integer', 'The service addon.'],
            'status' => ['string', 'pending, active, suspended, terminated, cancelled, fraud or completed. cancelled stops its billing too.'],
            'notes' => ['string', 'Notes.'],
        ],
        'returns' => ['addonid' => 'integer'],
        'errors' => [404 => 'No such addon.'],
    ],
    'modulecreate' => [
        'section' => 'services',
        'params' => ['*serviceid' => ['integer', 'The service.']],
        'returns' => ['(module fields)' => 'whatever the server module reports, such as message'],
        'errors' => [400 => 'The server module refused; message says why.', 404 => 'No such service.'],
        'notes' => 'Creates the account on the server through the product\'s server module.',
    ],
    'modulesuspend' => [
        'section' => 'services',
        'params' => [
            '*serviceid' => ['integer', 'The service.'],
            'reason' => ['string', 'Stored as the suspension reason and passed to the server module.'],
        ],
        'errors' => [400 => 'The server module refused.', 404 => 'No such service.'],
    ],
    'moduleunsuspend' => [
        'section' => 'services',
        'params' => ['*serviceid' => ['integer', 'The service.']],
        'errors' => [400 => 'The server module refused.', 404 => 'No such service.'],
    ],
    'moduleterminate' => [
        'section' => 'services',
        'params' => ['*serviceid' => ['integer', 'The service.']],
        'errors' => [400 => 'The server module refused.', 404 => 'No such service.'],
        'notes' => 'Deletes the account on the server. It cannot be undone.',
    ],
    'modulechangepw' => [
        'section' => 'services',
        'params' => [
            '*serviceid' => ['integer', 'The service.'],
            '*servicepassword' => ['string', 'The new password, at least 6 characters. password is accepted too.'],
        ],
        'errors' => [400 => 'No password was sent, or the server module refused.', 404 => 'No such service.'],
    ],
    'modulechangepackage' => [
        'section' => 'services',
        'params' => [
            '*serviceid' => ['integer', 'The service.'],
            '*packageid' => ['integer', 'The product to move the account to.'],
        ],
        'errors' => [400 => 'The server module refused.', 404 => 'No such service or product.'],
        'notes' => 'Changes the plan on the server only; billing is not touched. To bill the difference, use upgradeproduct.',
    ],
    'modulecustom' => [
        'section' => 'services',
        'params' => [
            '*serviceid' => ['integer', 'The service.'],
            '*func_name' => ['string', 'A function the service\'s server module lists in customFunctions().'],
        ],
        'errors' => [
            404 => 'No such service, or its module offers no function by that name (the message lists the ones it offers).',
            422 => 'The function ran and reported a failure.',
            502 => 'The function failed.',
        ],
        'notes' => 'None of the built-in server modules offers a custom function; this is for third-party modules.',
    ],
    'upgradeproduct' => [
        'section' => 'services',
        'params' => [
            '*serviceid' => ['integer', 'The service.'],
            '*packageid' => ['integer', 'The new product.'],
        ],
        'returns' => [
            'serviceid' => 'integer',
            'upgradeid' => 'integer',
            'invoiceid' => 'integer or null: the invoice for the difference',
            'applied' => 'boolean: true when the change was made at once (nothing to pay)',
        ],
        'errors' => [422 => 'The change is not allowed.'],
        'notes' => 'The same upgrade the client area offers: the difference is billed, and the server is changed once it is paid.',
    ],
    'addcancelrequest' => [
        'section' => 'services',
        'params' => [
            '*serviceid' => ['integer', 'The service.'],
            'type' => ['string', 'Immediate, or End of Billing Period (the default).'],
            'reason' => ['string', 'Up to 1000 characters.'],
        ],
        'returns' => ['serviceid' => 'integer'],
        'errors' => [404 => 'No such service.', 409 => 'A request is already open for it.', 422 => 'The service is terminated, cancelled or fraud.'],
    ],
    'getcancelledpackages' => [
        'section' => 'services',
        'list' => true,
        'notes' => 'The cancellation requests, newest first, each with its service.',
    ],
    'addproduct' => [
        'section' => 'services',
        'params' => [
            '*name' => ['string', 'Up to 255 characters.'],
            '*gid' => ['integer', 'The product group.'],
            '*type' => ['string', 'hostingaccount, reselleraccount, server or other (hosting, reseller, vps, ssl, other are accepted too).'],
            '*paytype' => ['string', 'free, onetime or recurring.'],
            'description' => ['string', 'Description.'],
            'module' => ['string', 'The server module that provisions it, for example panelica.'],
            'servergroupid' => ['integer', 'The server group.'],
            'autosetup' => ['string', 'order (set up when ordered; on is accepted too), payment, or manual.'],
            'package_name' => ['string', 'The package on the server.'],
            'pricing' => ['object', 'pricing[currencyid][cycle]=price, cycles monthly to triennially; -1 means not sold on that cycle.'],
        ],
        'returns' => ['pid' => 'integer'],
        'notes' => 'Created as the product screen creates it.',
        'example' => ['name' => 'Starter', 'gid' => 1, 'type' => 'hostingaccount', 'paytype' => 'recurring', 'module' => 'panelica', 'pricing[1][monthly]' => '4.99'],
    ],

    // ─────────────────────────────── DOMAINS ───────────────────────────────

    'getclientsdomains' => [
        'section' => 'domains',
        'list' => true,
        'params' => [
            'clientid' => ['integer', 'Only this client\'s domains. userid is accepted too.'],
            'domainid' => ['integer', 'One domain.'],
            'domain' => ['string', 'Domains whose name contains this.'],
            'status' => ['string', 'pending, active, grace, redemption, expired, cancelled, fraud or transferred_away.'],
        ],
    ],
    'gettldpricing' => [
        'section' => 'domains',
        'returns' => ['pricing' => 'array of the enabled extensions and their register, transfer and renew prices'],
    ],
    'domainregister' => [
        'section' => 'domains',
        'params' => [
            '*clientid' => ['integer', 'The client.'],
            '*domain' => ['string', 'The domain name.'],
            'years' => ['integer', '1 to 10. Default 1.'],
            'registrar' => ['string', 'A registrar module installed here. Default manual.'],
        ],
        'returns' => ['domainid' => 'integer', 'status' => 'string'],
        'errors' => [422 => 'The name is not a domain, or the registrar is unknown.'],
    ],
    'domaintransfer' => [
        'section' => 'domains',
        'params' => [
            '*domainid' => ['integer', 'The domain record.'],
            '*eppcode' => ['string', 'The transfer code from the current registrar.'],
        ],
        'returns' => ['domainid' => 'integer', 'message' => 'string'],
        'errors' => [422 => 'No registrar module is set on the domain, or the registrar refused.'],
    ],
    'domainrenew' => [
        'section' => 'domains',
        'params' => [
            '*domainid' => ['integer', 'The domain.'],
            'years' => ['integer', '1 to 10. Default 1.'],
        ],
        'returns' => ['domainid' => 'integer', 'expiry_date' => 'date'],
    ],
    'domaingetnameservers' => [
        'section' => 'domains',
        'params' => ['*domainid' => ['integer', 'The domain.']],
        'returns' => ['domainid' => 'integer', 'ns1' => 'string', 'ns2' => 'string', 'ns3' => 'string or null', 'ns4' => 'string or null', 'ns5' => 'string or null'],
        'errors' => [404 => 'No such domain.'],
        'notes' => 'The nameservers PNLCS has on record.',
    ],
    'domainupdatenameservers' => [
        'section' => 'domains',
        'params' => [
            '*domainid' => ['integer', 'The domain.'],
            '*ns1' => ['string', 'First nameserver.'],
            'ns2' => ['string', 'Second nameserver.'],
            'ns3' => ['string', 'Third nameserver.'],
            'ns4' => ['string', 'Fourth nameserver.'],
            'ns5' => ['string', 'Fifth nameserver.'],
        ],
        'returns' => ['domainid' => 'integer'],
        'errors' => [404 => 'No such domain.', 502 => 'The registrar did not accept them.'],
        'notes' => 'Sent to the registrar, as from the client area.',
    ],
    'domaingetlockingstatus' => [
        'section' => 'domains',
        'params' => ['*domainid' => ['integer', 'The domain.']],
        'returns' => ['domainid' => 'integer', 'lockstatus' => 'the lock status the registrar reports'],
        'errors' => [404 => 'No such domain.', 422 => 'No registrar module is set on the domain.', 502 => 'The registrar could not be reached.'],
    ],
    'domainupdatelockingstatus' => [
        'section' => 'domains',
        'params' => [
            '*domainid' => ['integer', 'The domain.'],
            '*lockstatus' => ['boolean', '1 to lock, 0 to unlock.'],
        ],
        'returns' => ['domainid' => 'integer', 'lockstatus' => 'boolean'],
        'errors' => [404 => 'No such domain.', 422 => 'No registrar module, or the registrar refused.', 502 => 'The registrar could not be reached.'],
    ],
    'domaingetwhoisinfo' => [
        'section' => 'domains',
        'params' => ['*domainid' => ['integer', 'The domain.']],
        'returns' => ['domainid' => 'integer', 'whois' => 'object: Registrant {Name, Organisation, Address1, City, State, Postcode, Country, Phone Number, Email Address}'],
        'errors' => [404 => 'No such domain.'],
        'notes' => 'Built from the client record in PNLCS, not read from the registrar.',
    ],
    'domainupdatewhoisinfo' => [
        'section' => 'domains',
        'unavailable' => 'Always answers 501: no registrar module implements a contact update. Change the contacts at the registrar.',
    ],
    'domainrequestepp' => [
        'section' => 'domains',
        'params' => ['*domainid' => ['integer', 'The domain.']],
        'returns' => ['domainid' => 'integer', 'eppcode' => 'string'],
        'errors' => [404 => 'No such domain.', 422 => 'No registrar module, or the registrar returned no code (its message is passed on).', 502 => 'The registrar could not be reached.'],
    ],
    'domaintoggleidprotect' => [
        'section' => 'domains',
        'params' => [
            '*domainid' => ['integer', 'The domain.'],
            'idprotect' => ['boolean', 'The state you want. Left out, the current state is flipped.'],
        ],
        'returns' => ['domainid' => 'integer', 'idprotection' => 'boolean'],
        'errors' => [404 => 'No such domain.'],
    ],
    'domainrelease' => [
        'section' => 'domains',
        'unavailable' => 'Always answers 501: no registrar module implements a release. Use domainrequestepp to get the transfer code.',
    ],
    'domainwhois' => [
        'section' => 'domains',
        'params' => ['*domain' => ['string', 'The name to check.']],
        'returns' => ['domain' => 'string', 'status' => 'available or unavailable'],
        'errors' => [422 => 'The name is not a domain.', 503 => 'No registrar or WHOIS server answered, so availability is unknown.'],
        'notes' => 'The same check as the domain search in the shop.',
    ],
    'updateclientdomain' => [
        'section' => 'domains',
        'params' => [
            '*domainid' => ['integer', 'The domain.'],
            'status' => ['string', 'pending, active, grace, redemption, expired, cancelled, fraud or transferred_away.'],
            'expirydate' => ['date', 'Expiry date.'],
            'nextduedate' => ['date', 'Next due date.'],
            'regdate' => ['date', 'Registration date.'],
            'registrar' => ['string', 'A registrar module installed here.'],
            'paymentmethod' => ['string', 'A gateway installed here.'],
            'dns_management' => ['boolean', 'DNS management add-on.'],
            'email_forwarding' => ['boolean', 'Email forwarding add-on.'],
            'id_protection' => ['boolean', 'ID protection add-on.'],
            'notes' => ['string', 'Admin notes.'],
        ],
        'returns' => ['domainid' => 'integer'],
        'errors' => [404 => 'No such domain.'],
        'notes' => 'Changes the record only; nothing is sent to the registrar.',
    ],
    'createorupdatetld' => [
        'section' => 'domains',
        'params' => [
            '*extension' => ['string', 'The extension, with or without the dot: .com or com.'],
            'register_price' => ['number', '0 or more.'],
            'transfer_price' => ['number', '0 or more.'],
            'renew_price' => ['number', '0 or more.'],
            'enabled' => ['boolean', 'Offered in the domain search.'],
            'sort_order' => ['integer', '0 or more.'],
        ],
        'returns' => ['tldid' => 'integer'],
    ],

    // ─────────────────────────────── TICKETS ───────────────────────────────

    'gettickets' => [
        'section' => 'tickets',
        'list' => true,
        'params' => [
            'clientid' => ['integer', 'Only this client\'s tickets. userid is accepted too.'],
            'deptid' => ['integer', 'Only this department.'],
            'status' => ['string', 'A ticket status, such as Open, Answered, Customer-Reply or Closed.'],
        ],
        'notes' => 'Most recently active first.',
    ],
    'getticket' => [
        'section' => 'tickets',
        'params' => ['*ticketid' => ['integer', 'The ticket.']],
        'returns' => ['ticket' => 'object: the ticket with its department, replies and staff notes'],
        'errors' => [404 => 'No such ticket.'],
    ],
    'openticket' => [
        'section' => 'tickets',
        'params' => [
            '*deptid' => ['integer', 'The support department.'],
            '*subject' => ['string', 'Up to 255 characters.'],
            '*message' => ['string', 'The first message.'],
            '*email' => ['email', 'The sender address.'],
            'clientid' => ['integer', 'The client, when the sender is one. userid is accepted too.'],
            'name' => ['string', 'The sender name, for a guest.'],
            'priority' => ['string', 'low, medium (default), high or critical.'],
            'adminusername' => ['string', 'Any value: the ticket is opened by staff.'],
        ],
        'returns' => ['tid' => 'string: the six-digit ticket number', 'ticketid' => 'integer'],
        'notes' => 'The acknowledgement to the sender and the alert to support go out as for any new ticket.',
    ],
    'addticketreply' => [
        'section' => 'tickets',
        'params' => [
            '*ticketid' => ['integer', 'The ticket.'],
            '*message' => ['string', 'The reply.'],
            'adminusername' => ['string', 'Any value: the reply is from staff, signed by the staff member the credential belongs to. Left out, the reply is the client\'s.'],
            'userid' => ['integer', 'For a client reply: which client. Default: the ticket\'s client.'],
        ],
        'returns' => ['replyid' => 'integer'],
        'errors' => [404 => 'No such ticket.'],
        'notes' => 'The ticket status and the notification mails follow, as for a reply from the panel.',
    ],
    'addticketnote' => [
        'section' => 'tickets',
        'params' => [
            '*ticketid' => ['integer', 'The ticket.'],
            '*message' => ['string', 'The note. Staff only; the client never sees it.'],
        ],
        'returns' => ['noteid' => 'integer'],
        'errors' => [404 => 'No such ticket.'],
    ],
    'updateticket' => [
        'section' => 'tickets',
        'params' => [
            '*ticketid' => ['integer', 'The ticket.'],
            'status' => ['string', 'One of the ticket statuses configured here (any letter case).'],
            'subject' => ['string', 'Up to 255 characters.'],
            'priority' => ['string', 'low, medium, high or critical.'],
            'deptid' => ['integer', 'Move it to this department.'],
            'flag' => ['integer', 'Assign it to this staff account (admin id).'],
        ],
        'returns' => ['ticketid' => 'integer'],
        'errors' => [404 => 'No such ticket.'],
    ],
    'updateticketreply' => [
        'section' => 'tickets',
        'params' => [
            '*replyid' => ['integer', 'The reply.'],
            'message' => ['string', 'The new text. Cannot be empty.'],
        ],
        'returns' => ['replyid' => 'integer'],
        'errors' => [404 => 'No such reply.'],
    ],
    'deleteticket' => [
        'section' => 'tickets',
        'params' => ['*ticketid' => ['integer', 'The ticket.']],
        'errors' => [404 => 'No such ticket.'],
    ],
    'deleteticketnote' => [
        'section' => 'tickets',
        'params' => ['*noteid' => ['integer', 'The note.']],
        'errors' => [404 => 'No such note.'],
    ],
    'deleteticketreply' => [
        'section' => 'tickets',
        'params' => ['*replyid' => ['integer', 'The reply.']],
        'errors' => [404 => 'No such reply.'],
    ],
    'getticketcounts' => [
        'section' => 'tickets',
        'returns' => ['counts' => 'object: all, open, answered, customer_reply, on_hold, closed'],
    ],
    'getticketnotes' => [
        'section' => 'tickets',
        'params' => ['*ticketid' => ['integer', 'The ticket.']],
        'returns' => ['notes' => 'array of staff notes'],
        'errors' => [404 => 'No such ticket.'],
    ],
    'getticketattachment' => [
        'section' => 'tickets',
        'params' => [
            '*ticketid' => ['integer', 'The ticket.'],
            'attachmentindex' => ['integer', 'Which file. Left out, the files are listed instead.'],
        ],
        'returns' => [
            'attachments' => 'without attachmentindex: array of {index, replyid, filename}',
            'filename' => 'with attachmentindex: string',
            'data' => 'with attachmentindex: the file, base64 encoded',
        ],
        'errors' => [404 => 'No such ticket, or no file at that index.'],
    ],
    'getsupportdepartments' => [
        'section' => 'tickets',
        'returns' => ['departments' => 'array of support departments in their display order'],
    ],
    'getsupportstatuses' => [
        'section' => 'tickets',
        'returns' => ['statuses' => 'array of ticket statuses in their display order'],
    ],
    'getticketpredefinedcats' => [
        'section' => 'tickets',
        'returns' => ['categories' => 'array of canned reply categories'],
    ],
    'getticketpredefinedreplies' => [
        'section' => 'tickets',
        'params' => ['catid' => ['integer', 'Only this category.']],
        'returns' => ['replies' => 'array of canned replies'],
    ],
    'mergeticket' => [
        'section' => 'tickets',
        'params' => [
            '*ticketid' => ['integer', 'The ticket to merge away.'],
            '*mergeid' => ['integer', 'The ticket to merge into.'],
        ],
        'returns' => ['ticketid' => 'integer: the ticket that remains'],
        'errors' => [404 => 'Either ticket does not exist.', 422 => 'The two ids are the same.'],
        'notes' => 'The replies and staff notes move to mergeid; ticketid is closed and points to it.',
    ],
    'blockticketsender' => [
        'section' => 'tickets',
        'params' => [
            '*ticketid' => ['integer', 'The ticket whose sender to block.'],
            'delete' => ['boolean', 'Delete the ticket as well.'],
        ],
        'returns' => ['ticketid' => 'integer', 'blocked' => 'string: the blocked address'],
        'errors' => [404 => 'No such ticket.', 422 => 'The ticket has no sender address.'],
        'notes' => 'Adds the address to the ticket spam filter: no more tickets from it, by mail or by form.',
    ],

    // ──────────────────────────────── QUOTES ───────────────────────────────

    'getquotes' => [
        'section' => 'quotes',
        'list' => true,
        'params' => [
            'userid' => ['integer', 'Only this client\'s quotes.'],
            'status' => ['string', 'Draft, Sent, Accepted or Declined.'],
        ],
        'notes' => 'Each quote carries its client and line items.',
    ],
    'createquote' => [
        'section' => 'quotes',
        'params' => [
            '*userid' => ['integer', 'The client. clientid is accepted too.'],
            'subject' => ['string', 'Default Quote.'],
            'validuntil' => ['date', 'Default 30 days from today.'],
            'items' => ['array', 'The lines: items[0][description], items[0][quantity] (default 1), items[0][unit_price], items[0][discount], items[0][taxable].'],
        ],
        'returns' => ['quoteid' => 'integer'],
        'notes' => 'Created as a Draft. Send it with sendquote.',
    ],
    'updatequote' => [
        'section' => 'quotes',
        'params' => [
            '*quoteid' => ['integer', 'The quote.'],
            'status' => ['string', 'Draft, Sent, Accepted or Declined (exactly as written).'],
            'validuntil' => ['date', 'Valid until.'],
            'notes' => ['string', 'Admin notes.'],
            'customer_notes' => ['string', 'Notes shown to the client.'],
            'proposal' => ['string', 'The proposal text.'],
        ],
        'returns' => ['quoteid' => 'integer'],
        'errors' => [404 => 'No such quote.'],
    ],
    'deletequote' => [
        'section' => 'quotes',
        'params' => ['*quoteid' => ['integer', 'The quote.']],
        'errors' => [404 => 'No such quote.'],
    ],
    'sendquote' => [
        'section' => 'quotes',
        'params' => ['*quoteid' => ['integer', 'The quote.']],
        'returns' => ['quoteid' => 'integer', 'status' => 'string: Sent'],
        'errors' => [404 => 'No such quote.'],
        'notes' => 'Marks it Sent, as the Send button in the admin area does, so the client can see and accept it in the client area. No email is sent.',
    ],
    'acceptquote' => [
        'section' => 'quotes',
        'params' => ['*quoteid' => ['integer', 'The quote.']],
        'returns' => ['quoteid' => 'integer', 'status' => 'string: Accepted', 'invoiceid' => 'integer: the invoice raised from it'],
        'errors' => [404 => 'No such quote.'],
        'notes' => 'Raises the invoice, as the client Accept button does. Accepting an accepted quote changes nothing.',
    ],

    // ─────────────────────────────── PROJECTS ──────────────────────────────

    'getprojects' => [
        'section' => 'projects',
        'list' => true,
        'params' => ['userid' => ['integer', 'Only this client\'s projects.']],
        'notes' => 'Each project carries its client, tasks and messages.',
    ],
    'getproject' => [
        'section' => 'projects',
        'params' => ['*projectid' => ['integer', 'The project. id is accepted too.']],
        'returns' => ['project' => 'object: the project with its client, messages, and tasks with their timers'],
        'errors' => [404 => 'No such project.'],
    ],
    'createproject' => [
        'section' => 'projects',
        'params' => [
            '*title' => ['string', 'Up to 255 characters.'],
            '*clientid' => ['integer', 'The client.'],
            'status' => ['string', 'pending (default), in_progress, completed or cancelled.'],
            'due_date' => ['date', 'Due date.'],
            'description' => ['string', 'Description.'],
        ],
        'returns' => ['projectid' => 'integer'],
        'notes' => 'Owned by the staff member the credential belongs to.',
    ],
    'updateproject' => [
        'section' => 'projects',
        'params' => [
            '*projectid' => ['integer', 'The project. id is accepted too.'],
            'title' => ['string', 'Up to 255 characters.'],
            'description' => ['string', 'Description.'],
            'status' => ['string', 'pending, in_progress, completed or cancelled.'],
            'due_date' => ['date', 'Due date.'],
        ],
        'returns' => ['projectid' => 'integer'],
        'errors' => [404 => 'No such project.'],
    ],
    'addprojectmessage' => [
        'section' => 'projects',
        'params' => [
            '*projectid' => ['integer', 'The project. project_id is accepted too.'],
            '*message' => ['string', 'The message.'],
        ],
        'returns' => ['messageid' => 'integer'],
        'errors' => [404 => 'No such project.'],
        'notes' => 'Signed with the user name of the staff member the credential belongs to.',
    ],
    'addprojecttask' => [
        'section' => 'projects',
        'params' => [
            '*projectid' => ['integer', 'The project. project_id is accepted too.'],
            '*task' => ['string', 'The task, up to 255 characters. title is accepted too.'],
            'notes' => ['string', 'Details. description is accepted too.'],
            'due_date' => ['date', 'Due date.'],
        ],
        'returns' => ['taskid' => 'integer'],
        'errors' => [404 => 'No such project.'],
    ],
    'updateprojecttask' => [
        'section' => 'projects',
        'params' => [
            '*taskid' => ['integer', 'The task.'],
            'task' => ['string', 'Up to 255 characters. title is accepted too.'],
            'notes' => ['string', 'Details. description is accepted too.'],
            'completed' => ['boolean', 'Mark it done or not done.'],
            'due_date' => ['date', 'Due date.'],
        ],
        'returns' => ['taskid' => 'integer'],
        'errors' => [404 => 'No such task.'],
    ],
    'deleteprojecttask' => [
        'section' => 'projects',
        'params' => ['*taskid' => ['integer', 'The task.']],
        'errors' => [404 => 'No such task.'],
    ],
    'starttasktimer' => [
        'section' => 'projects',
        'params' => [
            '*taskid' => ['integer', 'The task.'],
            'projectid' => ['integer', 'When sent, the task must belong to this project.'],
        ],
        'returns' => ['timerid' => 'integer', 'taskid' => 'integer', 'started_at' => 'ISO 8601 time'],
        'errors' => [404 => 'No such task (in that project).', 409 => 'A timer is already running for this task and staff member.'],
        'notes' => 'The timer belongs to the staff member the credential belongs to.',
    ],
    'endtasktimer' => [
        'section' => 'projects',
        'params' => ['*timerid' => ['integer', 'The timer.']],
        'returns' => ['timerid' => 'integer', 'seconds' => 'integer: how long this timer ran', 'task_total_seconds' => 'integer: all timers on the task'],
        'errors' => [404 => 'No such timer.'],
        'notes' => 'Ending a timer that has already ended changes nothing and reports it again.',
    ],

    // ────────────────────────────── AFFILIATES ─────────────────────────────

    'getaffiliates' => [
        'section' => 'affiliates',
        'list' => true,
        'notes' => 'Each affiliate carries its client.',
    ],
    'affiliateactivate' => [
        'section' => 'affiliates',
        'params' => ['*clientid' => ['integer', 'The client.']],
        'returns' => ['affiliateid' => 'integer'],
        'errors' => [404 => 'No such client.'],
        'notes' => 'Makes the client an affiliate on 10 percent commission; an affiliate already is left as it is.',
    ],

    // ──────────────────────────── API CREDENTIALS ──────────────────────────

    'listoauthcredentials' => [
        'section' => 'credentials',
        'returns' => ['credentials' => 'array of {id, identifier, description, allowed_ips, created_at} for every active credential. Secrets are never returned.'],
    ],
    'createoauthcredential' => [
        'section' => 'credentials',
        'params' => [
            'description' => ['string', 'Up to 255 characters.'],
            'allowed_ips' => ['string', 'The addresses it may be used from: IPv4 or IPv6 addresses and CIDR ranges, separated by commas (or an array). Left out, it works from anywhere.'],
        ],
        'returns' => ['credentialid' => 'integer', 'identifier' => 'string', 'secret' => 'string: shown this once, only its hash is stored', 'allowed_ips' => 'array'],
        'errors' => [422 => 'An allowed_ips entry is not an address or a range.'],
        'notes' => 'The new credential belongs to the staff member whose credential made the call, with that account\'s permissions.',
    ],
    'updateoauthcredential' => [
        'section' => 'credentials',
        'params' => [
            '*credentialid' => ['integer', 'The credential.'],
            'description' => ['string', 'Description.'],
            'active' => ['boolean', 'Switch it on or off.'],
            'allowed_ips' => ['string', 'Replace the addresses it may be used from (see createoauthcredential). Send it empty to allow anywhere.'],
        ],
        'returns' => ['credentialid' => 'integer'],
        'errors' => [404 => 'No such credential.', 422 => 'An allowed_ips entry is not an address or a range.'],
    ],
    'deleteoauthcredential' => [
        'section' => 'credentials',
        'params' => ['*credentialid' => ['integer', 'The credential.']],
        'errors' => [404 => 'No such credential.'],
    ],

    // ────────────────────────────────── SSL ────────────────────────────────

    'getsslorders' => [
        'section' => 'ssl',
        'params' => [
            'client_id' => ['integer', 'Only this client\'s certificates.'],
            'status' => ['string', 'Only this status.'],
            'limitnum' => ['integer', 'Page size, 1 to 100. Default 25.'],
            'page' => ['integer', 'Page number. Default 1.'],
        ],
        'returns' => ['totalresults' => 'integer', 'orders' => 'array of SSL orders, newest first, each with its client and service'],
    ],
    'getsslorder' => [
        'section' => 'ssl',
        'params' => ['*order_id' => ['integer', 'The SSL order. id is accepted too.']],
        'returns' => ['order' => 'object: the order with its client and service'],
        'errors' => [404 => 'No such order.'],
    ],
    'addsslorder' => [
        'section' => 'ssl',
        'params' => [
            '*client_id' => ['integer', 'The client.'],
            '*module' => ['string', 'An SSL module installed here, for example gogetssl.'],
            'service_id' => ['integer', 'A service of this client.'],
            'cert_type' => ['string', 'The certificate product at the provider.'],
            'domain' => ['string', 'The name it is for; *.example.com for a wildcard.'],
        ],
        'returns' => ['order_id' => 'integer', 'message' => 'string'],
        'notes' => 'Created as Awaiting Configuration. Send the CSR with configsslorder.',
    ],
    'configsslorder' => [
        'section' => 'ssl',
        'params' => [
            '*order_id' => ['integer', 'The SSL order.'],
            'csr' => ['string', 'The certificate signing request.'],
            'webserver_type' => ['string', 'As the provider names it.'],
            'validation_method' => ['string', 'email, dns or http.'],
            'approver_email' => ['email', 'For email validation (see getsslapproveremails).'],
            'domain' => ['string', 'The main name.'],
            'domains' => ['string', 'Extra names (SAN).'],
            'admin_first_name' => ['string', 'Contact details the provider asks for: admin_first_name, admin_last_name, admin_email, admin_phone, admin_org, admin_address, admin_city, admin_state, admin_zip, admin_country.'],
        ],
        'errors' => [404 => 'No such order.', 502 => 'The SSL provider could not be reached.'],
        'notes' => 'Answers result success or error with the provider message.',
    ],
    'cancelsslorder' => [
        'section' => 'ssl',
        'params' => [
            '*order_id' => ['integer', 'The SSL order.'],
            'reason' => ['string', 'Why.'],
        ],
        'errors' => [404 => 'No such order.', 502 => 'The SSL provider could not be reached.'],
        'notes' => 'Revokes the certificate at the provider.',
    ],
    'reissuesslorder' => [
        'section' => 'ssl',
        'params' => [
            '*order_id' => ['integer', 'The SSL order.'],
            '*csr' => ['string', 'The new certificate signing request.'],
        ],
        'errors' => [404 => 'No such order.', 422 => 'No CSR was sent.', 502 => 'The SSL provider could not be reached.'],
    ],
    'resendsslvalidation' => [
        'section' => 'ssl',
        'params' => ['*order_id' => ['integer', 'The SSL order.']],
        'errors' => [404 => 'No such order.', 502 => 'The SSL provider could not be reached.'],
    ],
    'getsslapproveremails' => [
        'section' => 'ssl',
        'params' => [
            '*domain' => ['string', 'The domain.'],
            'module' => ['string', 'The SSL module to ask. Default gogetssl.'],
        ],
        'returns' => ['emails' => 'array of addresses that may approve the certificate'],
        'errors' => [404 => 'No such SSL module.', 422 => 'No domain was sent.', 502 => 'The SSL provider could not be reached.'],
    ],
];
