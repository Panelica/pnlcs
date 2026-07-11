# Connect a Server

Connecting a server lets PNLCS create, suspend and terminate hosting accounts
automatically when orders are paid and cancelled. Configure servers under
**Configuration → Servers**.

## Add a server

1. **Configuration → Servers → Add Server**
2. Choose the **type** (the module): Panelica, cPanel, Plesk, DirectAdmin,
   HestiaCP, Proxmox, Vultr, or Custom.
3. Enter the connection details (below), then **Test Connection** on the edit page.
4. Save.

## Credentials by module

=== "Panelica"
    - **Hostname** and **port** of your Panelica server
    - **API key / secret** from the Panelica panel

=== "cPanel / WHM"
    - **Hostname**, port `2087`
    - **Username** (`root` or a reseller) and a **WHM API token**

=== "Plesk"
    - **Hostname**, port `8443`
    - A **Plesk API secret key** (X-API-Key)

=== "DirectAdmin"
    - **Hostname**, port `2222`
    - **Username** and a **login key** (or password)

=== "HestiaCP"
    - **Hostname**, port `8083`
    - Admin **username** and **password/API key**

=== "Proxmox"
    - **Hostname**, port `8006`
    - An **API token** (`PVEAPIToken=user@realm!id=uuid`) or user + password

=== "Vultr"
    - A **Vultr API key** (no hostname needed — it's a cloud API)

!!! tip "Always test the connection"
    Use the **Test Connection** button before assigning products. Most
    provisioning problems are simply wrong credentials or a firewall blocking
    the panel's API port.

## Server groups (optional)

If you have several servers of one type, group them under
**Configuration → Servers → Server Groups** and point a product at the group.
PNLCS picks a server from the group at provisioning time.

## Link a product to the server

A server does nothing until a **product** uses it. When you create or edit a
product (**Products**), set its **Server / Module** to the server you added and
choose an **auto-setup** mode. See [Your First Sale](../getting-started/your-first-sale.md).

## Verify provisioning

Place a test order, pay it, and confirm the account appears on the server and
the service shows **Active** in PNLCS. If it doesn't, see
[Provisioning failed](../troubleshooting/common-issues.md#provisioning-did-not-happen).
