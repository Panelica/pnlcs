# API overview

PNLCS has an HTTP API with 171 actions: clients, invoices, orders, services,
domains, tickets, quotes, projects, SSL certificates and the system itself.
Anything you can do in the admin area can be scripted, and the action names
are the ones WHMCS uses, so most WHMCS integrations need little more than a new
address.

This page explains what every call has in common. The actions themselves are
listed by area in the menu, and all together in [All endpoints](all-endpoints.md).

!!! tip "Machine-readable description"
    The whole API is also available as an OpenAPI 3.1 document:
    [openapi.json](openapi.json). Import it into Postman, Insomnia or Bruno,
    or generate a client library from it.

## Quick start

**1. Create a credential.** In the admin area open **Setup → API
Credentials** and create one. You get two values:

- the **identifier**, a 32-character name for the credential, and
- the **secret**, 64 characters, shown **once**. Only its hash is stored, so
  copy it now; if you lose it, create a new credential.

**2. Make a call.** Every action has its own address,
`https://your-pnlcs/api/v1/<action>`:

```bash
export PNLCS_IDENTIFIER="your identifier"
export PNLCS_SECRET="your secret"

curl -G https://billing.example.com/api/v1/getclients \
  -H "X-API-Key: $PNLCS_IDENTIFIER" \
  -H "X-API-Secret: $PNLCS_SECRET" \
  --data-urlencode "limitnum=5"
```

```json
{
  "result": "success",
  "totalresults": 42,
  "startnumber": 0,
  "numreturned": 5,
  "data": [ { "id": 1, "first_name": "Ada", "last_name": "Lovelace", "...": "..." } ]
}
```

**3. Check the answer.** `result` is `success` or `error`. On an error,
`message` says why.

## Addresses and methods

- Base address: `https://your-pnlcs/api/v1/`, followed by the action name.
- Actions that read use **GET**; actions that change something use **POST**.
  Each action's page says which. Calling with the other method answers `405`
  and names the right one.
- There is no single address taking an `action` parameter (the WHMCS
  `/includes/api.php` style); `POST /api/v1` with `action=...` answers `404`.

## Authentication

Every call must carry a credential. Send it in the headers:

| Header | Value |
|---|---|
| `X-API-Key` | the identifier |
| `X-API-Secret` | the secret |

Other ways are accepted for compatibility:

| How | Notes |
|---|---|
| `identifier` and `secret` parameters | Also `api_key` and `api_secret`. In a GET query string they end up in web server logs, so prefer the headers. |
| `Authorization: Bearer <secret>` | The secret alone identifies the credential. |
| `username` and `password` of a staff account | WHMCS style. Refused for accounts that use two-factor authentication. Not recommended: use a credential. |

A failed authentication answers `401`.

### What a credential may do

A credential belongs to the staff account that created it and **acts as that
account**, with exactly the permissions of its role:

- Each action needs one permission, shown on its page (for example
  `create_clients`). A call the account may not make answers `403`.
- Actions marked **full administrator only** need a role with full access.
- When the staff account is disabled or deleted, its credentials stop working
  (`403`).
- Anything the API writes in someone's name (ticket replies, notes, log
  entries, project messages) is signed with that account's user name. The
  `adminusername` parameter some WHMCS calls take does not change who signs.

Give an integration its own staff account with a role that has only the
permissions it needs.

### Restricting by address

A credential can be limited to a list of IP addresses or ranges (IPv4 and
IPv6, single addresses or CIDR such as `203.0.113.0/24`): fill in **Allowed IP
addresses** when you create it, or later with **Edit** on the API Credentials
screen (or `allowed_ips` in [createoauthcredential](credentials.md#createoauthcredential)
and [updateoauthcredential](credentials.md#updateoauthcredential)). A call
from anywhere else answers `403`. An empty list means no restriction.

**Edit** also switches a credential off without deleting it.

## Sending parameters

- **GET**: in the query string.
- **POST**: as a form (`application/x-www-form-urlencoded`) or as JSON
  (`Content-Type: application/json`).
- **Arrays**: `pid[0]=3&pid[1]=5` in a form, or a JSON array.
  Lists of objects: `items[0][description]=...&items[0][amount]=...`.
- **Booleans**: `1`/`0` or `true`/`false`.
- **Dates**: `YYYY-MM-DD`.
- Where WHMCS and PNLCS name a parameter differently, both names work; each
  action's page lists them (for example `userid` and `clientid`).

## Responses

Every answer is JSON with a `result` field:

```json
{ "result": "success", "clientid": 17 }
```

```json
{ "result": "error", "message": "Client Not Found" }
```

A validation error also lists each field that was refused:

```json
{
  "result": "error",
  "message": "The firstname field is required. (and 1 more error)",
  "errors": {
    "firstname": ["The firstname field is required."],
    "email": ["The email field is required."]
  }
}
```

## Lists and paging

List actions take the WHMCS paging parameters and answer in the same shape:

| Parameter | Meaning |
|---|---|
| `limitnum` | Rows per page, 1 to 250. Default 25. |
| `limitstart` | The first row wanted. Default 0. |

| Field | Meaning |
|---|---|
| `totalresults` | Rows matching the filters, on all pages. |
| `startnumber` | Where the returned page really starts. |
| `numreturned` | Rows in this answer. |
| `data` | The rows. |

Pages are aligned to `limitnum`: `limitstart` is rounded down to the start of
the page it falls in. With `limitnum=25`, `limitstart=30` returns rows 25 to 49
and says `startnumber: 25`. To walk a list, add `numreturned` to `startnumber`
until you reach `totalresults`.

## Errors

| Status | Meaning |
|---|---|
| 400 | The request is incomplete in a way the action checks itself (for example neither of two alternative parameters was sent), or a server module refused an action. |
| 401 | No credential, or a wrong one. |
| 403 | The credential's account lacks the permission, is disabled, or the call came from an address the credential does not allow. |
| 404 | The record does not exist, or there is no action at that address. |
| 405 | Wrong method: the message says which one the action takes. |
| 409 | The action conflicts with the current state (for example a cancellation request is already open). |
| 422 | A parameter failed validation (`errors` lists them), or the action is not possible for this record. |
| 429 | Too many requests. Wait for the number of seconds in the `Retry-After` header. |
| 501 | The action exists for WHMCS compatibility but is not available; its page says why. |
| 502 | A registrar, SSL provider or module was called and could not be reached or failed. |
| 503 | A lookup that needs an outside service could not be made (for example domain availability). |

Each action's page lists the errors specific to it.

## Rate limits

| Caller | Limit |
|---|---|
| With a credential | 300 requests per minute, per credential |
| Without a credential | 10 requests per minute, per IP address |

Every answer carries `X-RateLimit-Limit` and `X-RateLimit-Remaining`. Over the
limit the answer is `429` with `Retry-After`.

## Health checks

`GET /api/health` needs no credential and answers only whether PNLCS and its
database are up:

```json
{ "result": "success", "health": { "status": "ok", "database": "ok", "timestamp": "2026-09-24T12:00:00+00:00" } }
```

Point uptime monitors there. The detailed version, with PHP and framework
versions, disk and memory, is [gethealthstatus](system.md#gethealthstatus) and
needs a credential.

## Coming from WHMCS

1. Replace `https://your-whmcs/includes/api.php` with
   `https://your-pnlcs/api/v1/<action>`, where `<action>` is the WHMCS
   action name in lower case (`GetClients` becomes `getclients`).
2. Send the credential in the `X-API-Key` and `X-API-Secret` headers (or keep
   the `identifier` and `secret` parameters).
3. Use GET for the reading actions.
4. Read list rows from `data`.

Parameter names follow WHMCS, and where PNLCS used a different name both are
accepted. A few WHMCS actions answer `501` because PNLCS deliberately does not
offer them (card numbers through the API, decrypting stored values); their
pages give the alternative.

## Security checklist

- One credential per integration, owned by a staff account whose role has only
  the permissions that integration needs.
- Restrict each credential to the addresses it is used from.
- Send credentials in headers, never in URLs.
- Rotate a credential by creating a new one, switching the integration over,
  and deleting the old one.
- Watch the activity log: everything done through the API is attributed to the
  credential's staff account.

## The areas

| Area | Actions |
|---|---|
| [Clients](clients.md) | Accounts, contacts, logins and what they may do, credit, one-time sign-in links |
| [Invoices & Billing](invoices.md) | Invoices, payments, transactions, stored payment methods |
| [Orders](orders.md) | Placing, accepting, cancelling and screening orders |
| [Services & Products](services.md) | Services, server module actions, upgrades, cancellations, products |
| [Domains](domains.md) | Registration, transfer, renewal, nameservers, locks, extensions and prices |
| [Support Tickets](tickets.md) | Tickets, replies, notes, attachments, departments |
| [Quotes](quotes.md) | Quotes and turning them into invoices |
| [Projects](projects.md) | Projects, tasks, messages and time tracking |
| [Affiliates](affiliates.md) | The affiliate programme |
| [SSL Certificates](ssl.md) | Ordering, configuring and managing certificates |
| [API Credentials](credentials.md) | Managing credentials through the API |
| [System](system.md) | Settings, staff, modules, mail, notifications, logs, health |
