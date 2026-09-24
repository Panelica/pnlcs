# Security checklist

Go through this list after installing, and again whenever you change the
server.

## The server

- [ ] **HTTPS everywhere.** `APP_URL` starts with `https://`, and HTTP
  redirects to HTTPS ([installation step 15](native.md#15-turn-on-https)).
  Checkout and the admin login must never run over plain HTTP.
- [ ] **The web root is `public/`**, never the project directory.
  `curl -sI https://example.com/.env` must answer 403 or 404.
- [ ] **`APP_DEBUG=false`** in `.env`. With debug on, an error page shows
  stack traces and configuration to anyone.
- [ ] **`.env` belongs to the web server user, mode `640`.** It holds the
  database password and `APP_KEY`.
- [ ] **The install wizard is closed.** `https://example.com/install` answers
  404 once the wizard has finished.
- [ ] **Backups leave the server** ([Backups](backups.md)), and `.env` is part
  of them: without its `APP_KEY` the stored gateway and registrar keys cannot
  be read back.
- [ ] **You update regularly** ([Updating](updating.md)).

## Staff accounts

- [ ] **Two-factor authentication** on every staff account: each person turns
  it on under **My Account** (the menu under their name).
- [ ] **Least privilege.** Every role grants only what its people need
  ([Staff & Roles](../guides/staff-and-roles.md)); keep full-administrator
  roles for the few who need them.
- [ ] **Delete accounts when people leave** (**Setup → Admin Accounts**). The
  person can no longer sign in, and the account's API credentials stop working
  at once.
- [ ] **Read the activity log** now and then: **Utilities → Activity Log**
  records who did what.

## API credentials

- [ ] One credential per integration, owned by a staff account with only the
  permissions that integration needs.
- [ ] **Restricted to the addresses it is used from**: **Setup → API
  Credentials → Edit → Allowed IP addresses**.
- [ ] Credentials are sent in the `X-API-Key` / `X-API-Secret` headers, never in
  URLs ([API overview](../api/index.md#authentication)).

## Customers and orders

- [ ] **Email verification for sign-ups** is on (**Setup → General Settings →
  Email Verification**; on by default).
- [ ] **Fraud screening** is set up if you sell to people you do not know:
  MaxMind or FraudLabs Pro under **Setup → General Settings → Fraud
  Screening**. Orders it holds wait in **Orders → Fraud**.
- [ ] Customers can protect their own logins with two-factor authentication
  from their **Security** page in the client area.
- [ ] Abusive addresses and domains go on **Setup → Banned IPs** and
  **Setup → Banned Emails**.

## Reporting a vulnerability

Please do not open a public issue. Write to
[security@panelica.com](mailto:security@panelica.com); the
[security policy](https://github.com/Panelica/pnlcs/blob/main/SECURITY.md)
explains what happens next.
