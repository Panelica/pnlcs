# aaPanel hosting accounts

The `aapanel` server module provisions **Sub aaPanel customer accounts**. It uses the same account API as aaPanel's own WHMCS integration; WHMCS is not needed.

## Requirements

- PNLCS PHP 8.4+ / Laravel 13 (tested against source commit `54c31fcf16b3fcae3da08a3e40cb8786d370e652`).
- An aaPanel edition/license that includes Sub aaPanel. A paid panel license alone does not confirm that this component is installed or running.
- Sub aaPanel installed and running under **Account**. aaPanel currently requires Nginx for installing it. Install the PHP, MySQL and FTP services your hosting packages need separately.
- At least one existing Account resource package and a configured storage disk. Configure disk quotas in aaPanel if the package needs them.
- The main panel's API enabled and the **PNLCS application's outbound IP** in the aaPanel API whitelist.
- HTTPS on the main panel, with a trusted certificate matching its hostname. This adapter verifies TLS and does not follow redirects. Use the actual panel port; 8888 is only the default.

The adapter never installs/enables the component, buys licenses, opens firewall ports, changes API access, creates packages, or changes server security settings.

## Configure PNLCS

1. Apply the complete supplied patch against the documented baseline, including the adapter, registry, server form, lifecycle-lock contract and provisioning changes. Copying only the adapter omits required safety integration. The `pnlcs.json` manifest registers the module automatically.
2. In **Configuration → Servers**, select **aaPanel**. Enter the main panel hostname (no `https://`, path or port in this field) and its HTTPS port. Paste the aaPanel **API secret key** into **API Key**. The username and Password fields are not used. PNLCS stores this secret encrypted in `servers.access_hash`; retain your existing `APP_KEY` securely.
3. Use **Test Connection**. This checks API authentication, the installed/running Sub aaPanel component and the package-list contract. Failure should be investigated in the panel's Account logs, IP whitelist and TLS setup.
4. On a hosting product, choose aaPanel and select an existing resource package by name. With a server group, provision the same package names on every member.
5. Assign a pending service to that product. A supplied username must contain 3–32 lowercase letters/numbers/underscores, start with a letter, and be unique. Otherwise PNLCS generates `pnlcs` followed by the service ID. A strong password is generated and stored encrypted if none exists.

Creation allocates the customer account and its package limits. It does **not** automatically create a website, mail domain, DNS records or SSL certificates. The customer manages those within Sub aaPanel. The main administrator's credentials are never returned to the customer. There is no SSO implementation in this version; use the Sub aaPanel login address supplied by the operator and the service's account credentials.

## Supported operations

- Connection test and resource-package discovery
- Account creation and reconciliation after a lost response
- Suspend and unsuspend using the account status flag
- Termination, including deletion of the account's hosted resources
- Account password change
- Package upgrades/downgrades, subject to aaPanel's own validation
- Account disk/bandwidth usage and limits, converting upstream bytes into PNLCS MB

PNLCS's existing provisioning service owns status changes, billing hooks and notifications. An API failure returns failure rather than activating the service. No fake success is returned for unavailable components.

**Termination is destructive.** It sends `remove_account` with `is_del_resources=1`, matching the upstream Account delete operation. Back up the customer's resources and follow your normal termination authorization and retention rules before running it.

## Identity and recovery

Each provisioned service stores a random ownership reference, original server ID, username and returned account ID in `module_data`. The ownership reference is also the remote Account **remark**. Do not edit that remark while PNLCS manages the account. No API secrets or passwords are placed in `module_data`. Only this dedicated column is trusted: customer notes, including JSON notes, are never imported as aaPanel identity or erased.

A service will not adopt another account just because a username matches. Suspend, password/package changes and termination require the stored server, username, account ID and ownership remark to match. Existing manually created accounts are not automatically imported.

Creation records its intent before issuing the API call. If the response is lost, a later retry first looks for the exact owned account. If no matching account can be established, the create remains pending and no second create is sent. Similarly, ambiguous termination is not repeated while the account remains present, and pending termination fences every other mutation and activation. A terminated identity that reappears is not deleted again automatically. Status and package modifications reconcile their pending target against the next account read before another write. Password changes cannot be verified by a read, so an unknown password outcome blocks further modifications and termination until an operator resolves it; the locally stored password may then be stale. An operator must inspect the original panel and determine the outcome; do not blindly clear recovery flags or change the username/server to force a retry. After confirming a failed operation had no remote effect, an administrator can clear only the corresponding `aapanel_create_pending` , `aapanel_terminate_pending` or `aapanel_modify_pending` flag using their normal database-management process, keeping the identity fields intact. If the remote result exists, restore its correct mapping rather than duplicating it.

Module actions use a per-service Laravel cache lock. The aaPanel module also opts into a separate provisioning lock that covers refreshing the persisted service, the remote operation, the local status/password/product commit, queue settlement and synchronous hooks. Other server modules retain their existing behavior. All aaPanel lifecycle calls must go through `ProvisioningService`; do not run concurrent direct adapter calls or edit its database state during an operation.

Multi-node installations must use a shared atomic-lock-capable cache store. The adapter lease is one hour; the encompassing lifecycle lease is two hours. The maximum bounded API path is 78 calls at a 30-second timeout (39 minutes), but database work and hooks can add time. Configure worker and synchronous-hook execution limits safely below the leases. These are bounded crash-recovery leases, not indefinite fencing or an exactly-once guarantee. A crashed worker can require waiting for expiry; unknown remote outcomes still require the reconciliation described above. Administrative changes outside this workflow and cache failures cannot be serialized by these locks.

API requests have timeouts, no automatic HTTP retry, no redirect following and strict response validation. Paginated account/package lists reject invalid identity rows, duplicate IDs, malformed totals and changing counts rather than treating partial data as proof that an account is absent. Byte counters and quotas must be nonnegative integers within the PHP integer range; fractional, non-finite or overflowing numbers are rejected. Byte-to-MiB conversion rounds up without floating-point overflow; zero limits are preserved. Raw remote bodies and transport exceptions are not exposed in module results. For diagnosis, consult the panel's own logs securely. Never paste API keys or customer passwords into issue reports.

## Tests

From a normal dependency-installed PNLCS checkout:

    php vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php tests/Feature/AaPanelModuleTest.php tests/Feature/AaPanelSetupTest.php tests/Feature/AaPanelProvisioningLockTest.php
    composer install --working-dir=tools/aapanel-quality --no-scripts --no-plugins
    sh tools/aapanel-quality/run.sh

See `tools/aapanel-quality/README.md` for the pinned complexity metric, complete scope and additional PHPStan/Pint commands. PHP 8.4+, SQLite PDO, and Node.js are required for the isolated suites; Node executes the real server-form default-port helper.

The focused tests fake all HTTP and prevent stray network requests. They use fake HTTP, in-memory model doubles and fresh SQLite `:memory:` databases rather than an operator database. Persisted two-instance lifecycle tests deterministically reproduce the status race when the outer lock is bypassed and verify serialization with it enabled. They also cover save vetoes, hooks, queue transitions and non-aaPanel compatibility. They verify the adapter contract, failure behavior, identity checks and lifecycle payloads; they do **not** prove connectivity to a particular aaPanel installation. Do not run the repository's default full test configuration against a production database. It expects the project's dedicated MySQL test environment.

## API references and compatibility

- [Official aaPanel API authentication and whitelist setup](https://www.aapanel.com/docs/api/api-list.html)
- [Official Sub aaPanel/WHMCS setup and reference adapter](https://www.aapanel.com/docs/Function/whms/whms.html)
- [Account feature documentation](https://www.aapanel.com/docs/Function/Account.html)
- [Official API forwarding implementation](https://github.com/aaPanel/aaPanel/blob/master/class_v2/virtualModelV2/virtualModel.py)
- [Official Account UI lifecycle payloads](https://github.com/aaPanel/aaPanel/blob/master/BTPanel/static/vite/js/app-shared.js)

Verified from the public sources on 2026-10-10. The `/v2/virtual/` endpoints use form POSTs with `request_time` in milliseconds and `request_token = md5(request_time + md5(API secret))`. Successful replies use numeric `status = 0`; lists return `message.list` and current pagination totals in `message.page.count`. The storage response supports the current direct `message` array and the older `message.list` shape used by the official WHMCS adapter.

The upstream WHMCS adapter leaves some lifecycle methods unimplemented. This integration uses the current aaPanel Account UI's verified `modify_account` operation for suspend/resume and package edits. No upstream PHP source or telemetry code is bundled into this module. Future aaPanel API changes may require a compatibility update; a live staging smoke test with an authorized disposable account remains recommended before production billing automation.
