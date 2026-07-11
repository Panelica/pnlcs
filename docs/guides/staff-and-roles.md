# Staff & Roles

Give your team access without handing everyone the keys to everything. PNLCS
uses **role-based access control (RBAC)** with 45+ fine-grained permissions.

## Create a role

**Configuration → Admin Roles → Create Role**

1. Name the role (e.g. "Support Agent", "Billing Manager").
2. Tick the **permissions** this role should have — each admin area (clients,
   invoices, orders, products, tickets, settings…) has view/create/edit/delete
   permissions you can grant individually.
3. Save.

## Add a staff member

**Configuration → Admins → Add Admin**

1. Enter their name, email and username.
2. Assign a **role**.
3. Save. They receive login details and can sign in at `/admin/login`.

## Example roles

| Role | Typical permissions |
|------|---------------------|
| **Support Agent** | View clients, manage tickets, view services — no billing or settings |
| **Billing Manager** | Invoices, orders, refunds, reports — no server or role settings |
| **Administrator** | Everything |

## Good practice

- Give each person the **least access** they need to do their job.
- Require **2FA** for all staff (they enable it under My Account).
- Review roles periodically and remove access when people leave.

Every admin action is written to the **Activity Log**
(**System → Activity Log**), so you always have an audit trail of who did what.
