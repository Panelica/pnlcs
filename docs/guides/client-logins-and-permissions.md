# Client Logins & Permissions

A **client** is an account: the company or person you bill. A **login** is a
person who signs in to the client area. They are separate on purpose:

- one account can have **several logins**: an owner, an accountant, a
  developer;
- one login can open **several accounts**, and switches between them from the
  client area menu.

## The owner

The login that opened the account (by registering, or created with the
account) is its **owner**. The owner can do everything, always.

## Other logins and what they may do

Each other login on an account has a set of **permissions**. A login without a
permission cannot open that part of the client area; the page answers with a
message telling them to ask the account owner.

| Permission | Opens |
|---|---|
| `profile` | The account's profile and billing details |
| `contacts` | Contacts |
| `products` | Services: the list and their pages |
| `manageproducts` | Changing services: upgrades, cancellations, hosting management |
| `productsso` | Signing in to a service's control panel |
| `domains` | Domains: the list and their pages |
| `managedomains` | Changing domains: nameservers, lock, auto-renew, EPP code |
| `invoices` | Invoices, payment methods and account credit |
| `quotes` | Quotes |
| `tickets` | Support tickets |
| `affiliates` | The affiliate programme |
| `emails` | Email history |
| `orders` | Checking out new orders |

Logins created before permissions existed keep full access.

## Inviting someone to an account

An invitation is an email with a link, valid for **7 days**. The person opens
it, creates a login (or signs in with the one they have) and joins the account
with the permissions chosen for them.

!!! note "Through the API for now"
    Inviting, and changing a login's permissions, have no screen in the admin
    or client area yet. Use the API:
    [createclientinvite](../api/clients.md#createclientinvite),
    [getuserpermissions](../api/clients.md#getuserpermissions) and
    [updateuserpermissions](../api/clients.md#updateuserpermissions).
    To take a login off an account: [deleteuserclient](../api/clients.md#deleteuserclient).

## Contacts are not logins

**Contacts** (the **Contacts** page in the client area; admin: the client's
contacts) are people you may email about the account, for example a billing
contact. They do not sign in.

## Signing a customer in for them

- From the admin area, staff with the permission can open the client area as
  the customer, to see what they see.
- Integrations can create a **one-time sign-in link** with
  [createssotoken](../api/clients.md#createssotoken): valid for 60 seconds and
  one use, to the client area page you choose.

## Protecting logins

Each login can turn on **two-factor authentication** from its **Security**
page in the client area, and sign out its other sessions there.
