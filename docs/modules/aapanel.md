# aaPanel hosting accounts

The `aapanel` server module provisions **Sub aaPanel customer accounts** (aaPanel's **Account** feature). It uses the same `/v2/virtual/` API as aaPanel's own WHMCS integration; WHMCS is not needed. Customers manage their websites, databases, mail and DNS inside their own Sub aaPanel.

## Requirements

- **aaPanel Pro.** Sub aaPanel is a Pro feature: the panel's Account page says "This function provides 100 sub-accounts for Pro edition". Without the licence the API answers `unauthorized`, which PNLCS reports as "Sub aaPanel is not licensed on this panel (aaPanel Pro is required)". The panel must also be bound to an aaPanel.com account.
- **Sub aaPanel installed and running** under **Account**. aaPanel installs it only with Nginx as the web server. Install the PHP, MySQL and FTP services your packages need.
- At least one **resource package** under Account → Package, and a storage disk.
- The panel's **API enabled** (Settings → API) with the **PNLCS server's IP address** on its whitelist.

aaPanel serves its API with a self-signed certificate by default, so the certificate is not verified, as with every other panel module. Redirects are never followed. Use the panel's actual HTTPS port; recent installs pick a random one.

The module never installs Sub aaPanel, buys licences, opens firewall ports, changes API access or creates packages.

## Configure PNLCS

1. In **Setup → Servers**, add a server of type **aaPanel**: the panel's hostname or IP (no `https://`, path or port) and its HTTPS port. Paste the API secret key into **API Key**; it is stored encrypted. Username and Password are not used.
2. Use **Test Connection**. It passes when the key is accepted, Sub aaPanel is installed and running, and the package list can be read (so an unlicensed panel fails here).
3. On a hosting product, choose aaPanel and pick a resource package by name. In a server group, give the package the same name on every server.
4. A service's username must be 3 to 32 lowercase letters, digits or underscores, starting with a letter; otherwise PNLCS uses `pnlcs` and the service id. A strong password is generated when there is none.

Creating an account allocates the account and its package limits. It does not create a website, mail domain, DNS records or certificates; the customer does that in Sub aaPanel.

## Supported operations

- Connection test and package list
- Create, including reconciliation after a lost response
- Suspend and unsuspend (the account's status flag)
- Terminate, deleting the account's hosted resources
- Password change
- Package change
- Disk and bandwidth usage, converted from bytes to MB
- **Login to panel**: the customer opens their Sub aaPanel with a single-use link the panel mints (`get_account_temp_login_token`); the address is the one the panel returns.

aaPanel's own WHMCS module (checked 2026-10-10) implements create, terminate, password change and single sign-on, and leaves suspend, unsuspend and package change unimplemented. PNLCS adds them through `modify_account`, sending the account's current settings with only the change applied, as the panel's own Account screen does.

**Termination is destructive.** It sends `remove_account` with `is_del_resources=1`. Back up the customer's resources first.

## Identity and recovery

Each service stores, in `module_data`, a random ownership reference, the server id, the username and the account id. The ownership reference is also the remote account's **remark**; do not edit that remark while PNLCS manages the account. No secrets or passwords go into `module_data`.

A service never adopts an account just because the username matches: every change requires the stored server, username, account id and remark to match.

Create, terminate and status or package changes record their intent before the API call. If the answer is lost, the next attempt reconciles against what the panel reports instead of sending the write again. A password change cannot be verified by reading the account, so an unknown outcome blocks further changes until an operator checks the panel. After confirming that a failed operation had no effect, an administrator can clear only the matching `aapanel_create_pending`, `aapanel_terminate_pending` or `aapanel_modify_pending` flag, keeping the identity fields.

Each action holds a per-service cache lock (`aapanel:service:<id>`), as the Proxmox module does for its own work. Installations with several application nodes need a shared cache store that supports atomic locks.

## Tests

`tests/Feature/AaPanelModuleTest.php`, run with the rest of the suite. The panel is faked; the fake's answers have the shape of real ones. `get_service_info`, an empty account list (`"message": null`), a wrong key ("Secret key verification failed") and the unlicensed refusal were read from a real aaPanel 8.0.6 with Sub aaPanel 3.0.4. Account creation and the other writes could not be run against a real panel without a Pro licence; they follow the calls of aaPanel's own module.

## API references

- [aaPanel API authentication and whitelist](https://www.aapanel.com/docs/api/api-list.html)
- [Sub aaPanel and WHMCS setup](https://www.aapanel.com/docs/Function/whms/whms.html)
- [Account feature](https://www.aapanel.com/docs/Function/Account.html)
- [API forwarding in the panel](https://github.com/aaPanel/aaPanel/blob/master/class_v2/virtualModelV2/virtualModel.py)
- [Account screen payloads](https://github.com/aaPanel/aaPanel/blob/master/BTPanel/static/vite/js/app-shared.js)

Requests are form POSTs with `request_time` in milliseconds and `request_token = md5(request_time + md5(API secret))`. Successful answers carry `status = 0`; lists return `message.list` with the total in `message.page.count`, and an empty list as `"message": null`.
