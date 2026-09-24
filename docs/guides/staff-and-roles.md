# Staff & Roles

Give your team access without handing everyone every key. Each member of staff
has an account, and each account has a **role**: a set of permissions chosen
from 45.

## Create a role

**Setup → Admin Roles**

1. Name the role (for example "Support Agent" or "Billing Manager") and
   describe it.
2. Tick the **permissions** it grants. They follow the admin areas: listing,
   viewing, creating and editing clients; invoices; orders; products and
   services; domains; tickets (listing, replying, managing); quotes; projects;
   and one for each setup screen (servers, gateways, registrars, email
   templates, staff, settings and so on).
3. Or tick **full administrator**: the role then has every permission,
   including ones added in later versions.

## Add a member of staff

**Setup → Admin Accounts**

1. Enter their name, email and username, and choose a password (at least 6
   characters).
2. Assign a **role**.
3. Save, and give them the username and password yourself: PNLCS does not email
   them. They sign in at `https://example.com/admin/login` and can change the
   password under **My Account**.

## When someone leaves

Delete their account on **Setup → Admin Accounts**. They can no longer sign
in, and every API credential the account owned stops working at the same
moment.

## Example roles

| Role | Typical permissions |
|---|---|
| **Support Agent** | List and view clients, all ticket permissions, list services |
| **Billing Manager** | Invoices, orders, clients, reports; no servers, roles or settings |
| **Administrator** | Full administrator |

## Good practice

- Give each person the **least access** their job needs.
- Ask everyone to turn on **two-factor authentication** under **My Account**.
- Review roles now and then, and delete accounts as soon as people leave.

Staff actions are recorded in the **Activity Log** (**Utilities → Activity
Log**): who did what, and when.

## API access

Staff use the API through [API credentials](../api/index.md#authentication),
which act with the permissions of the account that owns them.
