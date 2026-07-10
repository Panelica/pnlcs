# PNLCS Hook System

WHMCS-compatible hook system. Register callbacks against named hook points;
core code fires them at the right moments. A throwing callback is logged and
skipped — a broken hook can never break billing or provisioning.

## Registering a hook

```php
// priority optional (default 10, lower runs first)
add_hook('InvoicePaid', 1, function (array $vars) {
    $invoice = $vars['invoice'];          // App\Models\Invoice
    // ...
});
```

## Where to put hook files

| Location | Loaded |
|---|---|
| `app/Hooks/*.php` | always (project-level hooks) |
| `modules/Gateways/<Name>/hooks.php` | always |
| `modules/Servers/<Name>/hooks.php` | always |
| `modules/Registrars/<Name>/hooks.php` | always |
| `modules/Ssl/<Name>/hooks.php` | always |
| `modules/Addons/<Name>/hooks.php` | only when the addon is **active** |

Each file simply calls `add_hook(...)` at top level. Files are loaded once per
request during application boot.

## Firing a hook from your own code

```php
$results = run_hook('MyCustomPoint', ['foo' => $bar]);
```

## Hook points

### Billing
| Hook | Params | When |
|---|---|---|
| `InvoiceCreated` / `InvoiceCreation` | invoice | new invoice generated |
| `InvoicePaid` | invoice, transactionId | invoice fully settled (any payment source) |
| `InvoicePartiallyPaid` | invoice, amount, balance | partial payment recorded |

### Orders
| Hook | Params | When |
|---|---|---|
| `OrderPlaced` / `AfterShoppingCartCheckout` | order | order created |
| `AcceptOrder` | order, manual | order accepted (auto or by admin) |
| `PendingOrder` | order | paid order awaiting manual acceptance |
| `CancelOrder` | order | order being cancelled |
| `FraudOrder` | order | order marked as fraud |

### Provisioning (server modules)
| Hook | Params | When |
|---|---|---|
| `PreModuleCreate` | service | before module create |
| `AfterModuleCreate` | service, result | module create succeeded |
| `PreModuleSuspend` | service, reason | before suspend |
| `AfterModuleSuspend` | service, reason | suspend succeeded |
| `PreModuleUnsuspend` | service | before unsuspend |
| `AfterModuleUnsuspend` | service | unsuspend succeeded |
| `PreModuleTerminate` | service | before terminate |
| `AfterModuleTerminate` | service | terminate succeeded |
| `AfterModulePassword` | service | password change succeeded |
| `AfterModuleChangePackage` | service, newProduct | package change succeeded |
| `ModuleActionFailed` | service, action, error | any module action failed (queued for retry) |

### Services (post-provisioning domain events)
| Hook | Params | When |
|---|---|---|
| `ServiceActivated` | service | service became active |
| `ServiceSuspended` | service, reason | service suspended |
| `ServiceTerminated` | service | service terminated |

### Clients & Support
| Hook | Params | When |
|---|---|---|
| `ClientCreated` / `ClientAdd` | client | new client registered |
| `TicketOpened` / `TicketOpen` | ticket | ticket opened |
| `TicketReplied` | ticket, replyMessage, isStaffReply | ticket reply added |

## Debugging

```php
app(\App\Services\HookManager::class)->registered(); // point => callback count
app(\App\Services\HookManager::class)->firedLog();   // fired this request
```
