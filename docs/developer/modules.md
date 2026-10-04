# Writing a module

A module is a folder under `modules/`. Drop it in, add a `pnlcs.json`
manifest, and PNLCS registers it on the next request — **no edit to the core,
nothing to re-register after an update**, because your folder is not part of
this repository.

**1. Folder layout** — exactly two levels below `modules/`:

```
modules/
└── Gateways/                 ← Gateways, Servers, Registrars or Ssl
    └── AcmePay/
        ├── AcmePayModule.php
        └── pnlcs.json
```

The namespace follows the path (PSR-4, `Modules\` → `modules/`), so the class
above is `Modules\Gateways\AcmePay\AcmePayModule`.

**2. The manifest** — `pnlcs.json`:

```json
{
    "name": "acmepay",
    "type": "gateway",
    "class": "Modules\\Gateways\\AcmePay\\AcmePayModule",
    "version": "1.0.0",
    "display_name": "AcmePay",
    "description": "AcmePay card payments",
    "author": { "name": "Your Company" }
}
```

| Field | Required | Meaning |
|---|---|---|
| `name` | yes | Unique key, stored on invoices, products and settings. Lower-case, no spaces. |
| `type` | yes | `gateway`, `server`, `registrar` or `ssl` |
| `class` | yes | Fully qualified class name of the module |
| `version`, `display_name`, `description`, `author` | no | Informational |

Two rules are enforced when the manifest is read, so a broken module cannot
break a working installation:

- **A built-in module always wins.** A manifest named `stripe` (or any other
  built-in name) is ignored — it cannot replace a module that ships with
  PNLCS.
- **The class must be the type it claims.** The class must implement the
  interface for its `type` (table below). A server class announced as a
  gateway is not registered at all, instead of failing at checkout.

A manifest that does not parse, names a class that does not exist, or leaves
out `name`, `type` or `class` is skipped silently.

**3. The interface** — implement the one for your type (`app/Contracts/`):

| `type` | Interface | Methods |
|---|---|---|
| `gateway` | `App\Contracts\GatewayModuleInterface` | `capture`, `refund`, `getPaymentForm`, `processWebhook`, `getConfigFields`, `getModuleName`, `isTokenised` |
| `server` | `App\Contracts\ServerModuleInterface` | `create`, `suspend`, `unsuspend`, `terminate`, `changePassword`, `changePackage`, `usageUpdate`, `testConnection`, `getConfigFields`, `getModuleName` |
| `registrar` | `App\Contracts\RegistrarModuleInterface` | `register`, `transfer`, `renew`, `getNameservers`, `saveNameservers`, `getEPPCode`, `getLockStatus`, `toggleLock`, `checkAvailability`, `getConfigFields`, `getModuleName` |
| `ssl` | `App\Contracts\SslModuleInterface` | `purchaseCertificate`, `getCertificateStatus`, `renewCertificate`, `revokeCertificate`, `reissueCertificate`, `resendValidationEmail`, `changeValidationMethod`, `getApproverEmails`, `getWebServerTypes`, `getCertificateTypes`, `decodeCsr`, `generateCsr`, `testConnection`, `getConfigFields`, `getModuleName` |

A gateway that can store a card and charge it later (automatic payment) also
implements `App\Contracts\TokenizableGatewayInterface` (`beginVaulting`,
`confirmVaulting`, `detachStoredMethod`, `chargeStoredMethod`); the Stripe,
iyzico and PayPal modules are complete examples.

Actions return an array with at least `success` (bool) and `message`
(string) — for example `['success' => true, 'message' => 'Account created']`.

**4. Settings** — for gateway, registrar and SSL modules,
`getConfigFields()` describes the fields on the module's settings page; PNLCS
draws the form and stores the values. A gateway is only offered at checkout
once every field marked `required` has a value. (Server modules get their
connection details — hostname, port, username, password or API key, access
hash — from the **Setup → Servers** form instead.)

```php
public function getConfigFields(): array
{
    return [
        ['name' => 'api_key',  'label' => 'API Key',   'type' => 'password', 'required' => true],
        ['name' => 'mode',     'label' => 'Mode',      'type' => 'select',   'options' => ['live' => 'Live', 'test' => 'Test']],
        ['name' => 'debug',    'label' => 'Debug log', 'type' => 'yesno',    'default' => '0'],
        ['name' => 'note',     'label' => 'Note',      'type' => 'textarea'],
    ];
}
```

Field types: `text`, `password` (never echoed back into the page), `textarea`,
`select` (with `options`) and `yesno`. A gateway reads its saved values from
`App\Models\GatewaySettings` (`gateway` = your `name`, `setting` = the field
`name`); the Mollie and Tpay modules show the pattern in a few lines.

**5. Start from a working module.** The simplest complete examples are
`modules/Servers/Custom` (a server module where every action succeeds) and
`modules/Gateways/BankTransfer` (an offline gateway). Copy one, rename the
folder, namespace and class, write the manifest, and open **Setup → Modules**
— your module appears there marked **Third-party**.

**Gateway callbacks.** A gateway without a route of its own receives its
provider's payment confirmations at `/gateway/<name>/callback` (GET or POST, no
CSRF; `route('gateway.callback', '<name>')`). The route answers only while the
gateway is switched on with its required settings filled in.

- `processWebhook()` gets the request fields plus `_raw_payload` (the raw body)
  and `_headers` (lower-case names). **Verify the call yourself**: signature,
  amount, currency.
- Return `['success' => true, 'invoice_id' => …, 'transaction_id' => …,
  'amount' => …]` to apply a payment. **Always return the provider's
  transaction id**: it is what keeps a retried callback from paying twice, and
  a success without one is logged and not applied.
- `response` (the body your provider expects, `OK` by default) and
  `http_status` set the reply. Return them on failures too, so the provider
  stops retrying.

**Addons** (`modules/Addons/<Name>/<Name>Module.php`, implementing
`App\Contracts\AddonModuleInterface`) need no manifest: any addon folder is
listed on the **Extensions** page (`/admin/config/addons/modules`, in the
settings sidebar) and on the Modules screen, where it is activated. The Staff Board and Project Management addons are working
examples.

**Admin menu entries.** While an addon is active, the entries its `sidebar()`
returns are listed in the **Extensions** menu of the admin top navigation. An
entry is `['label' => …, 'url' => …]`, optionally with `'children'` of the same
shape; entries without a label or a url are left out, and an addon whose
`sidebar()` throws is logged and skipped. The addon's own page
(`output()`) lives at `/admin/config/addons/modules/<name>`.

**Pages, views and tables from an addon.** An addon may ship its own Laravel
service provider, `modules/Addons/<Name>/<Name>ServiceProvider.php` (class
`Modules\Addons\<Name>\<Name>ServiceProvider`). PNLCS registers it while the
addon is active, so through it the addon can use the framework's own means:
routes (pages for customers or visitors), views, migrations and scheduled
tasks. Inactive addons, and addons without the file, change nothing. A provider
that fails - its file does not load, or its `register()` or `boot()` throws - is
logged with the addon's name (`Addon <name>: service provider failed to load`)
and skipped; the panel and the other addons keep working. Activating or deactivating an addon clears
cached routes (`php artisan optimize`), so its pages appear and disappear at once.

```php
// modules/Addons/Reviews/ReviewsServiceProvider.php
namespace Modules\Addons\Reviews;

use Illuminate\Support\ServiceProvider;

class ReviewsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/routes.php');
        $this->loadViewsFrom(__DIR__.'/views', 'reviews');      // view('reviews::index')
        $this->loadMigrationsFrom(__DIR__.'/migrations');
    }
}
```

```php
// modules/Addons/Reviews/routes.php
use Illuminate\Support\Facades\Route;

// A client-area page: the same guards as the client area's own pages.
Route::middleware(['web', 'banned.ip', 'client.permission', 'auth', '2fa'])
    ->prefix('client')->name('client.')
    ->group(function () {
        Route::get('reviews', [\Modules\Addons\Reviews\ReviewsController::class, 'index'])->name('reviews.index');
    });

// A public page.
Route::middleware('web')->get('reviews', [\Modules\Addons\Reviews\PublicController::class, 'index'])->name('reviews.public');
```

Keep route paths and names under your addon's own prefix so they cannot clash
with PNLCS or other addons.

**Upgrades.** PNLCS records the version an addon was activated at. When the
addon's files carry a newer `getVersion()`, its `upgrade($fromVersion)` runs
once, with the recorded version, when `php artisan pnlcs:addons-upgrade` runs
(a step of the update sequence, right after `migrate --force`; see
[Updating](../install/updating.md)), or before the addon is switched back on
if it was off. Return `['success' => true]` and the new version is recorded; on
failure (or an exception) the old version stays, the error is logged, the
command exits non-zero and the upgrade is tried again on the next run. A failed
upgrade also keeps an inactive addon off. Each addon's upgrade runs under a
lock, so two runs at once never upgrade it twice. Until it has run, the
Extensions screen shows the upgrade as pending.

**Tests.** `tests/Feature/ModuleDiscoveryTest.php` shows how to exercise a
module through the same discovery the application uses. Pull requests that add
a module with tests are reviewed first.

## Optional capabilities

A module can offer more than its interface requires:

| Implement | And PNLCS will |
|---|---|
| `App\Contracts\TokenizableGatewayInterface` (gateways) | store cards at the gateway and charge them for renewals |
| `App\Contracts\SyncsDomainData` (registrars: `syncDomain`) | read each domain's expiry, status and nameservers in the nightly domain sync |
| `App\Contracts\HostsAccountDomains` (servers: `accountDomains`, `createAccountDomain`) | offer customers **Set up on my hosting** for their domains ([guide](../guides/sell-domains.md#set-up-on-my-hosting)) |
| a `customFunctions()` method (servers) returning `method name => label` | run those methods on a service through the API ([modulecustom](../api/services.md#modulecustom)) |

## Hooks in a module

A `hooks.php` file in the module's folder is loaded on every request (for
addons: only while the addon is active). See [Hooks](hooks.md).


**SMS providers.** An addon for a local SMS provider implements
`App\Contracts\SmsSender` (`send(string $to, string $message): bool`, `$to` in
E.164) and binds it in its service provider:
`$this->app->bind(\App\Contracts\SmsSender::class, MySender::class);`.
Phone verification (`App\Contracts\PhoneVerifier`) then sends six-digit codes
of its own through it when Twilio Verify is not set up (ten minutes, five
tries); with Twilio set up, Twilio is used as before.
