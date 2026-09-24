# pnlcs-mcp

A [Model Context Protocol](https://modelcontextprotocol.io) server for
[PNLCS](https://github.com/Panelica/pnlcs), the open-source billing panel.
Connect any MCP client - Cursor, VS Code, Windsurf, Cline, Zed and others -
to your PNLCS install and work with it in plain English:

> *"Which invoices are overdue?"* — *"Show me this client's services and
> domains."* — *"Any orders held as fraud today?"* — *"Open a ticket for
> ada@example.com about her domain renewal."*

**Zero dependencies.** The server is one small Node process that talks to the
PNLCS admin API you already have. Nothing is installed on the PNLCS side.

- npm: [`pnlcs-mcp`](https://www.npmjs.com/package/pnlcs-mcp)
- Requires: Node 18+ on the machine your AI client runs on
- Transport: stdio (spawned by your client — no port, nothing to host)

---

## 1. Create an API credential in PNLCS

1. Log in to the **admin area** of your PNLCS install.
2. Go to **Setup → API Credentials** (needs the *manage staff* permission).
3. Click **Generate API Key**, give it a name like `mcp`, and copy the two
   values it shows you:
   - **Identifier** — the credential's username
   - **Secret** — shown once; store it somewhere safe

The credential answers with **its owner's permissions**: every tool can do
exactly what that member of staff could do in the admin area, and a tool the
owner may not use answers *Your account does not have permission for this
action.* Create it under a dedicated staff account with the role you want the
assistant to have. A disabled account's credential stops working at once.

That pair is all the server needs, passed through three environment
variables:

| Variable | Value |
|---|---|
| `PNLCS_URL` | Your install's address, e.g. `https://example.com` |
| `PNLCS_IDENTIFIER` | The credential's identifier |
| `PNLCS_SECRET` | The credential's secret |
| `PNLCS_ALLOW_WRITES` | Optional. Set to `1` to also enable the write tools (see below) |

---

## 2. Connect your client

### The configuration block

Most MCP clients read the same JSON block - put it wherever your client keeps
its MCP servers:

```json
{
  "mcpServers": {
    "pnlcs": {
      "command": "npx",
      "args": ["-y", "pnlcs-mcp"],
      "env": {
        "PNLCS_URL": "https://example.com",
        "PNLCS_IDENTIFIER": "your_identifier",
        "PNLCS_SECRET": "your_secret"
      }
    }
  }
}
```

Add `"PNLCS_ALLOW_WRITES": "1"` to `env` if you also want the write tools.
Restart the client after editing its configuration. If the file is committed
to a repository, keep the secret out of it and supply it from your shell
environment instead.

### Cursor

**Settings → MCP → Add new global MCP server**, or create `.cursor/mcp.json`
in your project with the block above.

### VS Code (Copilot agent mode)

Create `.vscode/mcp.json`:

```json
{
  "servers": {
    "pnlcs": {
      "type": "stdio",
      "command": "npx",
      "args": ["-y", "pnlcs-mcp"],
      "env": {
        "PNLCS_URL": "https://example.com",
        "PNLCS_IDENTIFIER": "your_identifier",
        "PNLCS_SECRET": "your_secret"
      }
    }
  }
}
```

### Anything else (Windsurf, Cline, Zed, ...)

Every MCP client that can spawn a stdio server uses the same three pieces:
command `npx`, args `["-y", "pnlcs-mcp"]`, and the environment variables
above. From a git checkout, `node mcp/server.js` works identically.

---

## 3. Tools

### Read tools — always available

| Tool | What it answers |
|---|---|
| `get_stats` | Counts of clients, services, domains, invoices, orders, tickets and staff |
| `get_health` | Health of the install itself |
| `list_clients` | Clients, searchable by name/email/company, sortable (`sorting=DESC` for newest first), pageable |
| `get_client` | One client with contacts, by `clientid` **or** `email` |
| `list_client_services` | Hosting services of one client |
| `list_client_domains` | Domains of one client |
| `list_invoices` | Invoices; filter by `status` (`draft`, `unpaid`, `paid`, `overdue`, `cancelled`) or client |
| `get_invoice` | One invoice with its line items |
| `list_orders` | Orders; filter by `status` (`pending`, `active`, `fraud`, `cancelled`) |
| `list_tickets` | Support tickets; filter by status |
| `get_ticket` | One ticket with replies and notes |
| `get_ticket_counts` | Ticket totals per status |
| `list_transactions` | Payments, newest first |
| `list_products` | The product catalogue |
| `get_activity_log` | Recent admin and system activity |

### Write tools — only with `PNLCS_ALLOW_WRITES=1`

| Tool | What it does |
|---|---|
| `add_client` | Create a client; with `password2` it also opens a portal login |
| `create_invoice` | Invoice a client with one or more line items |
| `add_invoice_payment` | Record a payment; marks the invoice paid when covered |
| `open_ticket` | Open a support ticket for a customer, as staff |
| `add_ticket_reply` | Reply to a ticket as staff — signed by the credential's owner, emailed to the customer, ticket marked *Answered* |
| `suspend_service` | Suspend a hosting service **on its server** |
| `unsuspend_service` | Lift a suspension |

Without the flag the write tools are not merely hidden — calling one is
refused before any HTTP happens. An assistant wired up for reporting cannot
even see a suspend button. Give a reporting setup a read-only life by simply
not setting the flag.

---

## 4. Security notes

- The secret only ever travels between the machine running your AI client
  and your PNLCS install, over the same HTTPS API your admin screens use.
  It is never sent to the model provider; the model sees tool *results*.
- The identifier and secret travel in the `X-API-Key` / `X-API-Secret`
  request headers, never in the URL, so they do not end up in web server,
  proxy or request logs. (Versions before 1.0.5 sent them in the query
  string of every read - rotate the credential if you used one of those.)
- Prefer a **dedicated API credential** for MCP so you can revoke it alone.
- Restrict the credential to the address of the machine running your AI
  client: **Edit** on the API Credentials screen, **Allowed IP addresses**.
- PNLCS rate-limits API credentials (300 requests/minute per credential),
  so a runaway agent cannot hammer your install.
- Keep `PNLCS_ALLOW_WRITES` off unless you actually want the assistant
  acting on your books, and read what it proposes before approving tool
  calls that create or change things.

## 5. Troubleshooting

| Symptom | Cause |
|---|---|
| Every tool answers `Set PNLCS_URL, PNLCS_IDENTIFIER and PNLCS_SECRET.` | One of the three variables is missing from the client config |
| Every tool answers `Invalid API secret` | The secret does not belong to that identifier |
| Every tool answers `Authentication required...` | No active credential has that identifier: mistyped, switched off or deleted |
| `PNLCS did not answer within 30 seconds` | The install is unreachable from this machine — check the URL and any firewall |
| Tools missing in the client | Restart the client after editing its config; check its MCP log for the stderr line above |
| Write tools missing | That is the default — set `PNLCS_ALLOW_WRITES=1` |
| `Your account does not have permission for this action.` | The credential's owner lacks that permission in PNLCS — give their role the permission, or use a credential of a member of staff who has it |
| `The account this credential belongs to is disabled.` | The owning staff account was disabled in PNLCS; issue a credential from an active account |

## 6. Tests

`npm test` runs the offline suite: it spawns the real server process, speaks
the real protocol to it, and checks every tool's HTTP shape against a local
stub — plus the write-gate in both directions, batch requests from older
protocol revisions, version negotiation, timeouts and shutdown draining.

`node test/live.mjs` runs **every** tool against a real install — point it at
a demo, never at production books; it creates clearly-named disposable data
for the write tools and prints one PASS/FAIL line per tool.
