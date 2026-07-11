# Developer API & Hooks

PNLCS is built to be extended. This page is a starting point for developers;
for the full hook catalog see [`docs/HOOKS.md`](https://github.com/Panelica/pnlcs/blob/main/docs/HOOKS.md)
in the repository.

## REST API

PNLCS exposes a REST API for external integrations, authenticated with an
API key.

- **Create an API credential:** admin panel → **Configuration → API
  Credentials**.
- **Authenticate:** send your key in the request (see the in-app **API
  Documentation** page, which lists every endpoint live for your install).
- **Coverage:** clients, invoices, orders, services, domains, SSL, tickets and
  system endpoints.

The in-app API docs (**admin menu → API Documentation**) are generated from
your actual routes, so they always match your version.

## Hook system

PNLCS has a WHMCS-compatible hook system. Register a callback against a named
hook point and it runs when that event fires:

```php
add_hook('InvoicePaid', 1, function (array $vars) {
    $invoice = $vars['invoice'];
    // your logic — notify Slack, sync to accounting, etc.
});
```

**Where hook files live:**

| Location | Loaded |
|----------|--------|
| `app/Hooks/*.php` | always |
| `modules/{Gateways,Servers,Registrars,Ssl}/<Name>/hooks.php` | always |
| `modules/Addons/<Name>/hooks.php` | only when the addon is active |

A throwing hook is logged and skipped — a broken hook can never break billing
or provisioning.

**Common hook points:** `InvoicePaid`, `InvoiceCreated`, `OrderPlaced`,
`AcceptOrder`, `ServiceActivated`, `ServiceSuspended`, `ServiceTerminated`,
`AfterModuleCreate`, `ModuleActionFailed`, `TicketOpened`, `RefundIssued`, and
more.

➡️ Full reference: [docs/HOOKS.md](https://github.com/Panelica/pnlcs/blob/main/docs/HOOKS.md)

## Writing a module

Server, gateway, registrar and SSL integrations live under `modules/` as
self-contained directories, each with a handler class implementing the relevant
interface. Copy an existing module of the same type as a starting point — no
core changes are needed to add one.

## Contributing

Extensions, fixes and new modules are welcome. See
[CONTRIBUTING.md](https://github.com/Panelica/pnlcs/blob/main/CONTRIBUTING.md).
