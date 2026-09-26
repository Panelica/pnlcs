# 1. Prepare Proxmox

This is done once per Proxmox host or cluster, as `root` in the host's shell
(**Datacenter → your node → Shell** in the Proxmox web UI, or SSH). It creates
what PNLCS works with and nothing else:

| What | Name used below | Why |
|------|-----------------|-----|
| A resource pool | `pnlcs` | Every server PNLCS sells goes in it; the token reaches only this pool |
| A role | `PNLCS` | Exactly the rights billing needs — no more |
| A user and an API token | `pnlcs@pve`, token `billing` | What PNLCS signs in with |
| A cloud-init snippet | `local:snippets/pnlcs-vendor.yaml` | Lets customers sign in with their password, installs the guest agent |
| *(optional)* a second role | `PNLCSImages` | Lets the admin panel download operating systems for you |

!!! tip "You do not have to type these from here"
    Add the server in PNLCS first ([step 2](connect-the-server.md)) and press
    **Test**: it reads what the token can do and prints exactly the commands
    that are missing, filled in with your pool, storage and node names. This
    page explains what they are.

## The pool, the role, the user and the token

```bash
# A role with exactly the rights PNLCS uses
pveum role add PNLCS --privs "VM.Allocate,VM.Clone,VM.Audit,VM.PowerMgmt,VM.Console,VM.Config.CPU,VM.Config.Memory,VM.Config.Disk,VM.Config.Network,VM.Config.Options,VM.Config.Cloudinit,VM.Config.CDROM,VM.Config.HWType,VM.Snapshot,VM.Snapshot.Rollback,VM.Backup,VM.GuestAgent.Audit,VM.GuestAgent.Unrestricted,Datastore.AllocateSpace,Datastore.Audit,Pool.Audit,SDN.Use,SDN.Audit"

# A pool for the guests PNLCS sells
pveum pool add pnlcs --comment "Guests sold through PNLCS"

# A user and API token for PNLCS (the secret is printed once - copy it)
pveum user add pnlcs@pve --comment "PNLCS billing"
pveum user token add pnlcs@pve billing --privsep 0 --comment "PNLCS"

# Rights on the pool, the storages, the bridges and read-only on the nodes
pveum acl modify /pool/pnlcs --users pnlcs@pve --roles PNLCS
pveum acl modify /storage/local-lvm --users pnlcs@pve --roles PVEDatastoreUser
pveum acl modify /storage/local --users pnlcs@pve --roles PVEDatastoreUser
pveum acl modify /sdn/zones/localnetwork --users pnlcs@pve --roles PVESDNUser
pveum acl modify /nodes --users pnlcs@pve --roles PVEAuditor
```

Change `local-lvm` to the storage your servers' disks go on (ZFS, Ceph…),
and repeat that line for every such storage.

`pveum user token add` prints a table with the **full-tokenid**
(`pnlcs@pve!billing`) and the **value** (the secret, a UUID). The secret is
shown only this once; you paste both into PNLCS in the next step.

### Doing the same in the web UI

| In the Proxmox web UI | Setting |
|-----------------------|---------|
| Datacenter → Permissions → **Pools** → Create | Name `pnlcs` |
| Datacenter → Permissions → **Roles** → Create | Name `PNLCS`, the privileges from the list above |
| Datacenter → Permissions → **Users** → Add | `pnlcs`, realm *Proxmox VE authentication server* |
| Datacenter → Permissions → **API Tokens** → Add | User `pnlcs@pve`, Token ID `billing`, **untick Privilege Separation** |
| Datacenter → **Permissions** → Add → User Permission | `/pool/pnlcs` → `PNLCS`; `/storage/local-lvm` and `/storage/local` → `PVEDatastoreUser`; `/sdn/zones/localnetwork` → `PVESDNUser`; `/nodes` → `PVEAuditor` |

!!! warning "Privilege separation — the most common mistake"
    A token created with **Privilege Separation** ticked has only the
    permissions given to the *token itself*; it does not inherit its user's.
    A token made that way in the web UI — for example `root@pam!test` —
    signs in fine and then cannot create a single server. Either untick
    Privilege Separation (as `--privsep 0` does above), or give the token the
    same permissions as the user. The **Test** button spots a token with no
    permissions and says so.

!!! danger "Do not use root"
    A `root@pam` token can do everything on the host, including deleting it.
    If the billing server is ever compromised, that key is the whole cluster.
    The role above can create and manage servers **inside the pool** and read
    what it needs elsewhere. The Test button warns when a token has rights
    such as `Sys.Modify` or `Permissions.Modify` that billing never needs.

### What each right is for

| Right | Needed for | Without it |
|-------|-----------|------------|
| `VM.Allocate`, `VM.Audit`, `VM.PowerMgmt`, `VM.Config.CPU`, `VM.Config.Memory`, `VM.Config.Disk`, `VM.Config.Network`, `VM.Config.Options` | Creating, sizing, starting and stopping servers | Nothing can be sold |
| `VM.Clone` | Cloning KVM templates | No KVM from templates |
| `VM.Config.Cloudinit` | Setting passwords, addresses and the vendor snippet | Passwords and fixed addresses cannot be set |
| `VM.Config.CDROM`, `VM.Config.HWType` | Servers installed from an ISO | No ISO installs |
| `VM.Snapshot`, `VM.Snapshot.Rollback` | The customer's snapshots | No snapshots |
| `VM.Backup` | The customer's backups | No backups |
| `VM.GuestAgent.Audit` | Reading the server's addresses and disk fill | Addresses from the configuration only |
| `VM.GuestAgent.Unrestricted` | Changing the root password without a reboot | Password changes wait for the next boot |
| `VM.Console` | Reserved for the console | — |
| `Datastore.AllocateSpace`, `Datastore.Audit` | Creating disks on a storage | Disks cannot be created there |
| `SDN.Use`, `SDN.Audit` | Putting a server on a bridge (required since Proxmox 8) | Servers cannot get a network card |
| `Pool.Audit` | Reading the pool | The pool cannot be checked |

## The cloud-init snippet

The official Debian, Ubuntu, AlmaLinux and Rocky cloud images **switch SSH
password logins off and refuse `root`**, even with the right password —
measured on a Debian 12 image: `Permission denied (publickey)` with the
password Proxmox had set. They also lack the QEMU guest agent, so Proxmox
cannot report the server's address or change its password while it runs.

A small *vendor* snippet fixes both. PNLCS attaches it to every server it
builds from a template:

```bash
# Allow "snippets" on the local storage - keep the content types it already has
pvesm set local --content iso,vztmpl,backup,snippets

mkdir -p /var/lib/vz/snippets
cat > /var/lib/vz/snippets/pnlcs-vendor.yaml <<'EOF'
#cloud-config
# PNLCS vendor data: lets customers sign in with the password PNLCS gives them,
# and installs the QEMU guest agent so PNLCS can read addresses and reset passwords.
ssh_pwauth: true
write_files:
  - path: /etc/ssh/sshd_config.d/01-pnlcs.conf
    permissions: "0644"
    content: |
      PasswordAuthentication yes
      PermitRootLogin yes
packages:
  - qemu-guest-agent
runcmd:
  - [sh, -c, "systemctl enable --now qemu-guest-agent || true"]
  - [sh, -c, "systemctl restart ssh 2>/dev/null || systemctl restart sshd"]
EOF
```

!!! warning "`pvesm set --content` replaces the list"
    It sets the storage's content types to exactly what you give it. Check
    what `local` holds first (`grep -A3 "dir: local" /etc/pve/storage.cfg`)
    and keep all of it — the Test button prints the line with your storage's
    current list already filled in.

In a cluster the snippet has to exist on every node that runs servers: put
it on shared storage, or create the file on each node.

You enter `local:snippets/pnlcs-vendor.yaml` as the server's
**Cloud-init vendor snippet** in [step 2](connect-the-server.md).

## Optional: let the admin panel install operating systems

PNLCS can download the official cloud images and container templates for
you and turn them into templates ([step 3](operating-systems.md)). That
needs two more rights and a storage that accepts *import* content:

```bash
pveum role add PNLCSImages --privs "Datastore.AllocateTemplate,Datastore.AllocateSpace,Datastore.Audit,Sys.AccessNetwork"
pveum acl modify /storage/local --users pnlcs@pve --roles PNLCSImages
pveum acl modify /nodes --users pnlcs@pve --roles PNLCSImages

# Allow imported disk images on the local storage (keep the existing types)
pvesm set local --content iso,vztmpl,backup,snippets,import
```

`Sys.AccessNetwork` lets the node download from the internet on PNLCS's
request; that is what Proxmox asks for before it fetches a URL. The *import*
content type exists on current Proxmox releases (9.1, which this was tested
on, has it); on older ones install templates by hand with the recipes the Test
button prints.

## Backups

Customer backups go to one storage you name on the server in PNLCS. It can
be a local directory storage with the *backup* content type, or a Proxmox
Backup Server storage. The token needs `Datastore.AllocateSpace` on it — the
`PVEDatastoreUser` line above gives that for `local`.

## Addresses

Pick one per product:

- **DHCP on the bridge** — the server asks your network for an address. Use
  this when your router or provider hands them out.
- **From the server's IPv4 pool** — you list ranges on the server in PNLCS,
  one per line, as `first-last/prefix gw gateway`:

  ```
  203.0.113.10-203.0.113.60/24 gw 203.0.113.1
  198.51.100.7/29 gw 198.51.100.1
  ```

  Each new server gets the next free address, written into cloud-init; it goes
  back to the pool when the service is terminated or cancelled.

IPv6 can be left off or set to SLAAC (automatic) per product.

Next: [connect the server](connect-the-server.md).
