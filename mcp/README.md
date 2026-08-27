# pnlcs-mcp

A [Model Context Protocol](https://modelcontextprotocol.io) server for
[PNLCS](https://github.com/Panelica/pnlcs). Connect Claude Desktop, Cursor,
VS Code or any MCP client to your PNLCS install and ask it things in plain
English: *which invoices are overdue*, *show me this client's services*,
*any orders held as fraud today?*

Zero dependencies. The server is a single small Node process that talks to
the PNLCS admin API you already have.

## Setup

1. In PNLCS, create an API credential: **Configuration → API Credentials**.
2. Add the server to your MCP client. Claude Desktop example
   (`claude_desktop_config.json`):

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

Running from a checkout instead: `node mcp/server.js` with the same
environment variables.

## Tools

Read tools, always available:

| Tool | What it answers |
|---|---|
| `get_stats` | Client, order, invoice and revenue totals |
| `get_health` | Health of the install |
| `list_clients` / `get_client` | Clients, searchable; one client by id **or email** |
| `list_client_services` / `list_client_domains` | What a client has |
| `list_invoices` / `get_invoice` | Invoices, filterable by status (e.g. `overdue`) |
| `list_orders` | Orders, filterable by status (e.g. `fraud`) |
| `list_tickets` / `get_ticket` / `get_ticket_counts` | Support tickets |
| `list_transactions` | Payments, newest first |
| `list_products` | The product catalogue |
| `get_activity_log` | Recent admin and system activity |

Write tools exist **only** when you set `PNLCS_ALLOW_WRITES=1` — an assistant
wired up for reporting cannot even see them:

`add_client`, `create_invoice`, `add_invoice_payment`, `open_ticket`,
`add_ticket_reply`, `suspend_service`, `unsuspend_service`.

## Tests

`npm test` runs the offline suite: it spawns the real server process, speaks
the real protocol to it, and checks every tool's HTTP shape against a local
stub. `node test/live.mjs` runs every tool against a real install — point it
at a demo, not at your production books; it creates (and names) disposable
data for the write tools.
