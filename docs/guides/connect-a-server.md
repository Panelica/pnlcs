# Connect a Server

Connecting a server lets PNLCS create, suspend and terminate hosting accounts
by itself when orders are paid and cancelled. Servers live under **Setup →
Servers**.

## Add a server

1. **Setup → Servers → Add Server**
2. Choose the **type**, which is the module that talks to it: Panelica,
   cPanel, Plesk, DirectAdmin, HestiaCP, Proxmox, Vultr, or Custom for accounts
   you create by hand. The form adjusts its fields and default port to the type.
3. Fill in the name, hostname and credentials (below).
4. Optional: the server's **nameservers**. Customers who
   [set a domain up on their hosting](sell-domains.md#set-up-on-my-hosting)
   are pointed at them.
5. Save, then press **Test** next to the server in the list.

## Credentials by type

=== "Panelica"
    - **Port:** 8443 (the panel port). No username.
    - **API Key:** the `pk_live_...` key, and **API Secret:** the `sk_live_...`
      secret. Create them in the Panelica panel under **Settings → API Keys**;
      the secret is shown only once there.

=== "cPanel / WHM"
    - **Port:** 2087. **Username:** the WHM account, usually `root`.
    - **API Token:** create one under **WHM → Development → Manage API
      Tokens**. The Access Hash field is only for old servers without tokens.

=== "Plesk"
    - **Port:** 8443. **Username:** the Plesk administrator, usually `admin`.
    - **Password / API Key:** its password or an API key.

=== "DirectAdmin"
    - **Port:** 2222. **Username:** the admin account.
    - **Password / Login Key:** its password or a login key.

=== "HestiaCP"
    - **Port:** 8083. **Username:** the admin account (default `admin`).
    - Its access key in **Access Hash**, or its password in **Password**.

=== "Proxmox"
    - **Port:** 8006.
    - An API token in **Access Hash**, in the form
      `PVEAPIToken=user@realm!tokenid=UUID`; or a username (default
      `root@pam`) and password.

=== "Vultr"
    - Your Vultr API key in **Access Hash**. The module talks to Vultr's API
      directly, so the hostname is only a label.

!!! tip "Always test the connection"
    Press **Test** before you assign products. Most provisioning
    problems are wrong credentials, or a firewall closing the panel's API port.

## Server groups

With several servers of one type, group them under **Setup → Server Groups**
and point a product at the group. PNLCS picks a server from the group when it
provisions.

## Link a product to the server

A server does nothing until a product uses it. On the product
(**Setup → Products/Services**), set its server module and server or group,
and choose when accounts are created (**auto-setup**). See
[Your First Sale](../getting-started/your-first-sale.md).

## Check it works

Place a test order, pay it, and confirm the account exists on the server and
the service is **Active** in PNLCS. If not, see
[Provisioning did not happen](../troubleshooting/common-issues.md#provisioning-did-not-happen).

## One click into the panel

Panelica servers are listed on the admin dashboard under **Live Servers**, each
with a button that signs you into that panel. See
[Live Servers](live-servers.md).
