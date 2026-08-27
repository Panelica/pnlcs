# pnlcs-mcp

A [Model Context Protocol](https://modelcontextprotocol.io) server for
[PNLCS](https://github.com/Panelica/pnlcs), the open-source billing panel.
Connect Claude Code, Claude Desktop, Cursor, VS Code or any other MCP client
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
2. Go to **Configuration → API Credentials**.
3. Click **Create**, give it a name like `mcp`, and copy the two values it
   shows you:
   - **Identifier** — the credential's username
   - **Secret** — shown once; store it somewhere safe

That pair is all the server needs, passed through three environment
variables:

| Variable | Value |
|---|---|
| `PNLCS_URL` | Your install's address, e.g. `https://billing.example.com` |
| `PNLCS_IDENTIFIER` | The credential's identifier |
| `PNLCS_SECRET` | The credential's secret |
| `PNLCS_ALLOW_WRITES` | Optional. Set to `1` to also enable the write tools (see below) |

---

## 2. Connect your client

### Claude Code (CLI)

One command:

```bash
claude mcp add pnlcs \
  --env PNLCS_URL=https://billing.example.com \
  --env PNLCS_IDENTIFIER=your_identifier \
  --env PNLCS_SECRET=your_secret \
  -- npx -y pnlcs-mcp
```

Add `--env PNLCS_ALLOW_WRITES=1` if you also want the write tools.
Use `--scope project` to share the entry with your team via `.mcp.json`
(put the secret in your shell environment, not in the committed file).
Check it with `claude mcp list`; remove it with `claude mcp remove pnlcs`.

### Claude Desktop

Edit `claude_desktop_config.json`
(macOS: `~/Library/Application Support/Claude/`, Windows: `%APPDATA%\Claude\`):

```json
{
  "mcpServers": {
    "pnlcs": {
      "command": "npx",
      "args": ["-y", "pnlcs-mcp"],
      "env": {
        "PNLCS_URL": "https://billing.example.com",
        "PNLCS_IDENTIFIER": "your_identifier",
        "PNLCS_SECRET": "your_secret"
      }
    }
  }
}
```

Restart Claude Desktop; the tools appear under the hammer icon.

### Cursor

**Settings → MCP → Add new global MCP server**, or create `.cursor/mcp.json`
in your project (same JSON shape as Claude Desktop above).

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
        "PNLCS_URL": "https://billing.example.com",
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
| `get_stats` | Client, order, invoice and revenue totals |
| `get_health` | Health of the install itself |
| `list_clients` | Clients, searchable by name/email/company, pageable |
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
| `open_ticket` | Open a support ticket |
| `add_ticket_reply` | Reply to a ticket |
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
- Prefer a **dedicated API credential** for MCP so you can revoke it alone.
- PNLCS rate-limits API credentials (300 requests/minute per credential),
  so a runaway agent cannot hammer your install.
- Keep `PNLCS_ALLOW_WRITES` off unless you actually want the assistant
  acting on your books, and read what it proposes before approving tool
  calls that create or change things.

## 5. Troubleshooting

| Symptom | Cause |
|---|---|
| `Set PNLCS_URL, PNLCS_IDENTIFIER and PNLCS_SECRET.` on stderr | One of the three variables is missing from the client config |
| Every tool answers `Invalid API secret` | Identifier/secret pair is wrong, or the credential was deactivated |
| `PNLCS did not answer within 30 seconds` | The install is unreachable from this machine — check the URL and any firewall |
| Tools missing in the client | Restart the client after editing its config; check its MCP log for the stderr line above |
| Write tools missing | That is the default — set `PNLCS_ALLOW_WRITES=1` |

## 6. Tests

`npm test` runs the offline suite: it spawns the real server process, speaks
the real protocol to it, and checks every tool's HTTP shape against a local
stub — plus the write-gate in both directions, batch requests from older
protocol revisions, version negotiation, timeouts and shutdown draining.

`node test/live.mjs` runs **every** tool against a real install — point it at
a demo, never at production books; it creates clearly-named disposable data
for the write tools and prints one PASS/FAIL line per tool.
