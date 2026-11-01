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
    - **Verify the TLS certificate:** off by default, because a panel reached
      by IP address or carrying its own self-signed certificate would stop
      answering the moment it were on. Turn it on once the panel answers on a
      host name with a certificate a browser trusts: every call PNLCS makes to
      it then checks that certificate, as the Proxmox module's own setting
      does.
    - **Mail:** customers' mail apps are told the host name the panel gives
      the domain's mail certificate, or else `mail.<their domain>`. When a
      domain gets its first mailbox, PNLCS asks the panel for that domain's
      mail certificate if it has none. The key needs the SSL permission for
      that. A domain whose DNS is elsewhere needs its mail host name,
      `webmail.`, `autoconfig.` and `autodiscover.` pointed at the server
      before the certificate can be issued. When issuing failed, the
      customer's email page lists these records (CNAMEs to the server's
      hostname, or A records to its public address) with a button to request
      the certificate again; a mail host name outside the customer's domain is
      left to you.

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
    - **Access Key:** run `v-add-access-key 'admin' '*'` on the server and
      paste the two values it prints, the Access Key ID and the Secret Access
      Key, joined by a colon: `ID:Secret`.
    - **Admin Password:** only for servers that still use the older
      user-and-password login. It is not sent when an access key is set.

=== "Proxmox"
    - **Port:** 8006. No nameservers are needed.
    - **API token ID** (e.g. `pnlcs@pve!billing`) and **API token secret**,
      from *Datacenter → Permissions → API Tokens*. A pasted
      `PVEAPIToken=user@realm!name=secret` line works too.
    - With privilege separation on, the token has only the permissions given
      to the token itself. **Test** reads them and prints the `pveum` commands
      that fix what is missing.
    - Node, resource pool, VM id range, IPv4 addresses, backup storage and
      cloud-init snippet are set on the same form. See
      [Sell VPS on Proxmox VE](sell-vps-on-proxmox.md).

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

## Ending a service

The status buttons at the top of a service's page (**Active**, **Suspended**,
**Terminated**, **Pending**, **Cancelled**) change the record in PNLCS only.
The account on the server is suspended, unsuspended or terminated with
**Module Actions** below them.

Closing the record while its account is still on the server (marking it
**Terminated** or **Cancelled**, or deleting it) asks first what happens to
the account:

- **Terminate on the server** (the default) removes the account, its domain
  and its apps from the server, then closes the record. If the server refuses,
  nothing is changed and you are told why.
- **Keep the account on the server** closes the record only, for an account you
  are moving or will remove yourself. This is written to the activity log.

Left on the server, an account keeps running with nothing billing it, and its
domain cannot be ordered again. For Panelica servers PNLCS asks the panel
whether the account is still there; for other modules an active or suspended
service is taken to have its account. An active or suspended service that is
not linked to an account on the panel (imported, or added without **Link to an
existing account**) is asked about too: PNLCS cannot terminate it there, so
remove the account on the panel yourself, or keep it.

A domain that is already set up on the servers an order would go to is refused
while the customer orders, with a message asking them to contact you.

## One click into the panel

Panelica servers are listed on the admin dashboard under **Live Servers**, each
with a button that signs you into that panel. See
[Live Servers](live-servers.md).
